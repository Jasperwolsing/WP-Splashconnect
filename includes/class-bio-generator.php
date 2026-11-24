<?php

class STA_Bio_Generator {

    // Placeholder die in de prompt vervangen wordt door de atleetnaam
    const NAME_PLACEHOLDER = '{naam}';

    // Standaard-prompt — wordt gebruikt als er niets in de instellingen staat
    const DEFAULT_PROMPT = 'Schrijf een korte, feitelijke bio voor zwematleet {naam} voor de website SwimmingTrials.nl.

Inschrijvingen voor dit toernooi:
{events}

Club: {club}

Aanvullende informatie van de sprekers (hoge betrouwbaarheid, gebruik dit bij voorkeur):
{speaker_notes}

Beschikbare informatie uit webzoekresultaten:
{context}

Richtlijnen:
- Schrijf in het Nederlands, in de derde persoon
- Begin direct met een feit of prestatie, niet met "{naam} is een zwemmer"
- Geef prioriteit aan de informatie van de sprekers boven webzoekresultaten
- Noem concrete prestaties, kampioenschappen, records of specialisaties als die bekend zijn uit de informatie
- Als er geen informatie beschikbaar is, schrijf dan 1 alinea over de deelname aan dit toernooi zonder te speculeren
- Geen twijfelachtige formuleringen ("zou kunnen", "mogelijk", "staat bekend als") — schrijf alleen wat zeker is
- Geen markdown, geen koppen, geen opsommingstekens — alleen lopende tekst
- Maximaal 2 alinea\'s';

    private string $anthropic_key;
    private string $serper_key;
    private array  $last_debug = [];

    public function get_last_debug(): array { return $this->last_debug; }

    public function __construct() {
        $this->anthropic_key = get_option( 'sta_anthropic_api_key', '' );
        $this->serper_key    = get_option( 'sta_serper_api_key', '' );
    }

    public static function get_prompt_template(): string {
        $saved = get_option( 'sta_bio_prompt', '' );
        return $saved ?: self::DEFAULT_PROMPT;
    }

    /**
     * Genereert een bio voor de gegeven naam.
     *
     * @return string|WP_Error
     */
    public function get_context( string $name ): string {
        return $this->search_web( $name ) ?: '(geen webresultaten gevonden)';
    }

    public function generate( string $name, int $post_id = 0 ): string|WP_Error {
        if ( empty( $this->anthropic_key ) ) {
            return new WP_Error( 'no_api_key', 'Voer een Anthropic API-sleutel in.' );
        }

        $events         = [];
        $club           = '';
        $speaker_notes  = '';
        if ( $post_id ) {
            $terms = wp_get_post_terms( $post_id, 'afstand', [ 'orderby' => 'name' ] );
            if ( ! is_wp_error( $terms ) ) {
                $events = wp_list_pluck( $terms, 'name' );
            }
            $club          = get_post_meta( $post_id, 'sta_club', true ) ?: '';
            $speaker_notes = get_post_meta( $post_id, 'sta_notes_public', true ) ?: '';
        }

        $context = $this->search_web( $name );
        return $this->generate_bio_with_claude( $name, $context, $events, $club, $speaker_notes );
    }

    private function search_web( string $name ): string {
        $this->last_debug = [];

        if ( empty( $this->serper_key ) ) {
            $this->last_debug[] = 'Serper-sleutel niet ingevuld.';
            return '';
        }

        $queries  = [ $name . ' swimmer', $name . ' zwemmer' ];
        $snippets = [];

        foreach ( $queries as $query ) {
            $response = wp_remote_post( 'https://google.serper.dev/search', [
                'timeout' => 10,
                'headers' => [
                    'X-API-KEY'    => $this->serper_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'q'   => $query,
                    'gl'  => 'nl',
                    'hl'  => 'nl',
                    'num' => 5,
                ] ),
            ] );

            if ( is_wp_error( $response ) ) {
                $msg = 'WP_Error voor "' . $query . '": ' . $response->get_error_message();
                error_log( '[STA Serper] ' . $msg );
                $this->last_debug[] = $msg;
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $raw  = wp_remote_retrieve_body( $response );
            $data = json_decode( $raw, true );

            if ( $code !== 200 ) {
                $msg = 'HTTP ' . $code . ' voor "' . $query . '": ' . substr( $raw, 0, 300 );
                error_log( '[STA Serper] ' . $msg );
                $this->last_debug[] = $msg;
                continue;
            }

            $count = count( $data['organic'] ?? [] );
            $this->last_debug[] = 'Query "' . $query . '": ' . $count . ' resultaten.';
            $results = $data['organic'] ?? [];

            foreach ( $results as $result ) {
                $snippet = trim( $result['snippet'] ?? '' );
                $title   = trim( $result['title']   ?? '' );
                $date    = trim( $result['date']     ?? '' );
                if ( $snippet ) {
                    $prefix     = $date ? '[' . $date . '] ' : '';
                    $snippets[] = $prefix . $title . ': ' . $snippet;
                }
            }

            usleep( 200000 );
        }

        return implode( "\n\n", array_slice( $snippets, 0, 8 ) );
    }

    private function generate_bio_with_claude( string $name, string $context, array $events = [], string $club = '', string $speaker_notes = '' ): string|WP_Error {
        $context_text = $context
            ? $context
            : 'Geen webzoekresultaten beschikbaar. Schrijf alleen op basis van de deelname aan SwimmingTrials.nl.';

        $events_text = $events
            ? implode( ', ', $events )
            : 'Geen afstanden bekend.';

        $notes_text = $speaker_notes ?: '';

        $template = self::get_prompt_template();

        $prompt = str_replace(
            [ self::NAME_PLACEHOLDER, '{context}', '{events}', '{club}', '{speaker_notes}', '{today}' ],
            [ $name, $context_text, $events_text, $club ?: 'onbekend', $notes_text, wp_date( 'd F Y' ) ],
            $template
        );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $this->anthropic_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 600,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? 'HTTP ' . $code;
            return new WP_Error( 'api_error', 'Claude API fout bij bio: ' . $msg );
        }

        $text = trim( $body['content'][0]['text'] ?? '' );

        // Verwijder eventuele markdown-kop bovenaan (# Naam of ## Naam)
        $text = preg_replace( '/^#{1,3}\s+.+\n+/u', '', $text );

        return trim( $text );
    }
}
