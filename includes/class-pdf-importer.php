<?php

/**
 * Leest een PDF-inschrijflijst via de Claude API en maakt athlete-posts aan.
 */
class STA_PDF_Importer {

    private string $api_key;
    private ?STA_Import_Job $job = null;

    public function __construct() {
        $this->api_key = get_option( 'sta_anthropic_api_key', '' );
    }

    public function set_job( STA_Import_Job $job ): void {
        $this->job = $job;
    }

    private function progress( int $pct, string $msg ): void {
        if ( $this->job ) {
            $this->job->set_progress( $pct, $msg );
        }
    }

    /** Import vanuit een $_FILES element (legacy, niet meer gebruikt voor grote PDF's) */
    public function import( array $file ): array|WP_Error {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Voer een Anthropic API-sleutel in bij de instellingen.' );
        }

        $mime = $file['type'] ?? '';
        if ( ! in_array( $mime, [ 'application/pdf', 'application/octet-stream' ], true ) ) {
            // Controleer extensie als MIME niet klopt
            if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'pdf' ) {
                return new WP_Error( 'invalid_file', 'Upload een PDF-bestand.' );
            }
        }

        $pdf_data = file_get_contents( $file['tmp_name'] );
        if ( ! $pdf_data ) {
            return new WP_Error( 'read_error', 'Kon het bestand niet lezen.' );
        }

        $athletes = $this->parse_pdf_with_claude( $pdf_data );
        if ( is_wp_error( $athletes ) ) {
            return $athletes;
        }

        return $this->create_posts( $athletes );
    }

    private function create_posts( array $athletes ): array {
        $created = [];
        $updated = [];
        $skipped = [];
        $total   = count( $athletes );
        $i       = 0;

        foreach ( $athletes as $athlete ) {
            $i++;
            $name = sanitize_text_field( $athlete['name'] ?? '' );
            if ( ! $name ) {
                continue;
            }

            if ( $i % 50 === 0 ) {
                $this->progress( 70 + intval( 25 * $i / $total ), "Posts aanmaken: {$i}/{$total}..." );
            }

            $existing = get_posts( [
                'post_type'   => 'athlete',
                'title'       => $name,
                'post_status' => 'any',
                'numberposts' => 1,
                'fields'      => 'ids',
            ] );

            $post_data = [
                'post_type'   => 'athlete',
                'post_title'  => $name,
                'post_status' => 'draft',
            ];

            if ( $existing ) {
                $post_id         = $existing[0];
                $post_data['ID'] = $post_id;
                wp_update_post( $post_data );
                $updated[] = $name;
            } else {
                $post_id   = wp_insert_post( $post_data );
                $created[] = $name;
            }

            if ( is_wp_error( $post_id ) ) {
                $skipped[] = $name;
                continue;
            }

            if ( ! empty( $athlete['club'] ) ) {
                update_post_meta( $post_id, 'sta_club', sanitize_text_field( $athlete['club'] ) );
            }

            if ( ! empty( $athlete['country'] ) ) {
                $code = strtoupper( sanitize_text_field( $athlete['country'] ) );
                if ( array_key_exists( $code, STA_CPT_Athlete::COUNTRIES ) ) {
                    update_post_meta( $post_id, 'sta_country', $code );
                }
            }

            if ( ! empty( $athlete['gender'] ) && in_array( $athlete['gender'], [ 'm', 'f' ], true ) ) {
                update_post_meta( $post_id, 'sta_gender', $athlete['gender'] );
            }

            if ( ! empty( $athlete['events'] ) && is_array( $athlete['events'] ) ) {
                update_post_meta( $post_id, 'sta_events', wp_json_encode( $athlete['events'] ) );
                $this->assign_afstand_terms( $post_id, $athlete['events'] );
            }

            update_post_meta( $post_id, 'sta_import_raw', wp_json_encode( $athlete ) );
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total'   => $total,
        ];
    }

    /**
     * Import vanuit een opgeslagen bestandspad (gebruikt door de achtergrond-processor).
     */
    public function import_from_path( string $path ): array|WP_Error {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Voer een Anthropic API-sleutel in bij de instellingen.' );
        }

        $pdf_data = file_get_contents( $path );
        if ( ! $pdf_data ) {
            return new WP_Error( 'read_error', 'Kon het PDF-bestand niet lezen.' );
        }

        $this->progress( 15, 'PDF gelezen, wordt naar Claude gestuurd...' );

        $athletes = $this->parse_pdf_with_claude( $pdf_data );
        if ( is_wp_error( $athletes ) ) {
            return $athletes;
        }

        $this->progress( 70, count( $athletes ) . ' atleten gevonden, WordPress-posts aanmaken...' );

        return $this->create_posts( $athletes );
    }

    /**
     * Zorgt dat elke afstand als term bestaat in de 'afstand'-taxonomie
     * en koppelt ze aan het athlete-post.
     *
     * @param int   $post_id
     * @param array $events  Bijv. ["100m Vrije slag", "50m Vlinderslag"]
     */
    private function assign_afstand_terms( int $post_id, array $events ): void {
        $term_ids = [];

        foreach ( $events as $event ) {
            $event = trim( $event );
            if ( ! $event ) {
                continue;
            }

            // Zoek bestaande term (case-insensitive via slug)
            $slug     = sanitize_title( $event );
            $existing = get_term_by( 'slug', $slug, 'afstand' );

            if ( $existing ) {
                $term_ids[] = $existing->term_id;
            } else {
                // Maak nieuwe term aan
                $result = wp_insert_term( $event, 'afstand', [ 'slug' => $slug ] );
                if ( ! is_wp_error( $result ) ) {
                    $term_ids[] = $result['term_id'];
                }
            }
        }

        if ( $term_ids ) {
            // append = true zodat bestaande termen van vorige import behouden blijven
            wp_set_object_terms( $post_id, $term_ids, 'afstand', true );
        }
    }

    /**
     * Stuurt het PDF naar Claude en vraagt om gestructureerde JSON terug.
     * Uploadt het PDF eerst via de Files API zodat de eigenlijke Claude-aanroep klein blijft.
     */
    private function parse_pdf_with_claude( string $pdf_data ): array|WP_Error {
        $this->progress( 18, 'PDF uploaden naar Anthropic Files API...' );

        // Stap 1: upload PDF als bestand en krijg een file_id terug
        $file_id = $this->upload_pdf_file( $pdf_data );
        if ( is_wp_error( $file_id ) ) {
            // Fallback: base64 inline (voor het geval Files API niet beschikbaar is)
            $this->progress( 18, 'Files API niet beschikbaar, probeer base64 inline...' );
            return $this->parse_pdf_with_base64( $pdf_data );
        }

        $this->progress( 30, 'PDF geüpload (file_id: ' . $file_id . '). Claude verwerkt de lijst...' );

        $pdf_base64 = null; // niet meer nodig

        $result = $this->run_extraction( $file_id, null );

        // Verwijder het geüploade bestand na verwerking
        $this->delete_uploaded_file( $file_id );

        return $result;
    }

    /** Fallback: stuur PDF als base64 inline (als Files API niet werkt) */
    private function parse_pdf_with_base64( string $pdf_data ): array|WP_Error {
        return $this->run_extraction( null, base64_encode( $pdf_data ) );
    }

    /**
     * Voert de extractie uit via streaming. Gebruikt file_id of base64, niet allebei.
     */
    private function run_extraction( ?string $file_id, ?string $base64 ): array|WP_Error {
        $prompt = 'Extract ALL athletes from this swimming competition registration PDF.\n\nReturn a JSON array only — no markdown, no explanation. Each element:\n{"name":"Full Name","club":"Club or null","country":"NL","gender":"m","events":["100m Freestyle"]}\n\nRules:\n- country: ISO-3166-1 alpha-2 uppercase, derive from club/flag/text, null if unknown\n- gender: "m" male, "f" female, null if unknown\n- events: English names — Freestyle, Breaststroke, Backstroke, Butterfly, Individual Medley\n- Compact JSON, close array with ]\n\nExample:\n[{"name":"Jan de Vries","club":"ZV Amsterdam","country":"NL","gender":"m","events":["100m Freestyle","50m Butterfly"]}]';

        $doc_source = $file_id
            ? [ 'type' => 'file', 'file_id' => $file_id ]
            : [ 'type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64 ];

        $messages = [ [
            'role'    => 'user',
            'content' => [
                [ 'type' => 'document', 'source' => $doc_source ],
                [ 'type' => 'text', 'text' => $prompt ],
            ],
        ] ];

        $result = $this->call_claude_streaming( $messages, 32000 );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $accumulated = $result['text'];

        $max_continuations = 8;
        $i = 0;
        while ( $result['stop_reason'] === 'max_tokens' && $i < $max_continuations ) {
            $i++;
            $partial    = $this->parse_json_athletes( $accumulated );
            $done_count = is_array( $partial ) ? count( $partial ) : 0;
            $last_name  = is_array( $partial ) && $done_count > 0 ? ( end( $partial )['name'] ?? '' ) : '';

            $cont_messages = [ [
                'role'    => 'user',
                'content' => [
                    [ 'type' => 'document', 'source' => $doc_source ],
                    [ 'type' => 'text', 'text' => "Already extracted {$done_count} athletes, last was \"{$last_name}\". Give ONLY the remaining athletes in the same JSON format. Start with [ end with ]. No explanation." ],
                ],
            ] ];

            $result = $this->call_claude_streaming( $cont_messages, 32000 );
            if ( is_wp_error( $result ) ) { break; }

            $extra = $this->parse_json_athletes( $result['text'] );
            if ( is_array( $extra ) && ! empty( $extra ) ) {
                $accumulated  = rtrim( rtrim( $accumulated ), ']' );
                $accumulated .= ',' . ltrim( wp_json_encode( $extra ), '[' );
            }
        }

        return $this->parse_json_athletes( $accumulated );
    }

    /**
     * Upload een PDF naar de Anthropic Files API.
     * Geeft file_id terug of WP_Error.
     */
    private function upload_pdf_file( string $pdf_data ): string|WP_Error {
        if ( ! function_exists( 'curl_init' ) ) {
            return new WP_Error( 'no_curl', 'cURL niet beschikbaar.' );
        }

        // Schrijf PDF tijdelijk naar schijf voor multipart upload
        $tmp = tempnam( sys_get_temp_dir(), 'sta_pdf_' );
        file_put_contents( $tmp, $pdf_data );

        $ch = curl_init( 'https://api.anthropic.com/v1/files' );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->api_key,
                'anthropic-version: 2023-06-01',
                'anthropic-beta: files-api-2025-04-14',
            ],
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile( $tmp, 'application/pdf', 'registration.pdf' ),
            ],
        ] );

        $body  = curl_exec( $ch );
        $error = curl_error( $ch );
        $code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );
        @unlink( $tmp );

        if ( $error ) {
            return new WP_Error( 'curl_error', 'Files API upload fout: ' . $error );
        }

        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? "HTTP {$code}";
            return new WP_Error( 'files_api_error', 'Files API fout: ' . $msg );
        }

        $file_id = $data['id'] ?? '';
        if ( ! $file_id ) {
            return new WP_Error( 'files_api_error', 'Geen file_id ontvangen van Files API.' );
        }

        return $file_id;
    }

    /** Verwijder een geüpload bestand van de Files API */
    private function delete_uploaded_file( string $file_id ): void {
        if ( ! function_exists( 'curl_init' ) ) return;

        $ch = curl_init( 'https://api.anthropic.com/v1/files/' . $file_id );
        curl_setopt_array( $ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->api_key,
                'anthropic-version: 2023-06-01',
                'anthropic-beta: files-api-2025-04-14',
            ],
        ] );
        curl_exec( $ch );
        curl_close( $ch );
    }

    /**
     * Roept de Claude API aan via streaming (SSE) met directe cURL.
     * Streaming voorkomt timeouts: elke ontvangen chunk reset de inactiviteitstimer.
     *
     * @return array{text: string, stop_reason: string}|WP_Error
     */
    private function call_claude_streaming( array $messages, int $max_tokens ): array|WP_Error {
        if ( ! function_exists( 'curl_init' ) ) {
            return new WP_Error( 'no_curl', 'cURL is niet beschikbaar op deze server.' );
        }

        $body = wp_json_encode( [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => $max_tokens,
            'stream'     => true,
            'messages'   => $messages,
        ] );

        $accumulated_text = '';
        $stop_reason      = 'end_turn';
        $http_status      = 0;
        $error_body       = '';
        $in_error         = false;

        $ch = curl_init( 'https://api.anthropic.com/v1/messages' );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '         . $this->api_key,
                'anthropic-version: 2023-06-01',
                'anthropic-beta: output-128k-2025-02-19,files-api-2025-04-14',
                'content-type: application/json',
                'accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT        => 600,   // max 10 min totaal
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            // Verwerk het response-header-blok
            CURLOPT_HEADERFUNCTION => function( $ch, $header ) use ( &$http_status, &$in_error ) {
                if ( preg_match( '/^HTTP\/\S+\s+(\d+)/i', $header, $m ) ) {
                    $http_status = (int) $m[1];
                    $in_error    = $http_status !== 200;
                }
                return strlen( $header );
            },
            // Verwerk de response-body chunk voor chunk
            CURLOPT_WRITEFUNCTION  => function( $ch, $chunk ) use ( &$accumulated_text, &$stop_reason, &$error_body, &$in_error ) {
                if ( $in_error ) {
                    $error_body .= $chunk;
                    return strlen( $chunk );
                }

                foreach ( explode( "\n", $chunk ) as $line ) {
                    $line = trim( $line );
                    if ( ! str_starts_with( $line, 'data: ' ) ) {
                        continue;
                    }
                    $json  = substr( $line, 6 );
                    $event = json_decode( $json, true );
                    if ( ! is_array( $event ) ) {
                        continue;
                    }

                    $type = $event['type'] ?? '';

                    if ( $type === 'content_block_delta' ) {
                        $accumulated_text .= $event['delta']['text'] ?? '';

                        // Update job-voortgang elke ~500 tokens zodat de balk beweegt
                        if ( $this->job && strlen( $accumulated_text ) % 2000 < 50 ) {
                            $tokens_est = intval( strlen( $accumulated_text ) / 4 );
                            $pct        = min( 90, 30 + intval( $tokens_est / 800 ) );
                            $this->job->set_progress( $pct, "Claude streamt... (~{$tokens_est} tokens ontvangen)" );
                        }
                    } elseif ( $type === 'message_delta' ) {
                        $stop_reason = $event['delta']['stop_reason'] ?? $stop_reason;
                    }
                }
                return strlen( $chunk );
            },
        ] );

        curl_exec( $ch );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            return new WP_Error( 'curl_error', 'cURL fout: ' . $curl_error );
        }

        if ( $http_status !== 200 ) {
            $api_err = json_decode( $error_body, true );
            $msg     = $api_err['error']['message'] ?? "HTTP {$http_status}: " . substr( $error_body, 0, 200 );
            return new WP_Error( 'api_error', 'Claude API fout: ' . $msg );
        }

        return [
            'text'        => $accumulated_text,
            'stop_reason' => $stop_reason,
        ];
    }

    /**
     * Parseert de geaccumuleerde tekst naar een array van atleten.
     * Herstelt ook een afgekapte JSON-array zo goed mogelijk.
     */
    private function parse_json_athletes( string $text ): array|WP_Error {
        $text = trim( $text );

        // Verwijder eventuele markdown-code-fencing
        $text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
        $text = preg_replace( '/\s*```$/i', '', trim( $text ) );
        $text = trim( $text );

        // Probeer eerst directe parse
        $athletes = json_decode( $text, true );
        if ( is_array( $athletes ) ) {
            return $athletes;
        }

        // JSON is afgekapt: zoek het laatste complete object en sluit de array
        $last_brace = strrpos( $text, '}' );
        if ( $last_brace !== false ) {
            $repaired = substr( $text, 0, $last_brace + 1 ) . ']';
            $athletes = json_decode( $repaired, true );
            if ( is_array( $athletes ) ) {
                return $athletes;
            }
        }

        return new WP_Error(
            'parse_error',
            'Kon de JSON-output van Claude niet verwerken. Ontvangen: ' . substr( $text, 0, 300 )
        );
    }
}
