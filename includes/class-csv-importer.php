<?php

class STA_CSV_Importer {

    private ?STA_Import_Job $job = null;

    private const IOC_TO_ISO2 = [
        'AFG'=>'AF','ALB'=>'AL','ALG'=>'DZ','AND'=>'AD','ANG'=>'AO','ANT'=>'AG','ARG'=>'AR','ARM'=>'AM',
        'ARU'=>'AW','ASA'=>'AS','AUS'=>'AU','AUT'=>'AT','AZE'=>'AZ','BAH'=>'BS','BAN'=>'BD','BAR'=>'BB',
        'BDI'=>'BI','BEL'=>'BE','BEN'=>'BJ','BER'=>'BM','BHU'=>'BT','BIH'=>'BA','BIZ'=>'BZ','BLR'=>'BY',
        'BOL'=>'BO','BOT'=>'BW','BRA'=>'BR','BRN'=>'BH','BRU'=>'BN','BUL'=>'BG','BUR'=>'BF','CAF'=>'CF',
        'CAM'=>'KH','CAN'=>'CA','CAY'=>'KY','CGO'=>'CG','CHA'=>'TD','CHI'=>'CL','CHN'=>'CN','CIV'=>'CI',
        'CMR'=>'CM','COD'=>'CD','COK'=>'CK','COL'=>'CO','COM'=>'KM','CPV'=>'CV','CRC'=>'CR','CRO'=>'HR',
        'CUB'=>'CU','CYP'=>'CY','CZE'=>'CZ','DEN'=>'DK','DJI'=>'DJ','DMA'=>'DM','DOM'=>'DO','ECU'=>'EC',
        'EGY'=>'EG','ERI'=>'ER','ESA'=>'SV','ESP'=>'ES','EST'=>'EE','ETH'=>'ET','FIJ'=>'FJ','FIN'=>'FI',
        'FRA'=>'FR','FSM'=>'FM','GAB'=>'GA','GAM'=>'GM','GBR'=>'GB','GBS'=>'GW','GEO'=>'GE','GEQ'=>'GQ',
        'GER'=>'DE','GHA'=>'GH','GRE'=>'GR','GRN'=>'GD','GUA'=>'GT','GUI'=>'GN','GUM'=>'GU','GUY'=>'GY',
        'HAI'=>'HT','HKG'=>'HK','HON'=>'HN','HUN'=>'HU','INA'=>'ID','IND'=>'IN','IRI'=>'IR','IRL'=>'IE',
        'IRQ'=>'IQ','ISL'=>'IS','ISR'=>'IL','ISV'=>'VI','ITA'=>'IT','IVB'=>'VG','JAM'=>'JM','JOR'=>'JO',
        'JPN'=>'JP','KAZ'=>'KZ','KEN'=>'KE','KGZ'=>'KG','KIR'=>'KI','KOR'=>'KR','KOS'=>'XK','KSA'=>'SA',
        'KUW'=>'KW','LAO'=>'LA','LAT'=>'LV','LBA'=>'LY','LBR'=>'LR','LCA'=>'LC','LES'=>'LS','LIB'=>'LB',
        'LIE'=>'LI','LTU'=>'LT','LUX'=>'LU','MAD'=>'MG','MAR'=>'MA','MAS'=>'MY','MAW'=>'MW','MDA'=>'MD',
        'MDV'=>'MV','MEX'=>'MX','MGL'=>'MN','MHL'=>'MH','MKD'=>'MK','MLI'=>'ML','MLT'=>'MT','MNE'=>'ME',
        'MON'=>'MC','MOZ'=>'MZ','MRI'=>'MU','MTN'=>'MR','MYA'=>'MM','NAM'=>'NA','NCA'=>'NI','NED'=>'NL',
        'NEP'=>'NP','NGR'=>'NG','NIG'=>'NE','NOR'=>'NO','NRU'=>'NR','NZL'=>'NZ','OMA'=>'OM','PAK'=>'PK',
        'PAN'=>'PA','PAR'=>'PY','PER'=>'PE','PHI'=>'PH','PLE'=>'PS','PLW'=>'PW','PNG'=>'PG','POL'=>'PL',
        'POR'=>'PT','PRK'=>'KP','PUR'=>'PR','QAT'=>'QA','ROU'=>'RO','RSA'=>'ZA','RUS'=>'RU','RWA'=>'RW',
        'SAM'=>'WS','SEN'=>'SN','SEY'=>'SC','SGP'=>'SG','SKN'=>'KN','SLE'=>'SL','SLO'=>'SI','SMR'=>'SM',
        'SOL'=>'SB','SOM'=>'SO','SRB'=>'RS','SRI'=>'LK','SSD'=>'SS','STP'=>'ST','SUD'=>'SD','SUI'=>'CH',
        'SUR'=>'SR','SVK'=>'SK','SWE'=>'SE','SWZ'=>'SZ','SYR'=>'SY','TAN'=>'TZ','TGA'=>'TO','THA'=>'TH',
        'TJK'=>'TJ','TKM'=>'TM','TLS'=>'TL','TOG'=>'TG','TPE'=>'TW','TTO'=>'TT','TUN'=>'TN','TUR'=>'TR',
        'TUV'=>'TV','UAE'=>'AE','UGA'=>'UG','UKR'=>'UA','URU'=>'UY','USA'=>'US','UZB'=>'UZ','VAN'=>'VU',
        'VEN'=>'VE','VIE'=>'VN','VIN'=>'VC','YEM'=>'YE','ZAM'=>'ZM','ZIM'=>'ZW',
    ];

    private const STROKE_MAP = [
        'FREE'   => 'Freestyle',
        'BACK'   => 'Backstroke',
        'BREAST' => 'Breaststroke',
        'FLY'    => 'Butterfly',
        'MEDLEY' => 'Individual Medley',
    ];

    // Programmanummers (Numer-kolom) die NJK-events zijn, met hun afstand-term.
    // Deze atleten krijgen een aparte taxonomy-term zodat ze niet op de senior afstandspagina's verschijnen.
    private const NJK_NUMER_MAP = [
        20 => 'NJK 1500m Freestyle U16', // Meisjes U16 – zaterdag series
        21 => 'NJK 800m Freestyle U16',  // Jongens U16 – zaterdag series
        45 => 'NJK 800m Freestyle U16',  // Meisjes U16 – zondag series
        46 => 'NJK 1500m Freestyle U16', // Jongens U16 – zondag series
        33 => 'NJK 800m Freestyle U14',  // Meisjes U14 – zondag series
        32 => 'NJK 1500m Freestyle U14', // Jongens U14 – zondag series
    ];

    public function set_job( STA_Import_Job $job ): void {
        $this->job = $job;
    }

    private function progress( int $pct, string $msg ): void {
        $this->job?->set_progress( $pct, $msg );
    }

    public function import_from_path( string $path ): array|WP_Error {
        $this->progress( 5, 'CSV bestand lezen...' );

        if ( ! file_exists( $path ) ) {
            return new WP_Error( 'file_not_found', 'CSV-bestand niet gevonden.' );
        }

        $handle = fopen( $path, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_error', 'Kon CSV-bestand niet openen.' );
        }

        // Detecteer BOM en sla header-rij op
        $raw_header = fgets( $handle );
        $raw_header = ltrim( $raw_header, "\xEF\xBB\xBF" ); // UTF-8 BOM
        $headers    = str_getcsv( trim( $raw_header ), ';' );
        $col        = array_flip( $headers );

        $athletes = [];

        while ( ( $line = fgetcsv( $handle, 0, ';' ) ) !== false ) {
            if ( count( $line ) < 10 ) continue;

            $athlete_id = trim( $line[ $col['ATHLETEID'] ] ?? '' );

            // Sla estafette-regels over (ATHLETEID = 0 of leeg, of RELAYCOUNT > 1)
            $relay_count = intval( $line[ $col['RELAYCOUNT'] ] ?? 1 );
            if ( ! $athlete_id || $athlete_id === '0' || $relay_count > 1 ) {
                continue;
            }

            // Sla inschrijvingen met status RJC over (rejected)
            $entry_status = isset( $col['STATUS'] ) ? strtoupper( trim( $line[ $col['STATUS'] ] ?? '' ) ) : '';
            if ( $entry_status === 'RJC' ) {
                continue;
            }

            $distance  = trim( $line[ $col['DISTANCE'] ] ?? '' );
            $stroke    = strtoupper( trim( $line[ $col['STROKE'] ] ?? '' ) );
            $stroke_en = self::STROKE_MAP[ $stroke ] ?? $stroke;
            $numer     = intval( $line[ $col['NUMBER'] ] ?? 0 );

            // NJK-events krijgen een eigen taxonomy-term zodat ze los staan van senioren.
            if ( $numer && isset( self::NJK_NUMER_MAP[ $numer ] ) ) {
                $event = self::NJK_NUMER_MAP[ $numer ];
            } else {
                $event = $distance ? "{$distance}m {$stroke_en}" : null;
            }

            if ( ! isset( $athletes[ $athlete_id ] ) ) {
                $firstname  = trim( $line[ $col['FIRSTNAME'] ]   ?? '' );
                $lastname   = trim( $line[ $col['LASTNAME'] ]    ?? '' );
                $prefix     = trim( $line[ $col['NAMEPREFIX'] ]  ?? '' );
                $name_parts = array_filter( [ $firstname, $prefix, $lastname ] );
                $name       = implode( ' ', $name_parts );

                $gender_raw = strtoupper( trim( $line[ $col['GENDER'] ] ?? '' ) );
                $gender     = match( $gender_raw ) {
                    'M' => 'm',
                    'F' => 'f',
                    default => null,
                };

                $nation3 = strtoupper( trim( $line[ $col['NATION'] ] ?? '' ) );
                $nation  = self::IOC_TO_ISO2[ $nation3 ] ?? ( strlen( $nation3 ) === 2 ? $nation3 : null );
                $club    = trim( $line[ $col['CLUBNAME'] ] ?? '' );

                $athletes[ $athlete_id ] = [
                    'name'    => $name,
                    'club'    => $club ?: null,
                    'country' => $nation ?: null,
                    'gender'  => $gender,
                    'events'  => [],
                ];
            }

            if ( $event && ! in_array( $event, $athletes[ $athlete_id ]['events'], true ) ) {
                $athletes[ $athlete_id ]['events'][] = $event;
            }
        }

        fclose( $handle );

        $athletes = array_values( $athletes );
        $total    = count( $athletes );

        if ( $total === 0 ) {
            return new WP_Error( 'no_athletes', 'Geen atleten gevonden in het CSV-bestand.' );
        }

        $this->progress( 40, "{$total} atleten gevonden. WordPress-posts aanmaken..." );

        return $this->create_posts( $athletes );
    }

    private function create_posts( array $athletes ): array {
        $created = [];
        $updated = [];
        $skipped = [];
        $total   = count( $athletes );

        require_once STA_DIR . 'includes/class-cpt-athlete.php';

        foreach ( $athletes as $i => $data ) {
            if ( empty( $data['name'] ) ) {
                $skipped[] = '(lege naam)';
                continue;
            }

            $pct = 40 + intval( ( $i / $total ) * 55 );
            if ( $i % 25 === 0 ) {
                $this->progress( $pct, "Posts aanmaken: {$i}/{$total}..." );
            }

            // Zoek bestaande post op naam
            $existing = get_posts( [
                'post_type'   => 'athlete',
                'post_status' => 'any',
                'title'       => $data['name'],
                'numberposts' => 1,
            ] );

            if ( $existing ) {
                $post_id = $existing[0]->ID;
                $updated[] = $data['name'];
            } else {
                $post_id = wp_insert_post( [
                    'post_type'   => 'athlete',
                    'post_title'  => sanitize_text_field( $data['name'] ),
                    'post_status' => 'draft',
                ] );

                if ( is_wp_error( $post_id ) ) {
                    $skipped[] = $data['name'];
                    continue;
                }

                $created[] = $data['name'];
            }

            if ( $data['club'] )    update_post_meta( $post_id, 'sta_club',    sanitize_text_field( $data['club'] ) );
            if ( $data['country'] ) update_post_meta( $post_id, 'sta_country', sanitize_text_field( $data['country'] ) );
            if ( $data['gender'] )  update_post_meta( $post_id, 'sta_gender',  $data['gender'] );

            if ( ! empty( $data['events'] ) ) {
                $this->assign_afstand_terms( $post_id, $data['events'] );
            }
        }

        $this->progress( 98, 'Bijna klaar...' );

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function assign_afstand_terms( int $post_id, array $events ): void {
        $term_ids = [];
        foreach ( $events as $event ) {
            $slug   = sanitize_title( $event );
            $term   = get_term_by( 'slug', $slug, 'afstand' );
            if ( ! $term ) {
                $result = wp_insert_term( $event, 'afstand', [ 'slug' => $slug ] );
                if ( ! is_wp_error( $result ) ) {
                    $term_ids[] = $result['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        if ( $term_ids ) {
            wp_set_object_terms( $post_id, $term_ids, 'afstand', false );
        }
    }
}
