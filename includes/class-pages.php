<?php
/**
 * Standaardpagina's die de plugin nodig heeft (Clubs, Programma).
 * Toont de status op de Overzichtspagina en maakt ontbrekende pagina's aan via een knop.
 * Bestaande pagina's worden nooit gewijzigd.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STA_Pages {

    public static function init(): void {
        add_action( 'admin_post_sta_create_pages', [ __CLASS__, 'handle_create' ] );
    }

    /** Pagina's die de plugin verwacht. 'match' bepaalt hoe een bestaande pagina wordt herkend. */
    public static function definitions(): array {
        return [
            'clubs'    => [
                'title'    => 'Clubs',
                'slug'     => 'clubs',
                'template' => STA_Templates::PAGE_CLUB,
                'content'  => '',
                'desc'     => 'Overzicht van alle clubs (paginasjabloon "Club overzicht"). De clubpagina\'s linken hiernaartoe.',
            ],
            'programma' => [
                'title'     => 'Programma',
                'slug'      => 'programma',
                'template'  => '',
                'content'   => '[sta_program]',
                'shortcode' => 'sta_program',
                'desc'      => 'Wedstrijdprogramma per dag (shortcode [sta_program]). Afstandspagina\'s linken hiernaartoe.',
            ],
        ];
    }

    /** Zoekt een bestaande pagina (niet in de prullenbak). Geeft post-ID of 0. */
    public static function find( array $def ): int {
        $page = get_page_by_path( $def['slug'], OBJECT, 'page' );
        if ( $page && $page->post_status !== 'trash' ) {
            return (int) $page->ID;
        }

        if ( ! empty( $def['shortcode'] ) ) {
            global $wpdb;
            $id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'page' AND post_status IN ('publish','draft','private')
                   AND post_content LIKE %s LIMIT 1",
                '%[' . $wpdb->esc_like( $def['shortcode'] ) . '%'
            ) );
            if ( $id ) {
                return (int) $id;
            }
        }

        if ( ! empty( $def['template'] ) ) {
            $ids = get_posts( [
                'post_type'      => 'page',
                'post_status'    => [ 'publish', 'draft', 'private' ],
                'meta_key'       => '_wp_page_template',
                'meta_value'     => $def['template'],
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ] );
            if ( $ids ) {
                return (int) $ids[0];
            }
        }

        return 0;
    }

    public static function handle_create(): void {
        if ( ! current_user_can( 'publish_pages' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang.', '', [ 'response' => 403 ] );
        }
        check_admin_referer( 'sta_create_pages' );

        $created = 0;
        foreach ( self::definitions() as $def ) {
            if ( self::find( $def ) ) {
                continue;
            }
            $args = [
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => $def['title'],
                'post_name'    => $def['slug'],
                'post_content' => $def['content'],
            ];
            $id = wp_insert_post( $args, true );
            if ( ! is_wp_error( $id ) && $id && ! empty( $def['template'] ) ) {
                // Direct als meta zetten: werkt ook als het actieve thema het sjabloon niet zelf kent.
                update_post_meta( $id, '_wp_page_template', $def['template'] );
            }
            if ( ! is_wp_error( $id ) && $id ) {
                $created++;
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=swimming-trials-ai&sta_pages=' . $created ) );
        exit;
    }

    public static function render_card(): void {
        $can_create = current_user_can( 'publish_pages' ) && current_user_can( 'manage_options' );
        $missing    = 0;
        ?>
        <div class="sta-card">
            <h2>Standaardpagina's</h2>
            <table class="widefat striped" style="max-width:760px;">
                <tbody>
                <?php foreach ( self::definitions() as $def ) :
                    $id = self::find( $def );
                    if ( ! $id ) {
                        $missing++;
                    }
                    ?>
                    <tr>
                        <td style="width:30%;"><strong><?php echo esc_html( $def['title'] ); ?></strong><br><code>/<?php echo esc_html( $def['slug'] ); ?>/</code></td>
                        <td><?php echo esc_html( $def['desc'] ); ?></td>
                        <td style="width:25%;white-space:nowrap;">
                            <?php if ( $id ) : ?>
                                <span style="color:#1a7f37;">✓ Bestaat</span> ·
                                <a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">Bewerken</a>
                            <?php else : ?>
                                <span style="color:#b32d2e;">✗ Ontbreekt</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $missing && $can_create ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                    <input type="hidden" name="action" value="sta_create_pages">
                    <?php wp_nonce_field( 'sta_create_pages' ); ?>
                    <?php submit_button( 'Ontbrekende pagina\'s aanmaken', 'primary', 'submit', false ); ?>
                    <span class="description" style="margin-left:8px;">De pagina's worden direct gepubliceerd. Bestaande pagina's blijven ongemoeid.</span>
                </form>
            <?php elseif ( ! $missing ) : ?>
                <p class="description" style="margin-top:10px;">Alle standaardpagina's zijn aanwezig.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
