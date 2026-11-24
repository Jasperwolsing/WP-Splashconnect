<?php
/**
 * Plugin Name: WP Splashconnect
 * Plugin URI:  https://github.com/Jasperwolsing/WP-Splashconnect
 * Description: Koppelt zwemwedstrijden aan WordPress: importeer atleten uit een PDF-inschrijflijst, vind Flickr-foto's, genereer AI-bio's en toon programma, uitslagen en sharecards.
 * Version:     1.3.1
 * Author:      Jasper Wolsing
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-splashconnect
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STA_VERSION', '1.3.1' );
define( 'STA_DIR', plugin_dir_path( __FILE__ ) );
define( 'STA_URL', plugin_dir_url( __FILE__ ) );

// Updates via GitHub Releases (alleen relevant in de admin en tijdens cron).
require_once STA_DIR . 'includes/class-github-updater.php';
STA_GitHub_Updater::init( __FILE__ );

require_once STA_DIR . 'includes/class-modules.php';
require_once STA_DIR . 'includes/class-templates.php';
STA_Templates::init();
require_once STA_DIR . 'includes/class-cpt-athlete.php';
require_once STA_DIR . 'includes/class-featured-ticker.php';
require_once STA_DIR . 'includes/class-pdf-importer.php';
require_once STA_DIR . 'includes/class-csv-importer.php';
require_once STA_DIR . 'includes/class-flickr-client.php';
require_once STA_DIR . 'includes/class-bio-generator.php';
require_once STA_DIR . 'includes/class-import-job.php';
require_once STA_DIR . 'includes/class-program.php';
require_once STA_DIR . 'includes/class-speaker-role.php';
require_once STA_DIR . 'includes/class-program-shortcode.php';
require_once STA_DIR . 'admin/admin-menu.php';
require_once STA_DIR . 'admin/admin-livestream.php';
require_once STA_DIR . 'admin/admin-program.php';
require_once STA_DIR . 'admin/admin-speakers.php';
require_once STA_DIR . 'admin/admin-sharecard.php';
if ( is_admin() ) {
    require_once STA_DIR . 'admin/admin-heroshot.php';
    require_once STA_DIR . 'admin/admin-namerecording.php';
    require_once STA_DIR . 'admin/admin-layout.php';
    require_once STA_DIR . 'admin/admin-fotointegratie.php';
    require_once STA_DIR . 'admin/admin-swimrankings.php';
}

register_activation_hook( __FILE__, 'sta_activate' );
function sta_activate() {
    STA_CPT_Athlete::register();
    STA_Speaker_Role::ensure_role();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'sta_deactivate' );
function sta_deactivate() {
    STA_Speaker_Role::remove_role();
}

add_action( 'init', [ 'STA_CPT_Athlete', 'register' ] );
add_action( 'init', [ 'STA_Featured_Ticker', 'register' ] );
add_action( 'init', [ 'STA_Program_Shortcode', 'register' ] );
add_action( 'admin_menu', [ 'STA_Admin_Menu', 'register' ] );
add_action( 'plugins_loaded', [ 'STA_Admin_Program',  'register' ] );

// Optionele modules: alleen registreren (menu, AJAX, shortcodes, cron) als ze aan staan.
STA_Modules::init();
if ( STA_Modules::is_active( 'speakers' ) ) {
    add_action( 'plugins_loaded', [ 'STA_Admin_Speakers', 'register' ] );
    add_action( 'plugins_loaded', [ 'STA_Speaker_Role',   'register' ] );
}
if ( STA_Modules::is_active( 'sharecard' ) ) {
    STA_Admin_Sharecard::register();
}
if ( STA_Modules::is_active( 'livestream' ) ) {
    STA_Admin_Livestream::register();
}
if ( is_admin() ) {
    if ( STA_Modules::is_active( 'heroshot' ) ) {
        STA_Admin_Heroshot::register();
    }
    if ( STA_Modules::is_active( 'namerecording' ) ) {
        STA_Admin_NameRecording::register();
    }
    if ( STA_Modules::is_active( 'photos' ) ) {
        STA_Admin_FotoIntegratie::register();
    }
    STA_Admin_Layout::register();
    STA_Admin_SwimRankings::register();
}
add_action( 'admin_enqueue_scripts', 'sta_admin_assets' );

function sta_admin_assets( $hook ) {
    if ( strpos( $hook, 'swimming-trials' ) === false ) {
        return;
    }
    wp_enqueue_style( 'sta-admin', STA_URL . 'assets/css/admin.css', [], STA_VERSION );
    wp_enqueue_script( 'sta-admin', STA_URL . 'assets/js/admin.js', [ 'jquery' ], STA_VERSION, true );
    wp_localize_script( 'sta-admin', 'STA', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'sta_nonce' ),
    ] );
}

// Verhoog timeouts voor zware API-aanroepen
add_filter( 'http_request_timeout', function( $timeout, $url ) {
    if ( strpos( $url, 'anthropic.com' ) !== false ) {
        return 300;
    }
    return $timeout;
}, 10, 2 );

// Eenmalige migratie: sta_club postmeta → sta_club taxonomy terms
add_action( 'admin_init', function() {
    if ( get_option( 'sta_club_tax_migrated' ) ) return;
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta}
         WHERE meta_key = 'sta_club' AND meta_value != ''",
        ARRAY_A
    );
    foreach ( $rows as $row ) {
        if ( get_post_type( $row['post_id'] ) === 'athlete' ) {
            wp_set_object_terms( (int) $row['post_id'], $row['meta_value'], 'sta_club', false );
        }
    }
    update_option( 'sta_club_tax_migrated', '1' );
} );

// AJAX: publiceer atleet
add_action( 'wp_ajax_sta_publish_athlete', 'sta_ajax_publish_athlete' );
function sta_ajax_publish_athlete() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Geen toegang.' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || get_post_type( $post_id ) !== 'athlete' ) {
        wp_send_json_error( 'Ongeldig post ID.' );
    }

    $result = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ], true );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success();
}

// AJAX: uitgelicht-toggle
add_action( 'wp_ajax_sta_toggle_featured', 'sta_ajax_toggle_featured' );
function sta_ajax_toggle_featured() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Geen toegang.' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $value   = ! empty( $_POST['featured'] ) ? '1' : '';

    if ( ! $post_id ) {
        wp_send_json_error( 'Ongeldig post ID.' );
    }

    update_post_meta( $post_id, 'sta_featured', $value );
    wp_send_json_success( [ 'featured' => (bool) $value ] );
}

// AJAX: start import — slaat PDF op en geeft meteen een job_id terug
add_action( 'wp_ajax_sta_import_pdf', 'sta_ajax_import_pdf' );
function sta_ajax_import_pdf() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Geen toegang.' );
    }

    $file_key = ! empty( $_FILES['csv_file'] ) ? 'csv_file' : ( ! empty( $_FILES['pdf_file'] ) ? 'pdf_file' : '' );
    if ( ! $file_key || $_FILES[ $file_key ]['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( 'Geen bestand ontvangen.' );
    }

    $original_name = $_FILES[ $file_key ]['name'] ?? '';
    $ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, [ 'csv', 'pdf' ], true ) ) {
        wp_send_json_error( 'Alleen CSV- of PDF-bestanden zijn toegestaan.' );
    }

    // Sla bestand tijdelijk op in de uploads-map
    $upload  = wp_upload_dir();
    $tmp_dir = trailingslashit( $upload['basedir'] ) . 'sta-import-tmp/';
    wp_mkdir_p( $tmp_dir );

    $filename = 'import-' . uniqid() . '.' . $ext;
    $dest     = $tmp_dir . $filename;

    if ( ! move_uploaded_file( $_FILES[ $file_key ]['tmp_name'], $dest ) ) {
        wp_send_json_error( 'Kon het bestand niet opslaan.' );
    }

    // Maak job aan en sla bestandspad op
    $job = STA_Import_Job::create();
    $job->update( [ 'file' => $dest ] );

    // Stuur een niet-blokkerende loopback-request om de verwerking te starten
    wp_remote_post( admin_url( 'admin-ajax.php' ), [
        'blocking' => false,
        'timeout'  => 1,
        'body'     => [
            'action'  => 'sta_process_import',
            'job_id'  => $job->get_id(),
            'token'   => wp_hash( $job->get_id() ),
        ],
    ] );

    wp_send_json_success( [ 'job_id' => $job->get_id() ] );
}

// AJAX: achtergrond-processor — draait los van de browser, geen timeout-probleem
add_action( 'wp_ajax_sta_process_import',      'sta_process_import' );
add_action( 'wp_ajax_nopriv_sta_process_import', 'sta_process_import' );
function sta_process_import() {
    $job_id = sanitize_text_field( $_POST['job_id'] ?? '' );
    $token  = sanitize_text_field( $_POST['token']  ?? '' );

    // Valideer token (geen nonce nodig want dit is een server-naar-server call)
    if ( ! $job_id || ! hash_equals( wp_hash( $job_id ), $token ) ) {
        exit( 'Unauthorized' );
    }

    $job = new STA_Import_Job( $job_id );
    $data = $job->get();

    if ( ! $data || $data['status'] !== 'pending' ) {
        exit;
    }

    @set_time_limit( 600 );
    @ini_set( 'memory_limit', '256M' );

    // Vertel de browser dat er al een antwoord is (sluit de HTTP-verbinding)
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    }

    $job->set_processing( 'PDF wordt naar Claude gestuurd...' );

    try {
        $file = $data['file'] ?? '';
        if ( ! $file || ! file_exists( $file ) ) {
            $job->set_error( 'PDF-bestand niet gevonden op server.' );
            exit;
        }

        $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

        if ( $ext === 'csv' ) {
            $importer = new STA_CSV_Importer();
        } else {
            $importer = new STA_PDF_Importer();
        }
        $importer->set_job( $job );

        $result = $importer->import_from_path( $file );

        // Ruim tijdelijk bestand op
        @unlink( $file );

        if ( is_wp_error( $result ) ) {
            $job->set_error( $result->get_error_message() );
        } else {
            $job->set_complete( $result );
        }
    } catch ( Throwable $e ) {
        @unlink( $data['file'] ?? '' );
        $job->set_error( 'PHP-fout: ' . $e->getMessage() . ' (regel ' . $e->getLine() . ')' );
    }

    exit;
}

// AJAX: poll job-status
add_action( 'wp_ajax_sta_import_status', 'sta_ajax_import_status' );
function sta_ajax_import_status() {
    check_ajax_referer( 'sta_nonce', 'nonce' );

    $job_id = sanitize_text_field( $_POST['job_id'] ?? '' );
    if ( ! $job_id ) {
        wp_send_json_error( 'Geen job_id.' );
    }

    $job  = new STA_Import_Job( $job_id );
    $data = $job->get();

    if ( ! $data ) {
        wp_send_json_error( 'Job niet gevonden.' );
    }

    wp_send_json_success( [
        'status'   => $data['status'],
        'progress' => $data['progress'] ?? 0,
        'message'  => $data['message']  ?? '',
        'result'   => $data['result']   ?? null,
        'error'    => $data['error']    ?? null,
    ] );
}

// AJAX: verrijk één atleet (Flickr + bio)
add_action( 'wp_ajax_sta_enrich_athlete', 'sta_ajax_enrich_athlete' );
function sta_ajax_enrich_athlete() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Geen toegang.' );
    }

    $post_id    = intval( $_POST['post_id'] ?? 0 );
    $skip_photo = ! empty( $_POST['skip_photo'] ) || ! STA_Modules::is_active( 'photos' );
    $skip_bio   = ! empty( $_POST['skip_bio'] );
    if ( ! $post_id ) {
        wp_send_json_error( 'Ongeldig post ID.' );
    }

    $name = get_the_title( $post_id );

    $response = [
        'photo_found'   => false,
        'photo_saved'   => false,
        'photo_error'   => null,
        'bio_saved'     => false,
        'bio_error'     => null,
    ];

    // Flickr foto
    if ( $skip_photo ) {
        $response['photo_error'] = null; // overgeslagen, geen fout
    } else {
    $gender  = get_post_meta( $post_id, 'sta_gender', true );
    $fb_key  = $gender === 'f' ? 'sta_fallback_photo_f' : 'sta_fallback_photo_m';
    $fb_id   = intval( get_option( $fb_key, 0 ) );
    $fb_url  = $fb_id ? wp_get_attachment_image_url( $fb_id, 'large' ) : '';
    $fb_thumb = $fb_id ? wp_get_attachment_image_url( $fb_id, 'thumbnail' ) : '';

    $flickr = new STA_Flickr_Client();
    $photo  = $flickr->find_photo_for_athlete( $name );

    if ( is_wp_error( $photo ) ) {
        $response['photo_error'] = $photo->get_error_message();
    } elseif ( $photo ) {
        $response['photo_found'] = true;
        $response['photo_debug'] = $photo['debug'] ?? [];

        update_post_meta( $post_id, 'sta_flickr_photo_url',  $photo['url'] );
        update_post_meta( $post_id, 'sta_flickr_photo_id',   $photo['id'] );
        update_post_meta( $post_id, 'sta_flickr_photo_page', $photo['page'] );
        update_post_meta( $post_id, 'sta_flickr_candidates', $photo['candidates'] ?? [] );

        $attachment_id = sta_sideload_flickr_photo( $photo['url'], $post_id, $name );

        if ( is_wp_error( $attachment_id ) ) {
            $response['photo_error'] = 'Foto gevonden maar sideload mislukt: ' . $attachment_id->get_error_message();
        } else {
            $result = set_post_thumbnail( $post_id, $attachment_id );
            if ( $result ) {
                delete_post_meta( $post_id, 'sta_photo_is_fallback' ); // echte foto — vlag wissen
                $candidates = array_map( fn($c) => [
                    'thumb' => $c['thumb'],
                    'url'   => $c['url'],
                    'page'  => $c['page'],
                    'title' => $c['title'],
                ], $photo['candidates'] ?? [] );

                if ( $fb_url ) {
                    $candidates[] = [
                        'thumb' => $fb_thumb,
                        'url'   => $fb_url,
                        'page'  => '',
                        'title' => 'Fallback foto',
                    ];
                }

                $response['photo_saved']      = true;
                $response['photo_thumb']      = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
                $response['photo_candidates'] = $candidates;
            } else {
                $response['photo_error'] = 'set_post_thumbnail mislukt (attachment_id=' . $attachment_id . ')';
            }
        }
    } else {
        $response['photo_debug'] = [];
        if ( $fb_id ) {
            set_post_thumbnail( $post_id, $fb_id );
            update_post_meta( $post_id, 'sta_photo_is_fallback', '1' ); // markeer als fallback
            $response['photo_saved']  = true;
            $response['photo_thumb']  = $fb_thumb;
            $response['photo_candidates'] = [];
        } else {
            $response['photo_error'] = 'Geen foto gevonden op Flickr voor: ' . $name;
        }
    }
    } // end if ( ! $skip_photo )

    // Bio
    if ( ! $skip_bio ) {
        $bio_gen = new STA_Bio_Generator();
        $bio     = $bio_gen->generate( $name, $post_id );

        if ( is_wp_error( $bio ) ) {
            $response['bio_error'] = $bio->get_error_message();
        } elseif ( $bio ) {
            update_post_meta( $post_id, 'sta_bio', $bio );
            wp_update_post( [ 'ID' => $post_id, 'post_content' => $bio ] );
            $response['bio_saved'] = true;
        }
    }

    wp_send_json_success( $response );
}

// AJAX: toon ruwe Serper-context voor een atleet
add_action( 'wp_ajax_sta_get_context', 'sta_ajax_get_context' );
function sta_ajax_get_context() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'Ongeldig post ID.' );

    $name    = get_the_title( $post_id );
    $gen     = new STA_Bio_Generator();
    $context = $gen->get_context( $name );
    $debug   = $gen->get_last_debug();

    wp_send_json_success( [ 'name' => $name, 'context' => $context, 'debug' => $debug ] );
}

// AJAX: zoek Flickr-kandidaatfoto's voor een atleet (slaat niets op)
add_action( 'wp_ajax_sta_find_photos', 'sta_ajax_find_photos' );
function sta_ajax_find_photos() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );
    if ( ! STA_Modules::is_active( 'photos' ) ) wp_send_json_error( 'De module Foto-integratie staat uit.' );

    $post_id  = intval( $_POST['post_id'] ?? 0 );
    $max      = min( 50, max( 1, intval( $_POST['max'] ?? 5 ) ) );
    $page     = max( 1, intval( $_POST['page'] ?? 1 ) );
    $alt_name = sanitize_text_field( $_POST['alt_name'] ?? '' );
    if ( ! $post_id ) wp_send_json_error( 'Ongeldig post ID.' );

    $name   = $alt_name ?: get_the_title( $post_id );
    $flickr = new STA_Flickr_Client();
    $result = $flickr->find_photos_for_athlete( $name, $max, $page );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $candidates = array_slice( array_map( fn( $p ) => [
        'thumb' => $p['thumb'],
        'url'   => $p['url'],
        'page'  => $p['page'] ?? '',
        'title' => $p['title'] ?? '',
    ], $result['photos'] ?? [] ), 0, $max );

    wp_send_json_success( [ 'candidates' => $candidates ] );
}

// AJAX: wissel de featured foto naar een andere kandidaat
add_action( 'wp_ajax_sta_switch_photo', 'sta_ajax_switch_photo' );
function sta_ajax_switch_photo() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

    $post_id   = intval( $_POST['post_id'] ?? 0 );
    $photo_url = esc_url_raw( $_POST['photo_url'] ?? '' );

    if ( ! $post_id || ! $photo_url ) wp_send_json_error( 'Ongeldige parameters.' );

    $name          = get_the_title( $post_id );
    $attachment_id = sta_sideload_flickr_photo( $photo_url, $post_id, $name );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( $attachment_id->get_error_message() );
    }

    $is_fallback = ! empty( $_POST['is_fallback'] );
    set_post_thumbnail( $post_id, $attachment_id );
    update_post_meta( $post_id, 'sta_flickr_photo_url', $photo_url );
    if ( $is_fallback ) {
        update_post_meta( $post_id, 'sta_photo_is_fallback', '1' );
    } else {
        delete_post_meta( $post_id, 'sta_photo_is_fallback' );
    }

    wp_send_json_success( [
        'thumb'   => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
        'focus_x' => (int) ( get_post_meta( $post_id, 'sta_focus_x', true ) ?: 50 ),
        'focus_y' => (int) ( get_post_meta( $post_id, 'sta_focus_y', true ) ?: 50 ),
    ] );
}

// AJAX: sla bio en publieke speaker notes op
add_action( 'wp_ajax_sta_save_bio', 'sta_ajax_save_bio' );
function sta_ajax_save_bio() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'Ongeldige post ID.' );

    $bio   = sanitize_textarea_field( wp_unslash( $_POST['bio']   ?? '' ) );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

    update_post_meta( $post_id, 'sta_bio', $bio );
    wp_update_post( [ 'ID' => $post_id, 'post_content' => $bio ] );
    update_post_meta( $post_id, 'sta_notes_public', $notes );

    wp_send_json_success();
}

// AJAX: genereer alleen een bio (zonder foto)
add_action( 'wp_ajax_sta_generate_bio', 'sta_ajax_generate_bio' );
function sta_ajax_generate_bio() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'Ongeldige post ID.' );

    $name  = get_the_title( $post_id );
    $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : null;

    // Tijdelijk de notes overschrijven als ze meegegeven zijn
    if ( $notes !== null ) {
        update_post_meta( $post_id, 'sta_notes_public', $notes );
    }

    $generator = new STA_Bio_Generator();
    $bio       = $generator->generate( $name, $post_id );

    if ( is_wp_error( $bio ) ) {
        wp_send_json_error( $bio->get_error_message() );
    }

    update_post_meta( $post_id, 'sta_bio', $bio );
    wp_update_post( [ 'ID' => $post_id, 'post_content' => $bio ] );

    wp_send_json_success( [ 'bio' => $bio ] );
}

// AJAX: test Serper-verbinding
add_action( 'wp_ajax_sta_serper_test', 'sta_ajax_serper_test' );
function sta_ajax_serper_test() {
    check_ajax_referer( 'sta_serper_test', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

    $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
    $query   = sanitize_text_field( $_POST['query']   ?? 'Caeleb Dressel swimmer' );

    if ( empty( $api_key ) ) wp_send_json_error( 'Vul eerst een API-sleutel in.' );

    $response = wp_remote_post( 'https://google.serper.dev/search', [
        'timeout' => 10,
        'headers' => [
            'X-API-KEY'    => $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode( [ 'q' => $query, 'gl' => 'nl', 'hl' => 'nl', 'num' => 3 ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'HTTP-fout: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 ) {
        wp_send_json_error( "HTTP {$code}: " . ( $body['message'] ?? wp_remote_retrieve_body( $response ) ) );
    }

    $count = count( $body['organic'] ?? [] );
    $first = $body['organic'][0]['snippet'] ?? '(geen snippet)';
    wp_send_json_success( "{$count} resultaten. Eerste snippet: \"{$first}\"" );
}

add_action( 'wp_ajax_sta_flickr_test', 'sta_ajax_flickr_test' );
function sta_ajax_flickr_test() {
    check_ajax_referer( 'sta_flickr_test', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

    $api_key  = sanitize_text_field( $_POST['api_key']  ?? '' );
    $username = sanitize_text_field( $_POST['username'] ?? '' );

    if ( empty( $api_key ) )  wp_send_json_error( 'Vul eerst een API-sleutel in.' );
    if ( empty( $username ) ) wp_send_json_error( 'Vul eerst een gebruikersnaam in.' );

    $profile_url = 'https://www.flickr.com/photos/' . $username . '/';
    $response    = wp_remote_get( add_query_arg( [
        'method'         => 'flickr.urls.lookupUser',
        'api_key'        => $api_key,
        'url'            => $profile_url,
        'format'         => 'json',
        'nojsoncallback' => 1,
    ], 'https://www.flickr.com/services/rest/' ), [ 'timeout' => 10 ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'HTTP-fout: ' . $response->get_error_message() );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ( $body['stat'] ?? '' ) !== 'ok' ) {
        $msg = $body['message'] ?? 'onbekende fout';
        wp_send_json_error( "Flickr-fout: {$msg}" );
    }

    $nsid      = $body['user']['id'] ?? '';
    $real_name = $body['user']['username']['_content'] ?? $username;
    wp_send_json_success( "Verbonden met '{$real_name}' (NSID: {$nsid})" );
}

// AJAX: sla focuspunt op
add_action( 'wp_ajax_sta_save_focus', 'sta_ajax_save_focus' );
function sta_ajax_save_focus() {
    check_ajax_referer( 'sta_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );
    $post_id = intval( $_POST['post_id'] ?? 0 );
    $x       = max( 0, min( 100, intval( $_POST['x'] ?? 50 ) ) );
    $y       = max( 0, min( 100, intval( $_POST['y'] ?? 50 ) ) );
    if ( ! $post_id ) wp_send_json_error( 'Ongeldige post ID.' );
    update_post_meta( $post_id, 'sta_focus_x', $x );
    update_post_meta( $post_id, 'sta_focus_y', $y );
    wp_send_json_success();
}

/** Geeft object-position CSS terug op basis van het ingestelde focuspunt */
function sta_focus_style( int $post_id ): string {
    $x = get_post_meta( $post_id, 'sta_focus_x', true ) ?: '50';
    $y = get_post_meta( $post_id, 'sta_focus_y', true ) ?: '50';
    return 'object-position:' . intval($x) . '% ' . intval($y) . '%;';
}

function sta_sideload_flickr_photo( $url, $post_id, $name ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Volg redirects handmatig zodat we de echte Content-Type weten
    $head = wp_remote_head( $url, [ 'redirection' => 5, 'timeout' => 10 ] );
    if ( ! is_wp_error( $head ) ) {
        $final_url = wp_remote_retrieve_header( $head, 'x-final-url' )
                     ?: ( $head['http_response'] ?? null )?->get_response_object()?->url
                     ?: $url;
        $url = $final_url ?: $url;
    }

    $tmp = download_url( $url, 30 );
    if ( is_wp_error( $tmp ) ) {
        return $tmp;
    }

    // Bepaal mime-type via WP (gebruikt finfo of getimagesize als fallback)
    $filetype = wp_check_filetype_and_ext( $tmp, basename( $url ) );
    $mime     = $filetype['type'] ?: 'image/jpeg';
    $ext      = $filetype['ext']  ?: 'jpg';

    // Als WP het niet herkent op basis van URL, probeer via getimagesize
    if ( ! $filetype['type'] ) {
        $info = @getimagesize( $tmp );
        if ( $info ) {
            $mime = $info['mime'] ?? 'image/jpeg';
            $ext  = match( $mime ) {
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                default      => 'jpg',
            };
        }
    }

    $file_array = [
        'name'     => sanitize_title( $name ) . '.' . $ext,
        'tmp_name' => $tmp,
        'type'     => $mime,
    ];

    $id = media_handle_sideload( $file_array, $post_id, $name );

    if ( file_exists( $tmp ) ) {
        @unlink( $tmp );
    }

    return $id;
}
