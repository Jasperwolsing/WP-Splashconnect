<?php
defined('ABSPATH') || exit;

class STA_Admin_NameRecording {

    static function register(): void {
        add_action('admin_menu',       [self::class, 'add_menu']);
        add_action('add_meta_boxes',   [self::class, 'add_meta_box']);
        register_setting('sta_namerecording', 'sta_name_recording_enabled', [
            'type'    => 'boolean',
            'default' => true,
        ]);
    }

    static function add_menu(): void {
        add_submenu_page(
            'swimming-trials-ai',
            'Naam opnamen',
            'Naam opnamen',
            'manage_options',
            'sta-name-recording',
            [self::class, 'render']
        );
    }

    static function add_meta_box(): void {
        add_meta_box(
            'sta_name_audio',
            'Naam opname',
            [self::class, 'render_meta_box'],
            'athlete',
            'side',
            'default'
        );
    }

    static function render_meta_box(\WP_Post $post): void {
        $rel      = get_post_meta($post->ID, 'sta_name_audio', true);
        $duration = (int) get_post_meta($post->ID, 'sta_name_audio_duration', true);
        if ($rel) {
            $url = home_url('/' . ltrim($rel, '/'));
            echo '<audio controls src="' . esc_url($url) . '" style="width:100%;"></audio>';
            if ($duration) {
                echo '<p style="font-size:11px;color:#999;margin:4px 0 0;">' . $duration . ' seconden</p>';
            }
        } else {
            echo '<p style="color:#999;font-size:13px;">Geen opname beschikbaar.</p>';
        }
    }

    static function render(): void {
        if (isset($_POST['sta_name_recording_enabled'])) {
            check_admin_referer('sta_namerecording-options');
            update_option('sta_name_recording_enabled', (bool)$_POST['sta_name_recording_enabled']);
            echo '<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>';
        }

        $enabled  = get_option('sta_name_recording_enabled', true);
        $audioDir = ABSPATH . 'EST2026/audio/';
        $filter   = $_GET['filter'] ?? 'all';

        // Build recording index from WP meta
        $athletes = get_posts([
            'post_type'   => 'athlete',
            'post_status' => ['publish','draft'],
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        $totalAthletes  = count($athletes);
        $recordingCount = 0;
        $longCount      = 0; // > 5 seconds

        foreach ($athletes as $a) {
            $rel      = get_post_meta($a->ID, 'sta_name_audio', true);
            $duration = (int) get_post_meta($a->ID, 'sta_name_audio_duration', true);
            if ($rel) {
                $recordingCount++;
                if ($duration > 5) $longCount++;
            }
        }
        ?>
        <div class="wrap">
            <h1>Naam opnamen</h1>

            <div style="display:flex;gap:16px;margin:16px 0 24px;flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $recordingCount; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">Opnames gemaakt</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $totalAthletes; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">Totaal atleten</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:<?php echo $longCount > 0 ? '#c00' : '#46b450'; ?>;"><?php echo $longCount; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">Langer dan 5 sec</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:<?php echo $enabled ? '#46b450' : '#c00'; ?>;">
                        <?php echo $enabled ? 'AAN' : 'UIT'; ?>
                    </div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">Functie status</div>
                </div>
            </div>

            <form method="post" style="margin-bottom:28px;">
                <?php wp_nonce_field('sta_namerecording-options'); ?>
                <input type="hidden" name="sta_name_recording_enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
                <button type="submit" class="button <?php echo $enabled ? 'button-secondary' : 'button-primary'; ?>">
                    <?php echo $enabled ? 'Functie uitschakelen' : 'Functie inschakelen'; ?>
                </button>
            </form>

            <!-- Filter -->
            <div style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
                <strong style="font-size:13px;">Filter:</strong>
                <?php
                $filters = [
                    'all'  => 'Alle atleten',
                    'has'  => 'Heeft opname',
                    'none' => 'Geen opname',
                    'long' => '&gt; 5 seconden',
                ];
                $base_url = admin_url('admin.php?page=sta-name-recording');
                foreach ($filters as $k => $label) :
                    $active = ($filter === $k);
                ?>
                <a href="<?php echo esc_url($base_url . '&filter=' . $k); ?>"
                   style="padding:5px 14px;border-radius:20px;font-size:13px;text-decoration:none;
                          background:<?php echo $active ? '#13315c' : '#f0f0f0'; ?>;
                          color:<?php echo $active ? '#fff' : '#333'; ?>;
                          border:1px solid <?php echo $active ? '#13315c' : '#ddd'; ?>;">
                    <?php echo $label; ?>
                    <?php if ($k === 'long' && $longCount > 0) echo ' <span style="background:#c00;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;">' . $longCount . '</span>'; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <table class="widefat striped" style="max-width:740px;">
                <thead>
                    <tr>
                        <th>Atleet</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:80px;">Duur</th>
                        <th>Opname</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $shown = 0;
                foreach ($athletes as $athlete) :
                    $rel      = get_post_meta($athlete->ID, 'sta_name_audio', true);
                    $duration = (int) get_post_meta($athlete->ID, 'sta_name_audio_duration', true);
                    $hasRec   = (bool) $rel;
                    $isLong   = $duration > 5;

                    if ($filter === 'has'  && ! $hasRec)         continue;
                    if ($filter === 'none' && $hasRec)           continue;
                    if ($filter === 'long' && (! $hasRec || ! $isLong)) continue;

                    $shown++;
                    $club = get_post_meta($athlete->ID, 'sta_club', true);
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($athlete->ID)); ?>"
                               style="font-weight:600;text-decoration:none;color:#13315C;">
                                <?php echo esc_html($athlete->post_title); ?>
                            </a>
                            <?php if ($club) : ?>
                                <span style="font-size:11px;color:#999;margin-left:6px;"><?php echo esc_html($club); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasRec) : ?>
                                <span style="color:#46b450;font-weight:600;">&#10003; Opgenomen</span>
                            <?php else : ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasRec && $duration) : ?>
                                <span style="<?php echo $isLong ? 'color:#c00;font-weight:600;' : 'color:#333;'; ?>">
                                    <?php echo $duration; ?>s
                                </span>
                            <?php elseif ($hasRec) : ?>
                                <span style="color:#999;">?</span>
                            <?php else : ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($hasRec) :
                                $url = home_url('/' . ltrim($rel, '/'));
                            ?>
                                <audio src="<?php echo esc_url($url); ?>" controls
                                       style="height:32px;vertical-align:middle;max-width:200px;"></audio>
                                <button class="sta-del-audio button button-small"
                                        data-id="<?php echo $athlete->ID; ?>"
                                        style="margin-left:8px;color:#c00;border-color:#c00;"
                                        title="Verwijder opname">
                                    &#128465;
                                </button>
                            <?php else : ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($shown === 0) : ?>
                    <tr><td colspan="4" style="color:#999;padding:16px;">Geen atleten gevonden voor dit filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        (function(){
            var nonce   = '<?php echo esc_js( wp_create_nonce('sta_nonce') ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';

            document.querySelectorAll('.sta-del-audio').forEach(function(btn){
                btn.addEventListener('click', async function(){
                    if (!confirm('Opname verwijderen?')) return;
                    var postId = btn.dataset.id;
                    btn.disabled = true;
                    var fd = new FormData();
                    fd.append('action',  'sta_delete_name_audio');
                    fd.append('nonce',   nonce);
                    fd.append('post_id', postId);
                    try {
                        var r = await fetch(ajaxUrl, {method:'POST', body:fd});
                        var d = await r.json();
                        if (d.success) {
                            var row = btn.closest('tr');
                            // Update status cell, duration cell, audio cell
                            var cells = row.querySelectorAll('td');
                            cells[1].innerHTML = '<span style="color:#999;">—</span>';
                            cells[2].innerHTML = '<span style="color:#ccc;">—</span>';
                            cells[3].innerHTML = '<span style="color:#ccc;">—</span>';
                            row.style.opacity = '.5';
                        } else {
                            alert('Fout: ' + (d.data || 'onbekend'));
                            btn.disabled = false;
                        }
                    } catch(e) {
                        alert('Netwerkfout.');
                        btn.disabled = false;
                    }
                });
            });
        })();
        </script>
        <?php
    }
}
