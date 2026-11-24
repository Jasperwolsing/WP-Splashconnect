<?php
defined('ABSPATH') || exit;

class STA_Admin_Heroshot {

    public static function register(): void {
        add_action('wp_ajax_sta_link_dqr2026_athletes',  [__CLASS__, 'ajax_link_athletes']);
        add_action('wp_ajax_nopriv_sta_heroshot_track',  [__CLASS__, 'ajax_track']);
        add_action('wp_ajax_sta_heroshot_track',         [__CLASS__, 'ajax_track']);
    }

    /**
     * Geheime sleutel voor de HMAC van Heroshot-bestandsnamen.
     * Instellen via wp-config.php:  define( 'STA_HEROSHOT_SECRET', '...' );
     * of via de optie sta_heroshot_secret. Staat bewust NIET in de code (publieke repo).
     */
    public static function secret(): string {
        if ( defined( 'STA_HEROSHOT_SECRET' ) && STA_HEROSHOT_SECRET ) {
            return (string) STA_HEROSHOT_SECRET;
        }
        return (string) get_option( 'sta_heroshot_secret', '' );
    }

    public static function ajax_track(): void {
        $action = sanitize_key( $_POST['track_action'] ?? '' );
        $hash   = sanitize_text_field( $_POST['hash'] ?? '' );
        if ( ! in_array( $action, [ 'share', 'download' ], true ) ) {
            wp_send_json_error();
        }
        $stats = get_option( 'sta_heroshot_stats', [] );
        $key   = $action === 'share' ? 'shares' : 'downloads';
        $stats[ $key ] = ( $stats[ $key ] ?? 0 ) + 1;
        if ( $hash ) {
            $stats['by_hash'][ $hash ][ $key ] = ( $stats['by_hash'][ $hash ][ $key ] ?? 0 ) + 1;
        }
        update_option( 'sta_heroshot_stats', $stats );
        wp_send_json_success();
    }

    // ── AJAX: create/update DQR2026 data files matched by sta_swrid ──────────
    public static function ajax_link_athletes(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $secret = self::secret();
        if ($secret === '') {
            wp_send_json_error('Geen Heroshot-sleutel ingesteld. Vul hem in bij WP Splashconnect → Instellingen → Heroshot.');
        }

        $data_dir = ABSPATH . 'DQR2026/data/';
        if (!is_dir($data_dir) && !mkdir($data_dir, 0755, true)) {
            wp_send_json_error('Map DQR2026/data/ kan niet aangemaakt worden.');
        }

        global $wpdb;
        // Fetch all athletes with sta_meet_athlete_id (used as DQR2026 URL id)
        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm.meta_value AS meet_id
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'sta_meet_athlete_id'
             WHERE p.post_type = 'athlete' AND p.post_status IN ('publish','draft')
               AND pm.meta_value != ''",
            ARRAY_A
        );

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $meet_id = (string)$row['meet_id'];
            $post_id = (int)$row['ID'];
            $name    = $row['post_title'];
            $wp_url  = get_permalink($post_id);
            $file    = $data_dir . $meet_id . '.json';
            $hmac    = hash_hmac('sha256', $meet_id, $secret);

            $existing = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

            // Skip if already fully linked and correct
            if (
                isset($existing['post_id'], $existing['wp_url']) &&
                (int)$existing['post_id'] === $post_id &&
                $existing['wp_url'] === $wp_url &&
                get_post_meta($post_id, 'sta_heroshot_hash', true) === $hmac
            ) {
                continue;
            }

            $data = array_merge($existing, [
                'post_id' => $post_id,
                'wp_url'  => $wp_url,
                'name'    => $existing['name'] ?? $name,
            ]);

            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            update_post_meta($post_id, 'sta_heroshot_id',   $meet_id);
            update_post_meta($post_id, 'sta_heroshot_hash', $hmac);
            if ($existing) $updated++; else $created++;
        }

        $no_swrid_posts = get_posts([
            'post_type'   => 'athlete',
            'post_status' => ['publish','draft'],
            'numberposts' => -1,
            'meta_query'  => [['key' => 'sta_meet_athlete_id', 'compare' => 'NOT EXISTS']],
        ]);
        $no_swrid = array_map(fn($p) => $p->post_title, $no_swrid_posts);

        wp_send_json_success([
            'created'  => $created,
            'updated'  => $updated,
            'no_swrid' => $no_swrid,
        ]);
    }

    public static function render(): void {
        global $wpdb;

        $total = (int) wp_count_posts('athlete')->publish;

        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title,
                    pm_hash.meta_value AS hs_hash,
                    pm_url.meta_value  AS wp_url
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_hash ON pm_hash.post_id = p.ID AND pm_hash.meta_key = 'sta_heroshot_hash'
             LEFT JOIN {$wpdb->postmeta} pm_url  ON pm_url.post_id  = p.ID AND pm_url.meta_key  = 'sta_athlete_url'
             WHERE p.post_type = 'athlete' AND p.post_status = 'publish'
             ORDER BY p.post_title ASC"
        );

        $video_dir      = ABSPATH . 'DQR2026/videos/';
        $with_video     = []; // publiek (.public bestand aanwezig)
        $recorded_video = []; // opgenomen maar niet publiek (alleen .mp4)
        $without_video  = []; // helemaal geen video

        foreach ( $rows as $row ) {
            $has_mp4    = $row->hs_hash && file_exists( $video_dir . $row->hs_hash . '.mp4' );
            $has_public = $row->hs_hash && file_exists( $video_dir . $row->hs_hash . '.public' );
            if ( $has_public ) {
                $with_video[] = $row;
            } elseif ( $has_mp4 ) {
                $recorded_video[] = $row;
            } else {
                $without_video[] = $row;
            }
        }

        $count_with     = count( $with_video );
        $count_recorded = count( $recorded_video );
        $count_without  = count( $without_video );

        $filter = $_GET['hs_filter'] ?? 'with';
        $base   = admin_url( 'admin.php?page=swimming-trials-heroshot' );

        $hs_stats      = get_option( 'sta_heroshot_stats', [] );
        $stat_downloads = intval( $hs_stats['downloads'] ?? 0 );
        $stat_shares    = intval( $hs_stats['shares']    ?? 0 );
        ?>
        <div class="wrap">
            <h1>Heroshot video's</h1>

            <div style="margin-bottom:24px;padding:16px 20px;background:#fff;border:1px solid #ddd;border-radius:8px;max-width:560px;">
                <strong style="display:block;margin-bottom:6px;">Koppel DQR2026 data aan WordPress-atleten</strong>
                <p style="margin:0 0 12px;font-size:13px;color:#555;">Leest alle bestanden in <code>DQR2026/data/</code> en vult <code>wp_url</code> en <code>post_id</code> in op basis van naam. Vereist voor publieke profiellink, heroshot openbaar zetten en naamopnames.</p>
                <button id="sta-link-dqr2026" class="button button-primary">Atleten koppelen aan DQR2026</button>
                <span id="sta-link-dqr2026-result" style="margin-left:12px;font-size:13px;"></span>
            </div>

            <script>
            document.getElementById('sta-link-dqr2026').addEventListener('click', function() {
                var btn = this;
                var res = document.getElementById('sta-link-dqr2026-result');
                btn.disabled = true; btn.textContent = 'Bezig…';
                var fd = new FormData();
                fd.append('action', 'sta_link_dqr2026_athletes');
                fd.append('nonce', '<?php echo esc_js(wp_create_nonce('sta_nonce')); ?>');
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {method:'POST', body:fd})
                    .then(function(r){return r.json();})
                    .then(function(d){
                        btn.disabled = false; btn.textContent = 'Atleten koppelen aan DQR2026';
                        if (d.success) {
                            res.style.color = '#46b450';
                            var msg = '✓ ' + d.data.created + ' aangemaakt, ' + d.data.updated + ' bijgewerkt';
                            if (d.data.no_swrid && d.data.no_swrid.length) {
                                msg += ' — zonder swrid: ' + d.data.no_swrid.join(', ');
                            }
                            res.textContent = msg;
                        } else {
                            res.style.color = '#c00';
                            res.textContent = '✗ ' + (d.data || 'Fout');
                        }
                    })
                    .catch(function(){ btn.disabled=false; btn.textContent='Atleten koppelen aan DQR2026'; res.style.color='#c00'; res.textContent='Verbindingsfout.'; });
            });
            </script>

            <div style="display:flex;gap:16px;margin:0 0 16px;flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $stat_downloads; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">⬇️ Downloads</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $stat_shares; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">↗️ Shares</div>
                </div>
            </div>

            <div style="display:flex;gap:16px;margin:0 0 24px;flex-wrap:wrap;">
                <a href="<?php echo esc_url( $base . '&hs_filter=with' ); ?>" style="text-decoration:none;">
                    <div style="background:<?php echo $filter === 'with' ? '#13315c' : '#fff'; ?>;border:1px solid <?php echo $filter === 'with' ? '#13315c' : '#ddd'; ?>;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;cursor:pointer;">
                        <div style="font-size:34px;font-weight:700;color:<?php echo $filter === 'with' ? '#fff' : '#13315C'; ?>;"><?php echo $count_with; ?></div>
                        <div style="font-size:12px;color:<?php echo $filter === 'with' ? '#aac' : '#666'; ?>;margin-top:4px;">Publiek beschikbaar</div>
                    </div>
                </a>
                <a href="<?php echo esc_url( $base . '&hs_filter=recorded' ); ?>" style="text-decoration:none;">
                    <div style="background:<?php echo $filter === 'recorded' ? '#13315c' : '#fff'; ?>;border:1px solid <?php echo $filter === 'recorded' ? '#13315c' : '#ddd'; ?>;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;cursor:pointer;">
                        <div style="font-size:34px;font-weight:700;color:<?php echo $filter === 'recorded' ? '#fff' : '#e67e22'; ?>;"><?php echo $count_recorded; ?></div>
                        <div style="font-size:12px;color:<?php echo $filter === 'recorded' ? '#aac' : '#666'; ?>;margin-top:4px;">Opgenomen, niet publiek</div>
                    </div>
                </a>
                <a href="<?php echo esc_url( $base . '&hs_filter=without' ); ?>" style="text-decoration:none;">
                    <div style="background:<?php echo $filter === 'without' ? '#13315c' : '#fff'; ?>;border:1px solid <?php echo $filter === 'without' ? '#13315c' : '#ddd'; ?>;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;cursor:pointer;">
                        <div style="font-size:34px;font-weight:700;color:<?php echo $filter === 'without' ? '#fff' : ( $count_without > 0 ? '#c00' : '#46b450' ); ?>;"><?php echo $count_without; ?></div>
                        <div style="font-size:12px;color:<?php echo $filter === 'without' ? '#aac' : '#666'; ?>;margin-top:4px;">Nog geen video</div>
                    </div>
                </a>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $total; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">Totaal atleten</div>
                </div>
            </div>

            <?php if ( $filter === 'with' ) : ?>

                <table class="widefat striped" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th>Atleet</th>
                            <th style="width:40px;text-align:center;">Publiek</th>
                            <th style="width:120px;">Profiel</th>
                            <th style="width:100px;">Bewerken</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $all_with_video = array_merge( $with_video, $recorded_video );
                    usort( $all_with_video, fn( $a, $b ) => strcmp( $a->post_title, $b->post_title ) );
                    $public_ids = array_column( $with_video, 'ID' );
                    foreach ( $all_with_video as $a ) :
                        $is_public   = in_array( $a->ID, $public_ids );
                        $profile_url = get_permalink( $a->ID );
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo esc_html( $a->post_title ); ?></td>
                            <td style="text-align:center;">
                                <?php if ( $is_public ) : ?>
                                    <span title="Publiek beschikbaar" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#46b450;"></span>
                                <?php else : ?>
                                    <span title="Niet publiek" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ddd;"></span>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?php echo esc_url( $profile_url ); ?>" target="_blank">Bekijk profiel</a></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $a->ID ) ); ?>">Bewerken</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $all_with_video ) ) : ?>
                        <tr><td colspan="4" style="color:#999;">Nog geen heroshot video's opgenomen.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ( $filter === 'recorded' ) : ?>

                <table class="widefat striped" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th>Atleet</th>
                            <th style="width:120px;">Publiek profiel</th>
                            <th style="width:100px;">Bewerken</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $recorded_video as $a ) :
                        $profile_url = get_permalink( $a->ID );
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo esc_html( $a->post_title ); ?></td>
                            <td><a href="<?php echo esc_url( $profile_url ); ?>" target="_blank">Bekijk profiel</a></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $a->ID ) ); ?>">Bewerken</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $recorded_video ) ) : ?>
                        <tr><td colspan="3" style="color:#999;">Geen atleten met een opgenomen maar niet-publieke heroshot video.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

            <?php else : ?>

                <table class="widefat striped" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th>Atleet</th>
                            <th style="width:100px;">Bewerken</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $without_video as $a ) : ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo esc_html( $a->post_title ); ?></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $a->ID ) ); ?>">Bewerken</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $without_video ) ) : ?>
                        <tr><td colspan="2" style="color:#999;">Alle atleten hebben een video!</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>
        <?php
    }
}
