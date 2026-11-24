<?php

/**
 * Zoekt foto's op Flickr in de stream van kjvoverbeeke.
 */
class STA_Flickr_Client {

    const API_BASE = 'https://www.flickr.com/services/rest/';

    private string $api_key;
    private string $photographer_username;
    private string $user_nsid = '';

    public function __construct() {
        $this->api_key               = get_option( 'sta_flickr_api_key', '' );
        $this->photographer_username = get_option( 'sta_flickr_username', 'kjvoverbeeke' );
    }

    /**
     * Vindt de beste foto voor een atleet.
     * Geeft array terug met url/id/page, null als niet gevonden, WP_Error bij API-fout.
     * Het 'debug' veld bevat een logboek van de pogingen.
     */
    /**
     * Vindt de beste foto voor een atleet (eerste resultaat) + kandidaten.
     * Geeft array terug met url/id/page + candidates[], null als niet gevonden.
     */
    public function find_photo_for_athlete( string $name ): array|WP_Error|null {
        $candidates = $this->find_photos_for_athlete( $name );
        if ( is_wp_error( $candidates ) || empty( $candidates['photos'] ) ) {
            return $candidates instanceof \WP_Error ? $candidates : null;
        }
        $first = $candidates['photos'][0];
        $first['candidates'] = $candidates['photos'];
        $first['debug']      = $candidates['debug'];
        return $first;
    }

    /**
     * Geeft tot 5 kandidaatfoto's terug voor een atleet.
     * Return: ['photos' => [...], 'debug' => [...]] of WP_Error
     */
    public function find_photos_for_athlete( string $name, int $per_page = 10, int $page = 1 ): array|WP_Error {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Voer een Flickr API-sleutel in bij de instellingen.' );
        }

        $nsid = $this->get_user_nsid();
        if ( is_wp_error( $nsid ) ) {
            return $nsid;
        }

        $debug       = [];
        $parts       = array_values( array_filter( explode( ' ', $name ) ) );
        $first       = mb_strtolower( $parts[0] ?? '', 'UTF-8' );
        $last        = mb_strtolower( end( $parts ), 'UTF-8' );
        $first_ascii = self::ascii( $parts[0] ?? '' );
        $last_ascii  = self::ascii( end( $parts ) );

        // Bouw zoekvarianten: originele naam + ASCII-variant (zonder diacritics)
        $name_ascii  = self::ascii_preserve_case( $name );
        $queries_full = array_unique( array_filter( [ $name, $name !== $name_ascii ? $name_ascii : null ] ) );

        // Strategie 1: volledige naam (en ASCII-variant als die verschilt)
        foreach ( $queries_full as $q ) {
            if ( ! empty( $photos ) ) break;
            $photos = $this->search_by_text( $nsid, $q, $debug, $per_page, $page );
            $photos = $this->filter_by_name( $photos, $first, $last, $first_ascii, $last_ascii, $debug );
        }

        // Strategie 2: voornaam + achternaam zonder tussenvoegsel (alleen bij 3+ delen, alleen op pagina 1)
        if ( empty( $photos ) && $page === 1 && count( $parts ) >= 3 ) {
            $short       = $parts[0] . ' ' . end( $parts );
            $short_ascii = self::ascii_preserve_case( $short );
            foreach ( array_unique( array_filter( [ $short, $short !== $short_ascii ? $short_ascii : null ] ) ) as $q ) {
                if ( ! empty( $photos ) ) break;
                $photos = $this->search_by_text( $nsid, $q, $debug, $per_page, 1 );
                $photos = $this->filter_by_name( $photos, $first, $last, $first_ascii, $last_ascii, $debug );
            }
        }

        return [ 'photos' => $photos, 'debug' => $debug ];
    }

    /**
     * Strips diacritics from a UTF-8 string, returning ASCII-only lowercase.
     * Uses a manual map so it works reliably regardless of server locale/iconv settings.
     */
    /** Zoals ascii() maar behoudt originele case — voor gebruik als zoekopdracht. */
    private static function ascii_preserve_case( string $s ): string {
        $lower = self::ascii( $s );
        // Herstel hoofdletters op posities waar het origineel een hoofdletter had
        $result = '';
        $i_orig = 0; $i_low = 0;
        $orig_chars = preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY );
        $low_chars  = str_split( $lower );
        // Eenvoudiger: vervang karakter voor karakter op basis van positie in de ASCII output
        $map_upper = [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE',
            'Ç'=>'C','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y',
            'Ā'=>'A','Ă'=>'A','Ą'=>'A','Ć'=>'C','Č'=>'C','Ď'=>'D',
            'Ě'=>'E','Ę'=>'E','Ğ'=>'G','Ī'=>'I','Į'=>'I','Ķ'=>'K',
            'Ĺ'=>'L','Ļ'=>'L','Ľ'=>'L','Ł'=>'L','Ń'=>'N','Ņ'=>'N','Ň'=>'N',
            'Ő'=>'O','Ŕ'=>'R','Ŗ'=>'R','Ř'=>'R','Ś'=>'S','Ş'=>'S','Š'=>'S',
            'Ţ'=>'T','Ť'=>'T','Ū'=>'U','Ů'=>'U','Ű'=>'U','Ų'=>'U',
            'Ź'=>'Z','Ż'=>'Z','Ž'=>'Z','Ð'=>'D','Þ'=>'TH','ß'=>'ss',
        ];
        $map_lower = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
            'ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y',
            'ā'=>'a','ă'=>'a','ą'=>'a','ć'=>'c','č'=>'c','ď'=>'d',
            'ě'=>'e','ę'=>'e','ğ'=>'g','ī'=>'i','į'=>'i','ķ'=>'k',
            'ĺ'=>'l','ļ'=>'l','ľ'=>'l','ł'=>'l','ń'=>'n','ņ'=>'n','ň'=>'n',
            'ő'=>'o','ŕ'=>'r','ŗ'=>'r','ř'=>'r','ś'=>'s','ş'=>'s','š'=>'s',
            'ţ'=>'t','ť'=>'t','ū'=>'u','ů'=>'u','ű'=>'u','ų'=>'u',
            'ź'=>'z','ż'=>'z','ž'=>'z','ð'=>'d','þ'=>'th',
        ];
        $full_map = $map_upper + $map_lower;
        foreach ( $orig_chars as $ch ) {
            $result .= $full_map[ $ch ] ?? $ch;
        }
        return $result;
    }

    private static function ascii( string $s ): string {
        $s   = mb_strtolower( $s, 'UTF-8' );
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
            'ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y',
            'ā'=>'a','ă'=>'a','ą'=>'a','ć'=>'c','č'=>'c','ď'=>'d',
            'ě'=>'e','ę'=>'e','ğ'=>'g','ī'=>'i','į'=>'i','ķ'=>'k',
            'ĺ'=>'l','ļ'=>'l','ľ'=>'l','ł'=>'l','ń'=>'n','ņ'=>'n','ň'=>'n',
            'ő'=>'o','ŕ'=>'r','ŗ'=>'r','ř'=>'r','ś'=>'s','ş'=>'s','š'=>'s',
            'ţ'=>'t','ť'=>'t','ū'=>'u','ů'=>'u','ű'=>'u','ų'=>'u',
            'ź'=>'z','ż'=>'z','ž'=>'z','ð'=>'d','þ'=>'th','ß'=>'ss',
        ];
        return strtr( $s, $map );
    }

    /**
     * Behoudt alleen foto's waarvan de tags OF titel zowel de voornaam als achternaam bevatten.
     * Vergelijkt zowel met de originele als de ASCII-variant van voor- en achternaam.
     */
    private function filter_by_name( array $photos, string $first, string $last, string $first_ascii, string $last_ascii, array &$debug ): array {
        $filtered = [];
        foreach ( $photos as $photo ) {
            $haystack       = mb_strtolower( $photo['title'] . ' ' . $photo['tags'], 'UTF-8' );
            $haystack_ascii = self::ascii( $photo['title'] . ' ' . $photo['tags'] );
            $has_first = str_contains( $haystack, $first ) || str_contains( $haystack_ascii, $first_ascii );
            $has_last  = str_contains( $haystack, $last )  || str_contains( $haystack_ascii, $last_ascii );
            if ( $has_first && $has_last ) {
                $filtered[] = $photo;
            } else {
                $debug[] = "Gefilterd: '{$photo['title']}' (voornaam=" . ($has_first?'✓':'✗') . ", achternaam=" . ($has_last?'✓':'✗') . ")";
            }
        }
        return $filtered;
    }

    /**
     * Zoekt via flickr.photos.search; geeft array van photo-arrays terug.
     */
    public function search_by_tag( string $tag, int $page = 1, int $per_page = 100 ): array|WP_Error {
        $user_id = $this->get_user_nsid();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $params = [
            'method'         => 'flickr.photos.search',
            'api_key'        => $this->api_key,
            'user_id'        => $user_id,
            'tags'           => $tag,
            'tag_mode'       => 'all',
            'sort'           => 'date-posted-desc',
            'per_page'       => $per_page,
            'page'           => $page,
            'format'         => 'json',
            'nojsoncallback' => 1,
            'extras'         => 'url_l,url_c,url_z,url_m,url_sq,tags,title',
        ];

        $url      = add_query_arg( $params, self::API_BASE );
        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ( $body['stat'] ?? '' ) !== 'ok' ) {
            return new WP_Error( 'flickr_error', $body['message'] ?? 'Onbekende Flickr-fout' );
        }

        $photos_data = $body['photos'] ?? [];
        $items       = $photos_data['photo'] ?? [];
        $pages       = (int) ( $photos_data['pages'] ?? 1 );

        $photos = [];
        foreach ( $items as $photo ) {
            $id     = $photo['id'];
            $secret = $photo['secret'];
            $server = $photo['server'];

            $img_url = $photo['url_l']
                ?? $photo['url_c']
                ?? $photo['url_z']
                ?? $photo['url_m']
                ?? "https://live.staticflickr.com/{$server}/{$id}_{$secret}_b.jpg";

            $photos[] = [
                'flickr_id' => $id,
                'url'       => $img_url,
                'thumb'     => "https://live.staticflickr.com/{$server}/{$id}_{$secret}_q.jpg",
                'page'      => sprintf( 'https://www.flickr.com/photos/%s/%s', $this->photographer_username, $id ),
                'title'     => $photo['title'] ?? '',
                'tags_raw'  => $photo['tags']  ?? '',
            ];
        }

        return [ 'photos' => $photos, 'pages' => $pages ];
    }

    private function search_by_text( string $user_id, string $query, array &$debug, int $per_page = 5, int $page = 1 ): array {
        $params = [
            'method'         => 'flickr.photos.search',
            'api_key'        => $this->api_key,
            'user_id'        => $user_id,
            'text'           => $query,
            'sort'           => 'date-taken-desc',
            'per_page'       => $per_page,
            'page'           => $page,
            'format'         => 'json',
            'nojsoncallback' => 1,
            'extras'         => 'url_l,url_c,url_z,url_m,url_sq,tags',
        ];

        $url      = add_query_arg( $params, self::API_BASE );
        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) ) {
            $debug[] = "text:'{$query}' → WP_Error: " . $response->get_error_message();
            return [];
        }

        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $stat  = $body['stat'] ?? 'fail';
        $items = $body['photos']['photo'] ?? [];
        $debug[] = "text:'{$query}' → stat={$stat}, resultaten=" . count( $items );

        if ( $stat !== 'ok' || empty( $items ) ) {
            return [];
        }

        $results = [];
        foreach ( $items as $photo ) {
            $id      = $photo['id'];
            $secret  = $photo['secret'];
            $server  = $photo['server'];

            $img_url = $photo['url_l']
                ?? $photo['url_c']
                ?? $photo['url_z']
                ?? $photo['url_m']
                ?? "https://live.staticflickr.com/{$server}/{$id}_{$secret}_b.jpg";

            // Kleine thumbnail voor de kiezer (150×150 square crop via _q suffix)
            $thumb_url = "https://live.staticflickr.com/{$server}/{$id}_{$secret}_q.jpg";

            $results[] = [
                'id'    => $id,
                'url'   => $img_url,
                'thumb' => $thumb_url,
                'page'  => sprintf( 'https://www.flickr.com/photos/%s/%s', $this->photographer_username, $id ),
                'title' => $photo['title'] ?? '',
                'tags'  => $photo['tags']  ?? '',
            ];
        }

        return $results;
    }

    private function get_user_nsid(): string|WP_Error {
        if ( $this->user_nsid ) {
            return $this->user_nsid;
        }

        $cached = get_transient( 'sta_flickr_nsid' );
        if ( $cached ) {
            $this->user_nsid = $cached;
            return $cached;
        }

        // Gebruik lookupUser op basis van de profielURL — werkt altijd,
        // ook als de URL-alias verschilt van de geregistreerde gebruikersnaam.
        $profile_url = 'https://www.flickr.com/photos/' . $this->photographer_username . '/';

        $response = wp_remote_get( add_query_arg( [
            'method'         => 'flickr.urls.lookupUser',
            'api_key'        => $this->api_key,
            'url'            => $profile_url,
            'format'         => 'json',
            'nojsoncallback' => 1,
        ], self::API_BASE ), [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ( $body['stat'] ?? '' ) !== 'ok' ) {
            $msg = $body['message'] ?? 'onbekend';
            return new WP_Error( 'flickr_error', "Flickr profiel '{$profile_url}' niet gevonden: {$msg}" );
        }

        $nsid = $body['user']['id'] ?? '';
        if ( ! $nsid ) {
            return new WP_Error( 'flickr_error', 'Geen NSID ontvangen van Flickr.' );
        }

        set_transient( 'sta_flickr_nsid', $nsid, DAY_IN_SECONDS );
        $this->user_nsid = $nsid;

        return $nsid;
    }
}
