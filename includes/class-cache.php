<?php
/**
 * Leegt bekende paginacaches zodra instellingen veranderen die de weergave op de site bepalen
 * (Pagina-opmaak, modules, programma). Zonder dit blijven bezoekers de oude HTML zien.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STA_Cache {

    /** Opties die de publieke weergave beïnvloeden. */
    const OPTIONS = [
        'sta_layout',
        'sta_modules',
        'sta_program_times',
        'sta_program_session_times',
        'sta_program_session_names',
        'sta_program_para',
        'sta_sharecard',
    ];

    public static function init(): void {
        foreach ( self::OPTIONS as $opt ) {
            add_action( 'update_option_' . $opt, [ __CLASS__, 'purge' ] );
            add_action( 'add_option_' . $opt,    [ __CLASS__, 'purge' ] );
        }
    }

    public static function purge(): void {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        // LiteSpeed Cache
        do_action( 'litespeed_purge_all' );
        // WP Rocket
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }
        // W3 Total Cache
        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
        }
        // WP Super Cache
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }
        // WP Fastest Cache
        if ( function_exists( 'wpfc_clear_all_cache' ) ) {
            wpfc_clear_all_cache( true );
        }
        // Algemeen (o.a. Cache Enabler, Breeze)
        do_action( 'sta_cache_purged' );
    }
}
