<?php
/**
 * Plugin-updates via GitHub Releases (publieke repo, geen token nodig).
 *
 * Een release met tag "vX.Y.Z" wordt als update aangeboden zodra X.Y.Z hoger is
 * dan de geïnstalleerde versie. Bij voorkeur wordt de bijgevoegde asset
 * "wp-splashconnect.zip" gebruikt; anders de automatische zipball van GitHub.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STA_GitHub_Updater {

    const REPO      = 'Jasperwolsing/WP-Splashconnect';
    const SLUG      = 'wp-splashconnect';
    const ASSET     = 'wp-splashconnect.zip';
    const CACHE_KEY = 'sta_github_release';
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    private static string $plugin_file;   // plugin_basename, bv. wp-splashconnect/wp-splashconnect.php

    public static function init( string $main_file ): void {
        self::$plugin_file = plugin_basename( $main_file );

        add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_update' ] );
        add_filter( 'plugins_api',                           [ __CLASS__, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection',             [ __CLASS__, 'fix_folder_name' ], 10, 4 );
        add_action( 'upgrader_process_complete',            [ __CLASS__, 'clear_cache' ], 10, 0 );
    }

    /** Laatste release ophalen (gecachet). Geeft null bij fouten of geen release. */
    private static function latest_release(): ?array {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) {
            return $cached ?: null;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::REPO . '/releases/latest',
            [
                'timeout' => 10,
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WP-Splashconnect-Updater',
                ],
            ]
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // Kort cachen om GitHub niet te hameren bij storingen of rate limits.
            set_transient( self::CACHE_KEY, [], 30 * MINUTE_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['tag_name'] ) ) {
            set_transient( self::CACHE_KEY, [], 30 * MINUTE_IN_SECONDS );
            return null;
        }

        $package = '';
        foreach ( (array) ( $data['assets'] ?? [] ) as $asset ) {
            if ( ( $asset['name'] ?? '' ) === self::ASSET && ! empty( $asset['browser_download_url'] ) ) {
                $package = $asset['browser_download_url'];
                break;
            }
        }
        if ( ! $package ) {
            $package = $data['zipball_url'] ?? '';
        }

        $release = [
            'version'   => ltrim( (string) $data['tag_name'], 'vV' ),
            'package'   => $package,
            'url'       => $data['html_url'] ?? 'https://github.com/' . self::REPO,
            'changelog' => (string) ( $data['body'] ?? '' ),
            'published' => $data['published_at'] ?? '',
        ];

        // Alleen https-pakketten van github.com accepteren.
        $host = wp_parse_url( $release['package'], PHP_URL_HOST );
        $ok   = $host && ( $host === 'github.com' || substr( $host, -11 ) === '.github.com' || $host === 'api.github.com' || substr( $host, -22 ) === '.githubusercontent.com' );
        if ( ! $ok || 0 !== strpos( $release['package'], 'https://' ) ) {
            set_transient( self::CACHE_KEY, [], 30 * MINUTE_IN_SECONDS );
            return null;
        }

        set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
        return $release;
    }

    public static function check_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = self::latest_release();
        if ( ! $release || ! version_compare( $release['version'], STA_VERSION, '>' ) ) {
            return $transient;
        }

        $transient->response[ self::$plugin_file ] = (object) [
            'id'          => 'github.com/' . self::REPO,
            'slug'        => self::SLUG,
            'plugin'      => self::$plugin_file,
            'new_version' => $release['version'],
            'url'         => $release['url'],
            'package'     => $release['package'],
        ];

        return $transient;
    }

    /** Info in het "Details bekijken"-venster. */
    public static function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
            return $result;
        }

        $release = self::latest_release();
        if ( ! $release ) {
            return $result;
        }

        return (object) [
            'name'          => 'WP Splashconnect',
            'slug'          => self::SLUG,
            'version'       => $release['version'],
            'author'        => '<a href="https://github.com/Jasperwolsing">Jasper Wolsing</a>',
            'homepage'      => 'https://github.com/' . self::REPO,
            'download_link' => $release['package'],
            'last_updated'  => $release['published'],
            'sections'      => [
                'description' => 'Koppelt zwemwedstrijden aan WordPress.',
                'changelog'   => nl2br( esc_html( $release['changelog'] ) ),
            ],
        ];
    }

    /**
     * De automatische GitHub-zipball heeft een mapnaam als "Jasperwolsing-WP-Splashconnect-abc123".
     * Hernoem die naar "wp-splashconnect" zodat de plugin op dezelfde plek blijft staan.
     */
    public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['plugin'] ) || self::$plugin_file !== $hook_extra['plugin'] ) {
            return $source;
        }

        global $wp_filesystem;
        $target = trailingslashit( $remote_source ) . self::SLUG . '/';

        if ( untrailingslashit( $source ) === untrailingslashit( $target ) ) {
            return $source;
        }
        if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $target ), true ) ) {
            return $target;
        }

        return new WP_Error( 'sta_update_rename', 'WP Splashconnect: kon de map van de update niet hernoemen.' );
    }

    public static function clear_cache(): void {
        delete_transient( self::CACHE_KEY );
    }
}
