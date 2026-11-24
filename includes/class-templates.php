<?php
/**
 * Frontend-sjablonen die met de plugin meekomen (map /templates).
 *
 * WordPress laadt sjablonen normaal alleen uit het thema. Deze klasse laadt ze
 * uit de plugin, zodat elke site ze zonder thema-aanpassing krijgt. Heeft het
 * (child)thema een bestand met dezelfde naam, dan wint het thema; zo blijven
 * aanpassingen per site mogelijk.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STA_Templates {

    /** Paginasjabloon "Club overzicht" (zelfde sleutel als in het oude thema). */
    const PAGE_CLUB = 'page-club-overzicht.php';

    public static function init(): void {
        add_filter( 'template_include',     [ __CLASS__, 'template_include' ], 20 );
        add_filter( 'theme_page_templates', [ __CLASS__, 'page_templates' ] );
    }

    public static function page_templates( array $templates ): array {
        $templates[ self::PAGE_CLUB ] = 'Club overzicht';
        return $templates;
    }

    public static function template_include( string $template ): string {
        $file = '';

        if ( is_tax( 'afstand' ) ) {
            $file = 'taxonomy-afstand.php';
        } elseif ( is_tax( 'sta_club' ) ) {
            $file = 'taxonomy-sta_club.php';
        } elseif ( is_singular( 'athlete' ) ) {
            $file = 'single-athlete.php';
        } elseif ( is_post_type_archive( 'athlete' ) ) {
            $file = 'archive-athlete.php';
        } elseif ( is_page() && get_page_template_slug() === self::PAGE_CLUB ) {
            $file = self::PAGE_CLUB;
        }

        if ( ! $file ) {
            return $template;
        }

        // Een eigen versie in het (child)thema heeft voorrang.
        $theme_file = locate_template( [ $file ] );
        if ( $theme_file ) {
            return $theme_file;
        }

        $plugin_file = STA_DIR . 'templates/' . $file;
        return is_readable( $plugin_file ) ? $plugin_file : $template;
    }
}
