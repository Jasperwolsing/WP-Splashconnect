<?php
/**
 * Locatie van de Heroshot-bestanden (data/ en videos/).
 *
 * De map is instelbaar (Instellingen → Heroshot) als pad ten opzichte van de
 * hoofdmap van de site (de map met wp-config.php). Standaard "DQR2026".
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STA_Heroshot_Paths {

    const OPTION  = 'sta_heroshot_dir';
    const DEFAULT = 'DQR2026';

    /** Maakt van gebruikersinvoer een veilig relatief pad ("" bij ongeldige invoer). */
    public static function sanitize( $value ): string {
        $value = str_replace( '\\', '/', trim( (string) $value ) );
        $value = trim( $value, '/ ' );
        if ( $value === '' ) {
            return self::DEFAULT;
        }
        $parts = explode( '/', $value );
        foreach ( $parts as $part ) {
            // Geen lege delen, punten of ".."; alleen letters, cijfers, - en _
            if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $part ) ) {
                return (string) get_option( self::OPTION, self::DEFAULT );
            }
        }
        return implode( '/', $parts );
    }

    /** Relatief pad, bv. "DQR2026" of "media/heroshot". */
    public static function relative(): string {
        $value = (string) get_option( self::OPTION, self::DEFAULT );
        $clean = self::sanitize( $value );
        return $clean !== '' ? $clean : self::DEFAULT;
    }

    /** Absoluut pad op de server, met slash op het eind. */
    public static function dir( string $sub = '' ): string {
        $path = trailingslashit( ABSPATH ) . self::relative() . '/';
        return $sub !== '' ? $path . trim( $sub, '/' ) . '/' : $path;
    }

    /** Publieke URL, met slash op het eind. */
    public static function url( string $sub = '' ): string {
        $url = home_url( '/' . self::relative() . '/' );
        return $sub !== '' ? $url . trim( $sub, '/' ) . '/' : $url;
    }
}
