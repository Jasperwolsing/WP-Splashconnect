<?php
/**
 * Module-register: bepaalt welke onderdelen van WP Splashconnect actief zijn.
 *
 * Kernonderdelen staan altijd aan. Optionele modules zijn via de hoofdpagina
 * (WP Splashconnect → Overzicht) aan en uit te zetten; een uitgeschakelde module
 * verdwijnt uit het menu en registreert geen eigen hooks, AJAX-acties of shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STA_Modules {

    const OPTION = 'sta_modules';

    /** Optionele modules. Standaard staan ze allemaal aan (zodat bestaande sites ongewijzigd blijven). */
    public static function optional(): array {
        return [
            'photos'        => [
                'label' => 'Foto-integratie',
                'desc'  => 'Zoekt toernooifoto\'s op Flickr, koppelt ze aan atleten en plant een automatische scan. Zonder deze module verdwijnen ook de foto-opties bij "Verrijk atleten" en de Flickr-instellingen.',
                'page'  => 'swimming-trials-fotointegratie',
            ],
            'heroshot'      => [
                'label' => 'Heroshot video',
                'desc'  => 'Koppelt Heroshot-video\'s aan atleten.',
                'page'  => 'swimming-trials-heroshot',
            ],
            'sharecard'     => [
                'label' => 'Share card',
                'desc'  => 'Template-editor en generator voor deelbare atleetkaarten.',
                'page'  => 'swimming-trials-sharecard',
            ],
            'livestream'    => [
                'label' => 'Livestream',
                'desc'  => 'Livestream-notificaties en de shortcode [livestream_melding].',
                'page'  => 'sta-livestream',
            ],
            'namerecording' => [
                'label' => 'Naam opnamen',
                'desc'  => 'Spraakopnamen van atletennamen.',
                'page'  => 'sta-name-recording',
            ],
            'speakers'      => [
                'label' => 'Speakers',
                'desc'  => 'Speaker-rol en overzicht met notities en bio\'s voor de zaalspeaker.',
                'page'  => 'swimming-trials-speakers',
            ],
        ];
    }

    /** Onderdelen die altijd aan staan (alleen voor weergave op de hoofdpagina). */
    public static function core(): array {
        return [
            [ 'label' => 'SwimRankings import', 'page' => 'sta-swimrankings' ],
            [ 'label' => 'Programma',           'page' => 'swimming-trials-program' ],
            [ 'label' => 'Atleten',             'url'  => 'edit.php?post_type=athlete' ],
            [ 'label' => 'Verrijk atleten',     'page' => 'swimming-trials-enrich' ],
            [ 'label' => 'Pagina-opmaak',       'page' => 'sta-layout' ],
            [ 'label' => 'Instellingen',        'page' => 'swimming-trials-settings' ],
        ];
    }

    public static function is_active( string $key ): bool {
        if ( ! isset( self::optional()[ $key ] ) ) {
            return true; // kernonderdeel
        }
        $saved = get_option( self::OPTION, [] );
        if ( ! is_array( $saved ) || ! array_key_exists( $key, $saved ) ) {
            return true;
        }
        return (bool) $saved[ $key ];
    }

    public static function init(): void {
        add_action( 'admin_post_sta_save_modules', [ __CLASS__, 'handle_save' ] );
    }

    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang.', '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'sta_save_modules' );

        $enabled = isset( $_POST['sta_modules'] ) && is_array( $_POST['sta_modules'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['sta_modules'] ) )
            : [];

        $new = [];
        foreach ( array_keys( self::optional() ) as $key ) {
            $new[ $key ] = in_array( $key, $enabled, true ) ? 1 : 0;
        }
        update_option( self::OPTION, $new );

        // Foto-scan-cron opruimen als de module uit staat; bij aanzetten plant de module hem zelf weer in.
        if ( empty( $new['photos'] ) ) {
            wp_clear_scheduled_hook( 'sta_tournament_photo_scan' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=swimming-trials-ai&saved=1' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $can_edit = current_user_can( 'manage_options' );
        $saved    = ! empty( $_GET['saved'] );
        ?>
        <div class="wrap sta-wrap">
            <h1>WP Splashconnect</h1>
            <p>Beheer hier welke onderdelen van de plugin actief zijn. Uitgeschakelde modules verdwijnen uit het menu; hun gegevens blijven bewaard.</p>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>Modules opgeslagen.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['sta_pages'] ) ) : $made = (int) $_GET['sta_pages']; ?>
                <div class="notice notice-success is-dismissible"><p><?php echo $made ? esc_html( $made . ' pagina(\'s) aangemaakt.' ) : 'Er waren geen ontbrekende pagina\'s.'; ?></p></div>
            <?php endif; ?>

            <?php STA_Pages::render_card(); ?>

            <div class="sta-card">
                <h2>Altijd actief</h2>
                <ul style="margin:0;list-style:disc;padding-left:20px;">
                    <?php foreach ( self::core() as $item ) :
                        $url = isset( $item['url'] ) ? admin_url( $item['url'] ) : admin_url( 'admin.php?page=' . $item['page'] );
                        ?>
                        <li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="sta_save_modules">
                <?php wp_nonce_field( 'sta_save_modules' ); ?>

                <div class="sta-card">
                    <h2>Optionele modules</h2>
                    <table class="form-table" role="presentation">
                        <?php foreach ( self::optional() as $key => $mod ) :
                            $active = self::is_active( $key );
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="sta-mod-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $mod['label'] ); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               id="sta-mod-<?php echo esc_attr( $key ); ?>"
                                               name="sta_modules[]"
                                               value="<?php echo esc_attr( $key ); ?>"
                                               <?php checked( $active ); ?>
                                               <?php disabled( ! $can_edit ); ?>>
                                        Ingeschakeld
                                    </label>
                                    <?php if ( $active ) : ?>
                                        &nbsp;·&nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $mod['page'] ) ); ?>">Openen</a>
                                    <?php endif; ?>
                                    <p class="description"><?php echo esc_html( $mod['desc'] ); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if ( $can_edit ) : ?>
                        <?php submit_button( 'Modules opslaan' ); ?>
                    <?php else : ?>
                        <p class="description">Alleen beheerders kunnen modules aan- of uitzetten.</p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    }
}
