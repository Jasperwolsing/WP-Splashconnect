<?php

class STA_Heroshot_Importer {

    public function import_from_file( string $path ): array|WP_Error {
        if ( ! file_exists( $path ) ) {
            return new WP_Error( 'file_not_found', 'Bestand niet gevonden.' );
        }

        $handle = fopen( $path, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_error', 'Kon bestand niet openen.' );
        }

        // Strip BOM, read header
        $raw_header = fgets( $handle );
        $raw_header = ltrim( $raw_header, "\xEF\xBB\xBF" );
        $headers    = str_getcsv( trim( $raw_header ), ',' );
        $col        = array_flip( array_map( 'trim', $headers ) );

        if ( ! isset( $col['Full Name'], $col['URL'] ) ) {
            fclose( $handle );
            return new WP_Error( 'invalid_format', 'CSV mist verplichte kolommen "Full Name" of "URL".' );
        }

        $data_dir = STA_Heroshot_Paths::dir( 'data' );
        if ( ! is_dir( $data_dir ) ) {
            wp_mkdir_p( $data_dir );
        }

        $linked   = [];
        $skipped  = [];
        $notfound = [];

        while ( ( $line = fgetcsv( $handle, 0, ',' ) ) !== false ) {
            $name = trim( $line[ $col['Full Name'] ] ?? '' );
            $url  = trim( $line[ $col['URL'] ]       ?? '' );

            if ( ! $name || ! $url ) {
                continue;
            }

            $parsed = parse_url( $url );
            parse_str( $parsed['query'] ?? '', $params );
            $heroshot_id   = $params['id'] ?? '';
            $heroshot_hash = $params['h']  ?? '';

            if ( ! $heroshot_id || ! $heroshot_hash ) {
                $skipped[] = $name;
                continue;
            }

            $posts = get_posts( [
                'post_type'   => 'athlete',
                'post_status' => 'any',
                'title'       => $name,
                'numberposts' => 1,
            ] );

            if ( empty( $posts ) ) {
                $notfound[] = $name;
                continue;
            }

            $post_id  = $posts[0]->ID;
            $wp_url   = get_permalink( $post_id );

            update_post_meta( $post_id, 'sta_heroshot_id',   sanitize_text_field( $heroshot_id ) );
            update_post_meta( $post_id, 'sta_heroshot_hash', sanitize_text_field( $heroshot_hash ) );

            // Write data file for EST2026/index.php
            file_put_contents(
                $data_dir . $heroshot_id . '.json',
                wp_json_encode( [ 'name' => $name, 'wp_url' => $wp_url ] )
            );

            $linked[] = $name;
        }

        fclose( $handle );

        return compact( 'linked', 'skipped', 'notfound' );
    }
}
