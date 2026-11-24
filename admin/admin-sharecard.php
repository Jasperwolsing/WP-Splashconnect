<?php

class STA_Admin_Sharecard {

    public static function register(): void {
        add_action( 'wp_ajax_sta_sharecard_save',        [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_nopriv_sta_sharecard_track', [ __CLASS__, 'ajax_track' ] );
        add_action( 'wp_ajax_sta_sharecard_track',        [ __CLASS__, 'ajax_track' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( string $hook ): void {
        if ( strpos( $hook, 'swimming-trials-sharecard' ) === false ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-resizable' );
    }

    public static function register_rest(): void {
        register_rest_route( 'sta/v1', '/share-card/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_generate' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'validate_callback' => function( $v ) { return is_numeric( $v ); } ],
            ],
        ] );
    }

    public static function rest_generate( WP_REST_Request $request ): void {
        require_once STA_DIR . 'includes/class-sharecard-generator.php';
        $post_id = intval( $request['id'] );
        $post    = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'athlete' || $post->post_status !== 'publish' ) {
            status_header( 404 );
            echo 'Athlete not found.';
            exit;
        }
        STA_Sharecard_Generator::generate( $post_id );
    }

    public static function ajax_save(): void {
        check_ajax_referer( 'sta_sharecard_save', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Geen toegang.' );
        }

        $raw  = wp_unslash( $_POST['settings'] ?? '' );
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'Ongeldige data.' );
        }

        $clean = [
            'bg_id'    => intval( $data['bg_id'] ?? 0 ),
            'logo_id'  => intval( $data['logo_id'] ?? 0 ),
            'enabled'  => ! empty( $data['enabled'] ),
            'elements' => [],
            'extras'   => [],
        ];

        foreach ( $data['extras'] ?? [] as $ex ) {
            $eid = intval( $ex['id'] ?? 0 );
            if ( ! $eid ) continue;
            $clean['extras'][] = [
                'id' => $eid,
                'x'  => floatval( $ex['x'] ?? 0 ),
                'y'  => floatval( $ex['y'] ?? 0 ),
                'w'  => floatval( $ex['w'] ?? 20 ),
            ];
        }

        foreach ( [ 'photo', 'flag', 'logo', 'name', 'events' ] as $key ) {
            if ( ! isset( $data['elements'][ $key ] ) ) continue;
            $el   = $data['elements'][ $key ];
            $base = [
                'x' => floatval( $el['x'] ?? 0 ),
                'y' => floatval( $el['y'] ?? 0 ),
            ];
            if ( $key === 'photo' ) {
                $base['w'] = floatval( $el['w'] ?? 30 );
                $base['h'] = floatval( $el['h'] ?? 40 );
            } elseif ( in_array( $key, [ 'flag', 'logo' ] ) ) {
                $base['w'] = floatval( $el['w'] ?? 10 );
            } else {
                $base['size']  = intval( $el['size'] ?? 60 );
                $base['color'] = preg_match( '/^#[0-9a-fA-F]{3,6}$/', $el['color'] ?? '' ) ? $el['color'] : '#ffffff';
                if ( $key === 'events' ) {
                    $base['line_height'] = intval( $el['line_height'] ?? 64 );
                    $base['pill_color']  = preg_match( '/^#[0-9a-fA-F]{3,6}$/', $el['pill_color'] ?? '' ) ? $el['pill_color'] : '#13315c';
                    $base['pill_shape']  = ( ( $el['pill_shape'] ?? 'rounded' ) === 'rectangular' ) ? 'rectangular' : 'rounded';
                }
            }
            $clean['elements'][ $key ] = $base;
        }

        update_option( 'sta_sharecard', $clean );
        wp_send_json_success();
    }

    public static function ajax_track(): void {
        $action  = sanitize_key( $_POST['track_action'] ?? '' );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! in_array( $action, [ 'share', 'download' ], true ) || ! $post_id ) {
            wp_send_json_error();
        }
        $stats = get_option( 'sta_sharecard_stats', [] );
        $key   = $action === 'share' ? 'shares' : 'downloads';
        $stats[ $key ] = ( $stats[ $key ] ?? 0 ) + 1;
        // Per-atleet teller
        $stats['by_athlete'][ $post_id ][ $key ] = ( $stats['by_athlete'][ $post_id ][ $key ] ?? 0 ) + 1;
        update_option( 'sta_sharecard_stats', $stats );
        wp_send_json_success();
    }

    public static function render(): void {
        $sc_stats  = get_option( 'sta_sharecard_stats', [] );
        $stat_shares    = intval( $sc_stats['shares']    ?? 0 );
        $stat_downloads = intval( $sc_stats['downloads'] ?? 0 );

        $settings = get_option( 'sta_sharecard', [] );
        $bg_id    = intval( $settings['bg_id'] ?? 0 );
        $bg_url   = $bg_id ? wp_get_attachment_image_url( $bg_id, 'large' ) : '';
        $elements = $settings['elements'] ?? [];

        $defaults = [
            'photo'  => [ 'x' => 5,  'y' => 5,  'w' => 90, 'h' => 55 ],
            'name'   => [ 'x' => 5,  'y' => 62, 'size' => 72, 'color' => '#ffffff' ],
            'flag'   => [ 'x' => 5,  'y' => 73, 'w' => 12 ],
            'events' => [ 'x' => 5,  'y' => 78, 'size' => 40, 'color' => '#ffffff', 'line_height' => 64, 'pill_color' => '#13315c', 'pill_shape' => 'rounded' ],
            'logo'   => [ 'x' => 70, 'y' => 62, 'w' => 25 ],
        ];
        foreach ( $defaults as $k => $d ) {
            if ( ! isset( $elements[ $k ] ) ) {
                $elements[ $k ] = $d;
            }
        }

        $preview_athletes = get_posts( [
            'post_type'   => 'athlete',
            'post_status' => 'publish',
            'numberposts' => 50,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ] );

        // Tel atleten met heroshot
        $counts         = wp_count_posts( 'athlete' );
        $total_athletes  = (int) $counts->publish;
        global $wpdb;
        $heroshot_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
             WHERE p.post_type = 'athlete' AND p.post_status = 'publish'"
        );

        $logo_id   = intval( $settings['logo_id'] ?? 0 );
        $logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $enabled   = ! empty( $settings['enabled'] );
        $nonce     = wp_create_nonce( 'sta_sharecard_save' );
        $rest_base = rest_url( 'sta/v1/share-card/' );

        $extras_with_urls = [];
        foreach ( $settings['extras'] ?? [] as $ex ) {
            $eid = intval( $ex['id'] ?? 0 );
            if ( ! $eid ) continue;
            $extras_with_urls[] = [
                'id'  => $eid,
                'x'   => floatval( $ex['x'] ?? 0 ),
                'y'   => floatval( $ex['y'] ?? 0 ),
                'w'   => floatval( $ex['w'] ?? 20 ),
                'url' => wp_get_attachment_image_url( $eid, 'medium' ) ?: '',
            ];
        }

        // Pass data to footer JS via a global
        $js_data = wp_json_encode( [
            'elements'  => $elements,
            'enabled'   => $enabled,
            'logo_id'   => $logo_id,
            'nonce'     => $nonce,
            'restBase'  => $rest_base,
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'extras'    => $extras_with_urls,
        ] );

        // Output JS in admin_footer so it runs after jQuery UI is loaded
        add_action( 'admin_footer', function() use ( $js_data ) {
            ?>
<style>
.ui-resizable-handle { background: rgba(255,255,255,.7); border: 1px solid #0096ff; border-radius: 3px; width: 10px !important; height: 10px !important; z-index: 100; }
.ui-resizable-se { right: -2px; bottom: -2px; cursor: se-resize; }
.ui-resizable-sw { left: -2px; bottom: -2px; cursor: sw-resize; }
.ui-resizable-ne { right: -2px; top: -2px; cursor: ne-resize; }
.ui-resizable-nw { left: -2px; top: -2px; cursor: nw-resize; }
.ui-resizable-e  { right: -5px; top: 50%; margin-top:-5px; cursor: e-resize; }
.ui-resizable-w  { left: -5px; top: 50%; margin-top:-5px; cursor: w-resize; }
.ui-resizable-s  { bottom: -5px; left: 50%; margin-left:-5px; cursor: s-resize; }
.ui-resizable-n  { top: -5px; left: 50%; margin-left:-5px; cursor: n-resize; }
</style>
<script>
jQuery(function($) {
    var cfg         = <?php echo $js_data; ?>;
    var elState     = cfg.elements;
    var extrasState = {}; // localId => { id, x, y, w }
    var extraCtr    = 0;
    var STAGE_W     = 400;
    var STAGE_H     = 500;

    function addExtraToStage(localId, attId, imgUrl, x, y, w) {
        var $el = $('<div class="sc-el" id="sc-el-extra-' + localId + '">')
            .css({ position:'absolute', zIndex:12, left:x+'%', top:y+'%',
                   width:w+'%', minWidth:'20px', boxSizing:'border-box', cursor:'move' });
        if (imgUrl) {
            $el.html('<img src="' + imgUrl + '" style="width:100%;height:auto;display:block;pointer-events:none;opacity:.9;">');
        } else {
            $el.css({ background:'rgba(230,126,34,.25)', border:'2px dashed #e67e22' })
               .html('<span style="color:#e67e22;font-weight:700;font-size:11px;padding:4px 8px;display:block;">🖼 Extra</span>');
        }
        $('#sc-stage').append($el);
        $el.draggable({ containment:'#sc-stage', stop: function(e,ui) {
            extrasState[localId].x = parseFloat((ui.position.left / STAGE_W * 100).toFixed(2));
            extrasState[localId].y = parseFloat((ui.position.top  / STAGE_H * 100).toFixed(2));
        }}).resizable({ containment:'#sc-stage', handles:'e,w', stop: function(e,ui) {
            extrasState[localId].w = parseFloat((ui.size.width / STAGE_W * 100).toFixed(2));
        }});
    }

    function addExtraToList(localId, attId, imgUrl) {
        var $row = $('<div id="sc-extra-item-' + localId + '" style="display:flex;align-items:center;gap:8px;padding:6px 8px;background:#f6f7f7;border-radius:5px;border:1px solid #ddd;">');
        if (imgUrl) $row.append('<img src="' + imgUrl + '" style="width:40px;height:40px;object-fit:cover;border-radius:3px;border:1px solid #ccc;flex-shrink:0;">');
        $row.append('<span style="flex:1;font-size:12px;color:#555;">Afbeelding #' + (localId + 1) + '</span>');
        $row.append($('<button type="button" class="button button-small" style="color:#c00;border-color:#c00;flex-shrink:0;">×</button>')
            .on('click', function() {
                delete extrasState[localId];
                $('#sc-el-extra-' + localId).remove();
                $('#sc-extra-item-' + localId).remove();
            }));
        $('#sc-extras-list').append($row);
    }

    // Init existing extras
    if (cfg.extras) {
        cfg.extras.forEach(function(ex) {
            var localId = extraCtr++;
            extrasState[localId] = { id: ex.id, x: ex.x, y: ex.y, w: ex.w };
            addExtraToStage(localId, ex.id, ex.url, ex.x, ex.y, ex.w);
            addExtraToList(localId, ex.id, ex.url);
        });
    }

    // Add extra via media picker
    var extraFrame;
    $('#sc-add-extra').on('click', function() {
        if (extraFrame) { extraFrame.open(); return; }
        extraFrame = wp.media({ title:'Kies extra afbeelding', button:{ text:'Selecteer' }, multiple:false, library:{ type:'image' } });
        extraFrame.on('select', function() {
            var att = extraFrame.state().get('selection').first().toJSON();
            var localId = extraCtr++;
            extrasState[localId] = { id: att.id, x: 5, y: 5, w: 20 };
            var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
            addExtraToStage(localId, att.id, url, 5, 5, 20);
            addExtraToList(localId, att.id, url);
            extraFrame = null; // reset so next add opens fresh
        });
        extraFrame.open();
    });

    // Draggable for all elements
    $('.sc-el').draggable({
        containment: '#sc-stage',
        stop: function(e, ui) {
            var key  = $(this).data('key');
            var xPct = parseFloat((ui.position.left / STAGE_W * 100).toFixed(2));
            var yPct = parseFloat((ui.position.top  / STAGE_H * 100).toFixed(2));
            if (!elState[key]) elState[key] = {};
            elState[key].x = xPct;
            elState[key].y = yPct;
        }
    });

    // Photo: resizable in all directions
    $('#sc-el-photo').resizable({
        containment: '#sc-stage',
        handles: 'n, e, s, w, ne, se, sw, nw',
        stop: function(e, ui) {
            elState['photo'].w = parseFloat((ui.size.width  / STAGE_W * 100).toFixed(2));
            elState['photo'].h = parseFloat((ui.size.height / STAGE_H * 100).toFixed(2));
        }
    });

    // Flag: resizable width only
    $('#sc-el-flag').resizable({
        containment: '#sc-stage',
        handles: 'e, w',
        stop: function(e, ui) {
            var wPct = parseFloat((ui.size.width / STAGE_W * 100).toFixed(2));
            elState['flag'].w = wPct;
            $('#sc-flag-w').val(wPct);
        }
    });

    // Logo: resizable width only
    $('#sc-el-logo').resizable({
        containment: '#sc-stage',
        handles: 'e, w',
        stop: function(e, ui) {
            var wPct = parseFloat((ui.size.width / STAGE_W * 100).toFixed(2));
            elState['logo'].w = wPct;
            $('.sc-setting[data-key="logo"][data-prop="w"]').val(wPct);
        }
    });

    // Settings inputs
    $('.sc-setting').on('change input', function() {
        var key  = $(this).data('key');
        var prop = $(this).data('prop');
        var val  = $(this).attr('type') === 'number' ? parseFloat($(this).val()) : $(this).val();
        if (!elState[key]) elState[key] = {};
        elState[key][prop] = val;
    });

    // Logo picker
    var logoFrame;
    $('#sc-choose-logo').on('click', function() {
        if (logoFrame) { logoFrame.open(); return; }
        logoFrame = wp.media({ title: 'Kies toernooilogo', button: { text: 'Selecteer' }, multiple: false, library: { type: 'image' } });
        logoFrame.on('select', function() {
            var att = logoFrame.state().get('selection').first().toJSON();
            $('#sc-logo-id').val(att.id);
            cfg.logo_id = att.id;
            var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
            $('#sc-logo-thumb').replaceWith('<img id="sc-logo-thumb" src="' + url + '" style="max-width:80px;max-height:60px;object-fit:contain;border-radius:4px;border:1px solid #ccc;background:#fff;padding:4px;">');
            $('#sc-el-logo').html('<img src="' + url + '" style="width:100%;height:auto;display:block;pointer-events:none;opacity:.8;">');
        });
        logoFrame.open();
    });

    // Background image picker
    var mediaFrame;
    $('#sc-choose-bg').on('click', function() {
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title: 'Kies achtergrondafbeelding',
            button: { text: 'Selecteer' },
            multiple: false,
            library: { type: 'image' }
        });
        mediaFrame.on('select', function() {
            var att = mediaFrame.state().get('selection').first().toJSON();
            $('#sc-bg-id').val(att.id);
            var url = (att.sizes && att.sizes.large) ? att.sizes.large.url : att.url;
            if ($('#sc-bg-img').length) {
                $('#sc-bg-img').attr('src', url);
            } else {
                $('#sc-stage').prepend('<img id="sc-bg-img" src="' + url + '" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;z-index:0;">');
                $('#sc-bg-placeholder').hide();
            }
            $('#sc-bg-thumb').replaceWith('<img id="sc-bg-thumb" src="' + url + '" style="width:80px;height:100px;object-fit:cover;border-radius:4px;border:1px solid #ccc;">');
        });
        mediaFrame.open();
    });

    function getPayload() {
        return {
            bg_id:    parseInt($('#sc-bg-id').val()) || 0,
            logo_id:  parseInt($('#sc-logo-id').val()) || 0,
            enabled:  $('#sc-enabled').is(':checked'),
            elements: elState,
            extras:   Object.values(extrasState)
        };
    }

    // Toggle slider animation
    $('#sc-enabled').on('change', function() {
        var on = this.checked;
        $('#sc-toggle-slider').css('background', on ? '#46b450' : '#ccc');
        $('#sc-toggle-knob').css('left', on ? '27px' : '3px');
    });

    // Save
    $('#sc-save').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#sc-save-status').hide();
        $.post(cfg.ajaxurl, {
            action:   'sta_sharecard_save',
            nonce:    cfg.nonce,
            settings: JSON.stringify(getPayload())
        }, function(resp) {
            if (resp.success) {
                $('#sc-save-status').text('✓ Opgeslagen!').fadeIn().delay(2500).fadeOut();
            } else {
                alert('Opslaan mislukt: ' + (resp.data || 'onbekende fout'));
            }
        }).always(function() { $btn.prop('disabled', false); });
    });

    // Preview
    $('#sc-preview').on('click', function() {
        var pid = $('#sc-preview-athlete').val();
        if (!pid) { alert('Kies eerst een atleet.'); return; }
        var url = cfg.restBase + pid + '?t=' + Date.now();
        $('#sc-preview-result').html(
            '<p style="color:#666;margin:0 0 8px;">Genereren... (kan 5-10 sec duren)</p>' +
            '<a href="' + url + '" target="_blank">' +
            '<img src="' + url + '" style="max-width:100%;border-radius:6px;border:1px solid #ddd;" ' +
            'onerror="this.closest(\'div\').innerHTML=\'<p style=color:red>Genereren mislukt. Sla eerst de template op.</p>\'">' +
            '</a>' +
            '<p style="font-size:12px;color:#666;margin:6px 0 0;">Klik op de afbeelding om op te slaan.</p>'
        );
    });
});
</script>
            <?php
        } );
        ?>

        <div class="wrap sta-wrap">
            <h1>Share card – template editor</h1>

            <div style="display:flex;gap:16px;margin:16px 0 24px;flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $total_athletes; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">Totaal atleten</div>
                </div>
                <div onclick="staStatsModal('shares')" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;cursor:pointer;" onmouseover="this.style.borderColor='#13315C'" onmouseout="this.style.borderColor='#ddd'">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $stat_shares; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">🔗 Delen geklikt</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;">klik voor details</div>
                </div>
                <div onclick="staStatsModal('downloads')" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 24px;text-align:center;min-width:130px;cursor:pointer;" onmouseover="this.style.borderColor='#13315C'" onmouseout="this.style.borderColor='#ddd'">
                    <div style="font-size:34px;font-weight:700;color:#13315C;"><?php echo $stat_downloads; ?></div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">⬇️ Downloads</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;">klik voor details</div>
                </div>
            </div>

            <?php
            // Bouw per-atleet stats op voor JS
            $by_athlete = $sc_stats['by_athlete'] ?? [];
            $athlete_rows = [];
            foreach ( $by_athlete as $pid => $counts ) {
                $post = get_post( (int) $pid );
                $athlete_rows[] = [
                    'name'      => $post ? $post->post_title : 'Atleet #' . $pid,
                    'url'       => $post ? get_edit_post_link( (int) $pid ) : '',
                    'shares'    => intval( $counts['shares']    ?? 0 ),
                    'downloads' => intval( $counts['downloads'] ?? 0 ),
                ];
            }
            usort( $athlete_rows, fn($a,$b) => ($b['shares']+$b['downloads']) - ($a['shares']+$a['downloads']) );
            ?>
            <script>
            var staAthleteStats = <?php echo json_encode( $athlete_rows ); ?>;

            function staStatsModal(type) {
                var title  = type === 'shares' ? '🔗 Delen per atleet' : '⬇️ Downloads per atleet';
                var key    = type === 'shares' ? 'shares' : 'downloads';
                var sorted = staAthleteStats.slice().sort(function(a,b){ return b[key]-a[key]; }).filter(function(r){ return r[key] > 0; });

                var rows = sorted.length ? sorted.map(function(r) {
                    var link = r.url ? '<a href="'+r.url+'" style="color:#13315C;font-weight:600;text-decoration:none;">'+r.name+'</a>' : r.name;
                    return '<tr><td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">'+link+'</td><td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:700;color:#13315C;">'+r[key]+'</td></tr>';
                }).join('') : '<tr><td colspan="2" style="padding:16px;text-align:center;color:#999;">Nog geen data</td></tr>';

                document.getElementById('sta-stats-modal-title').textContent = title;
                document.getElementById('sta-stats-modal-table').innerHTML = rows;
                document.getElementById('sta-stats-modal').style.display = 'flex';
            }
            </script>

            <!-- Stats detail modal -->
            <div id="sta-stats-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;box-sizing:border-box;">
                <div style="background:#fff;border-radius:12px;padding:28px;width:100%;max-width:480px;max-height:80vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.25);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h2 id="sta-stats-modal-title" style="margin:0;font-size:18px;color:#13315C;"></h2>
                        <button onclick="document.getElementById('sta-stats-modal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#999;line-height:1;">✕</button>
                    </div>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f5f7fa;">
                                <th style="padding:8px 12px;text-align:left;font-size:12px;color:#666;font-weight:600;border-bottom:2px solid #e0e7f0;">Atleet</th>
                                <th style="padding:8px 12px;text-align:right;font-size:12px;color:#666;font-weight:600;border-bottom:2px solid #e0e7f0;">Aantal</th>
                            </tr>
                        </thead>
                        <tbody id="sta-stats-modal-table"></tbody>
                    </table>
                </div>
            </div>

            <!-- Live toggle -->
            <div style="display:flex;align-items:center;gap:16px;padding:16px 20px;margin-bottom:24px;border-radius:8px;background:<?php echo $enabled ? '#edfaee' : '#fdf3f3'; ?>;border:1px solid <?php echo $enabled ? '#46b450' : '#dc3232'; ?>;">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;margin:0;">
                    <div style="position:relative;width:52px;height:28px;flex-shrink:0;">
                        <input type="checkbox" id="sc-enabled" <?php checked( $enabled ); ?>
                               style="opacity:0;width:0;height:0;position:absolute;"
                               onchange="document.getElementById('sc-toggle-wrap').style.background=this.checked?'#edfaee':'#fdf3f3';document.getElementById('sc-toggle-wrap').style.borderColor=this.checked?'#46b450':'#dc3232';document.getElementById('sc-toggle-label').textContent=this.checked?'Zichtbaar op atletenpagina\'s':'Verborgen op atletenpagina\'s';">
                        <span id="sc-toggle-slider" style="position:absolute;inset:0;border-radius:28px;background:<?php echo $enabled ? '#46b450' : '#ccc'; ?>;transition:background .2s;"></span>
                        <span style="position:absolute;top:3px;left:<?php echo $enabled ? '27px' : '3px'; ?>;width:22px;height:22px;background:#fff;border-radius:50%;transition:left .2s;pointer-events:none;" id="sc-toggle-knob"></span>
                    </div>
                    <div>
                        <strong id="sc-toggle-label"><?php echo $enabled ? 'Zichtbaar op atletenpagina\'s' : 'Verborgen op atletenpagina\'s'; ?></strong><br>
                        <span style="font-size:12px;color:#666;">De "Share profile card" knop is <?php echo $enabled ? 'aan' : 'uit'; ?>. Sla op om te wijzigen.</span>
                    </div>
                </label>
            </div>
            <div id="sc-toggle-wrap" style="display:none;"></div>

            <p>Sleep de elementen naar de juiste positie. Vergroot/verklein het fotovak via de hoeken. Sla op en gebruik de preview om het resultaat te zien.</p>

            <div id="sc-layout" style="display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap;">

                <!-- STAGE -->
                <div style="flex-shrink:0;">
                    <div id="sc-stage" style="position:relative;width:400px;height:500px;overflow:visible;border:2px solid #ccc;background:#0d2d63;">

                        <?php if ( $bg_url ) : ?>
                            <img id="sc-bg-img" src="<?php echo esc_url( $bg_url ); ?>"
                                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;z-index:0;border-radius:0;">
                        <?php else : ?>
                            <div id="sc-bg-placeholder" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;opacity:.4;font-size:13px;pointer-events:none;z-index:0;">Nog geen achtergrond gekozen</div>
                        <?php endif; ?>

                        <!-- Photo -->
                        <div class="sc-el" id="sc-el-photo" data-key="photo"
                             style="position:absolute;z-index:10;
                                    left:<?php echo $elements['photo']['x']; ?>%;
                                    top:<?php echo $elements['photo']['y']; ?>%;
                                    width:<?php echo $elements['photo']['w']; ?>%;
                                    height:<?php echo $elements['photo']['h']; ?>%;
                                    background:rgba(0,150,255,.2);
                                    border:2px dashed #0096ff;
                                    box-sizing:border-box;cursor:move;">
                            <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#0096ff;font-weight:700;font-size:12px;white-space:nowrap;text-shadow:0 1px 3px #000;pointer-events:none;">📷 Foto</span>
                        </div>

                        <!-- Name -->
                        <div class="sc-el" id="sc-el-name" data-key="name"
                             style="position:absolute;z-index:10;
                                    left:<?php echo $elements['name']['x']; ?>%;
                                    top:<?php echo $elements['name']['y']; ?>%;
                                    background:rgba(255,120,0,.25);
                                    border:2px dashed #ff7800;
                                    padding:4px 10px;cursor:move;white-space:nowrap;">
                            <span style="color:#ff7800;font-weight:700;font-size:11px;text-shadow:0 1px 3px #000;pointer-events:none;">👤 Naam zwemmer</span>
                        </div>

                        <!-- Flag -->
                        <div class="sc-el" id="sc-el-flag" data-key="flag"
                             style="position:absolute;z-index:10;
                                    left:<?php echo $elements['flag']['x']; ?>%;
                                    top:<?php echo $elements['flag']['y']; ?>%;
                                    width:<?php echo $elements['flag']['w']; ?>%;
                                    min-width:30px;
                                    background:rgba(0,200,100,.25);
                                    border:2px dashed #00c864;
                                    padding:4px 8px;cursor:move;white-space:nowrap;box-sizing:border-box;">
                            <span style="color:#00c864;font-weight:700;font-size:11px;text-shadow:0 1px 3px #000;pointer-events:none;">🏳 Vlag</span>
                        </div>

                        <!-- Logo -->
                        <div class="sc-el" id="sc-el-logo" data-key="logo"
                             style="position:absolute;z-index:10;
                                    left:<?php echo $elements['logo']['x']; ?>%;
                                    top:<?php echo $elements['logo']['y']; ?>%;
                                    width:<?php echo $elements['logo']['w']; ?>%;
                                    min-width:30px;
                                    background:rgba(255,220,0,.25);
                                    border:2px dashed #ffdc00;
                                    padding:4px 8px;cursor:move;white-space:nowrap;box-sizing:border-box;">
                            <?php if ( $logo_url ) : ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" style="width:100%;height:auto;display:block;pointer-events:none;opacity:.8;">
                            <?php else : ?>
                                <span style="color:#ffdc00;font-weight:700;font-size:11px;text-shadow:0 1px 3px #000;pointer-events:none;">🏅 Logo</span>
                            <?php endif; ?>
                        </div>

                        <!-- Events -->
                        <div class="sc-el" id="sc-el-events" data-key="events"
                             style="position:absolute;z-index:10;
                                    left:<?php echo $elements['events']['x']; ?>%;
                                    top:<?php echo $elements['events']['y']; ?>%;
                                    background:rgba(220,0,220,.2);
                                    border:2px dashed #dc00dc;
                                    padding:4px 10px;cursor:move;white-space:nowrap;">
                            <span style="color:#dc00dc;font-weight:700;font-size:11px;text-shadow:0 1px 3px #000;pointer-events:none;">🏊 100m Vrije slag &nbsp;200m Rugslag</span>
                        </div>

                    </div>
                    <p style="font-size:11px;color:#888;margin:6px 0 0;">Stage 400×500 px = schaal van 1080×1350 px (4:5)</p>
                </div>

                <!-- SETTINGS -->
                <div style="flex:1;min-width:280px;max-width:380px;">

                    <div class="sta-card" style="margin-bottom:16px;">
                        <h3 style="margin-top:0;">Achtergrondafbeelding</h3>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <?php if ( $bg_url ) : ?>
                                <img id="sc-bg-thumb" src="<?php echo esc_url( $bg_url ); ?>" style="width:64px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #ccc;">
                            <?php else : ?>
                                <div id="sc-bg-thumb" style="width:64px;height:80px;background:#eee;border-radius:4px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;text-align:center;">Geen</div>
                            <?php endif; ?>
                            <div>
                                <button type="button" id="sc-choose-bg" class="button">Afbeelding kiezen</button>
                                <p style="font-size:11px;color:#888;margin:4px 0 0;">Aanbevolen: 1080×1350 px (PNG/JPG)</p>
                            </div>
                        </div>
                        <input type="hidden" id="sc-bg-id" value="<?php echo esc_attr( $bg_id ); ?>">
                    </div>

                    <div class="sta-card" style="margin-bottom:16px;border-left:4px solid #ffdc00;">
                        <h4 style="margin:0 0 10px;color:#a07800;">🏅 Toernooilogo</h4>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <?php if ( $logo_url ) : ?>
                                <img id="sc-logo-thumb" src="<?php echo esc_url( $logo_url ); ?>" style="max-width:80px;max-height:60px;object-fit:contain;border-radius:4px;border:1px solid #ccc;background:#fff;padding:4px;">
                            <?php else : ?>
                                <div id="sc-logo-thumb" style="width:80px;height:60px;background:#eee;border-radius:4px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;">Geen</div>
                            <?php endif; ?>
                            <div>
                                <button type="button" id="sc-choose-logo" class="button">Logo kiezen</button>
                                <p style="font-size:11px;color:#888;margin:4px 0 0;">PNG met transparantie aanbevolen</p>
                            </div>
                        </div>
                        <input type="hidden" id="sc-logo-id" value="<?php echo esc_attr( $logo_id ); ?>">
                        <br>
                        <label>Breedte (% van canvas)<br>
                            <input type="number" value="<?php echo floatval( $elements['logo']['w'] ?? 25 ); ?>" min="5" max="80" step="0.5" style="width:80px;" class="sc-setting" data-key="logo" data-prop="w">
                        </label>
                    </div>

                    <div class="sta-card" style="margin-bottom:16px;border-left:4px solid #e67e22;">
                        <h4 style="margin:0 0 10px;color:#e67e22;">🖼 Extra afbeeldingen</h4>
                        <div id="sc-extras-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px;"></div>
                        <button type="button" id="sc-add-extra" class="button">+ Afbeelding toevoegen</button>
                    </div>

                    <div class="sta-card" style="margin-bottom:12px;border-left:4px solid #ff7800;">
                        <h4 style="margin:0 0 10px;color:#ff7800;">👤 Naam</h4>
                        <label>Lettergrootte (px op 1080 canvas)<br>
                            <input type="number" value="<?php echo intval( $elements['name']['size'] ?? 72 ); ?>" min="20" max="200" style="width:80px;" class="sc-setting" data-key="name" data-prop="size">
                        </label>
                        &nbsp;
                        <label>Kleur<br>
                            <input type="color" value="<?php echo esc_attr( $elements['name']['color'] ?? '#ffffff' ); ?>" class="sc-setting" data-key="name" data-prop="color">
                        </label>
                    </div>

                    <div class="sta-card" style="margin-bottom:12px;border-left:4px solid #dc00dc;">
                        <h4 style="margin:0 0 10px;color:#dc00dc;">🏊 Afstanden</h4>
                        <label>Lettergrootte (px)<br>
                            <input type="number" value="<?php echo intval( $elements['events']['size'] ?? 40 ); ?>" min="14" max="120" style="width:80px;" class="sc-setting" data-key="events" data-prop="size">
                        </label>
                        &nbsp;
                        <label>Tekstkleur<br>
                            <input type="color" value="<?php echo esc_attr( $elements['events']['color'] ?? '#ffffff' ); ?>" class="sc-setting" data-key="events" data-prop="color">
                        </label>
                        <br><br>
                        <label>Achtergrondkleur vakjes<br>
                            <input type="color" value="<?php echo esc_attr( $elements['events']['pill_color'] ?? '#13315c' ); ?>" class="sc-setting" data-key="events" data-prop="pill_color">
                        </label>
                        <br><br>
                        <label>Vorm vakjes<br>
                            <select class="sc-setting" data-key="events" data-prop="pill_shape" style="margin-top:4px;">
                                <option value="rounded" <?php selected( $elements['events']['pill_shape'] ?? 'rounded', 'rounded' ); ?>>Afgerond</option>
                                <option value="rectangular" <?php selected( $elements['events']['pill_shape'] ?? 'rounded', 'rectangular' ); ?>>Rechthoekig</option>
                            </select>
                        </label>
                        <br><br>
                        <label>Regelafstand (px)<br>
                            <input type="number" value="<?php echo intval( $elements['events']['line_height'] ?? 64 ); ?>" min="20" max="200" style="width:80px;" class="sc-setting" data-key="events" data-prop="line_height">
                        </label>
                    </div>

                    <div class="sta-card" style="margin-bottom:16px;border-left:4px solid #00c864;">
                        <h4 style="margin:0 0 10px;color:#00c864;">🏳 Vlag breedte (% van canvas)</h4>
                        <input type="number" id="sc-flag-w" value="<?php echo floatval( $elements['flag']['w'] ?? 12 ); ?>" min="2" max="40" step="0.5" style="width:80px;" class="sc-setting" data-key="flag" data-prop="w">
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                        <button type="button" id="sc-save" class="button button-primary" style="font-size:14px;padding:6px 18px;">💾 Opslaan</button>
                        <span id="sc-save-status" style="font-size:13px;color:#46b450;display:none;"></span>
                    </div>

                    <div class="sta-card" style="background:#f8f8f8;">
                        <h4 style="margin:0 0 10px;">👁 Preview</h4>
                        <select id="sc-preview-athlete" style="width:100%;margin-bottom:10px;">
                            <option value="">— Kies een atleet —</option>
                            <?php foreach ( $preview_athletes as $a ) : ?>
                                <option value="<?php echo $a->ID; ?>"><?php echo esc_html( $a->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="sc-preview" class="button">Genereer preview</button>
                        <div id="sc-preview-result" style="margin-top:12px;"></div>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }
}
