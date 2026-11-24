<?php

class STA_Speaker_Role {

    const ROLE_SLUG = 'sta_speaker';

    public static function register(): void {
        add_action( 'init',               [ __CLASS__, 'ensure_role' ] );
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'dashboard_widget' ], 999 );
    }

    public static function ensure_role(): void {
        // Altijd bijwerken zodat nieuwe caps direct actief zijn
        remove_role( self::ROLE_SLUG );
        add_role( self::ROLE_SLUG, 'Speaker', self::capabilities() );
    }

    public static function remove_role(): void {
        remove_role( self::ROLE_SLUG );
    }

    private static function capabilities(): array {
        return [
            'read'                    => true,

            // Posts bewerken en publiceren
            'edit_posts'              => true,
            'edit_others_posts'       => true,
            'edit_published_posts'    => true,
            'publish_posts'           => true,
            'publish_pages'           => true,
            'read_private_posts'      => true,

            // Media
            'upload_files'            => true,

            // Pagina's lezen
            'edit_pages'              => true,
            'read_private_pages'      => true,

            // Atleten CPT (zelfde caps als posts want geen custom capability_type)
            'edit_others_pages'       => true,
            'edit_published_pages'    => true,
        ];
    }

    public static function dashboard_widget(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'sta_speaker_widget',
            '🎙️ Speakers',
            [ __CLASS__, 'render_widget' ]
        );

        // Zet widget bovenaan in de 'normal' kolom
        global $wp_meta_boxes;
        if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['sta_speaker_widget'] ) ) {
            $widget = $wp_meta_boxes['dashboard']['normal']['core']['sta_speaker_widget'];
            unset( $wp_meta_boxes['dashboard']['normal']['core']['sta_speaker_widget'] );
            $wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
                [ 'sta_speaker_widget' => $widget ],
                $wp_meta_boxes['dashboard']['normal']['core']
            );
        }
    }

    public static function render_widget(): void {
        $url_speakers = admin_url( 'admin.php?page=swimming-trials-speakers' );
        $url_enrich   = admin_url( 'admin.php?page=swimming-trials-enrich' );
        ?>
        <p style="margin:0 0 14px;color:#555;">Beheer sprekers-notities per atleet, of verrijk atleten met bio's en foto's.</p>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <a href="<?php echo esc_url( $url_speakers ); ?>" class="button button-primary" style="text-align:center;">
                🎙️ Sprekers-notities →
            </a>
            <a href="<?php echo esc_url( $url_enrich ); ?>" class="button" style="text-align:center;">
                ✨ Atleten verrijken →
            </a>
        </div>
        <?php
    }
}
