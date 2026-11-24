<?php

class STA_Admin_FotoIntegratie {

    static function register() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // Cron
        add_filter( 'cron_schedules',        [ __CLASS__, 'add_cron_interval' ] );
        add_action( 'sta_tournament_photo_scan', [ __CLASS__, 'run_scan' ] );
        add_action( 'update_option_sta_photo_scan_interval', [ __CLASS__, 'reschedule_cron' ], 10, 0 );

        if ( ! wp_next_scheduled( 'sta_tournament_photo_scan' ) ) {
            self::schedule_cron();
        }

        // AJAX
        add_action( 'wp_ajax_sta_scan_tournament_photos',    [ __CLASS__, 'ajax_scan_now' ] );
        add_action( 'wp_ajax_sta_flickr_search_athlete',     [ __CLASS__, 'ajax_search_athlete' ] );
        add_action( 'wp_ajax_sta_save_tournament_photos',    [ __CLASS__, 'ajax_save_tournament_photos' ] );
    }

    static function add_menu() {
        add_submenu_page(
            'swimming-trials-ai',
            'Foto integratie',
            'Foto integratie',
            'manage_options',
            'swimming-trials-fotointegratie',
            [ __CLASS__, 'render' ]
        );
    }

    static function register_settings() {
        foreach ( [
            'sta_flickr_photographer'  => 'sanitize_text_field',
            'sta_tournament_tag'       => 'sanitize_text_field',
            'sta_photo_scan_interval'  => 'absint',
        ] as $key => $cb ) {
            register_setting( 'sta_foto_settings', $key, [ 'sanitize_callback' => $cb ] );
        }

        // Fallback photos also editable here
    }

    static function add_cron_interval( $schedules ) {
        $hours = max( 1, intval( get_option( 'sta_photo_scan_interval', 4 ) ) );
        $schedules['sta_photo_scan'] = [
            'interval' => $hours * 3600,
            'display'  => "Elke {$hours} uur (toernooi foto scan)",
        ];
        return $schedules;
    }

    static function schedule_cron() {
        if ( ! wp_next_scheduled( 'sta_tournament_photo_scan' ) ) {
            wp_schedule_event( time() + 60, 'sta_photo_scan', 'sta_tournament_photo_scan' );
        }
    }

    static function reschedule_cron() {
        wp_clear_scheduled_hook( 'sta_tournament_photo_scan' );
        self::schedule_cron();
    }

    // ------------------------------------------------------------------ //
    // Scan: zoek toernooifoto's voor alle atleten                          //
    // ------------------------------------------------------------------ //
    static function run_scan() {
        $tag          = get_option( 'sta_tournament_tag', '' );
        $photographer = get_option( 'sta_flickr_photographer', '' );
        if ( ! $tag || ! $photographer ) return;

        // Bouw atleet-roster op voor snelle naam-matching
        $athletes = get_posts( [
            'post_type'   => 'athlete',
            'post_status' => 'publish',
            'numberposts' => -1,
        ] );

        $normalize = static function( string $s ): string {
            $s   = mb_strtolower( $s, 'UTF-8' );
            $map = ['ä'=>'a','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','å'=>'a','æ'=>'ae','ö'=>'o','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ø'=>'o','œ'=>'oe','ü'=>'u','ú'=>'u','ù'=>'u','û'=>'u','ë'=>'e','é'=>'e','è'=>'e','ê'=>'e','ï'=>'i','í'=>'i','ì'=>'i','î'=>'i','ñ'=>'n','ß'=>'ss','ç'=>'c','ý'=>'y','ÿ'=>'y'];
            return strtr( $s, $map );
        };

        $roster = [];
        foreach ( $athletes as $a ) {
            $parts = array_values( array_filter( explode( ' ', $a->post_title ) ) );
            $roster[] = [
                'id'        => $a->ID,
                'first'     => $normalize( $parts[0] ?? '' ),
                'last'      => $normalize( end( $parts ) ),
                'condensed' => $normalize( str_replace( ' ', '', $a->post_title ) ),
                'full'      => $normalize( $a->post_title ),
            ];
        }

        $flickr        = new STA_Flickr_Client();
        $last_seen_id  = get_option( 'sta_tournament_last_photo_id', '' );
        $newest_id     = '';
        $assignments   = []; // post_id => [ photo, ... ]
        $stop          = false;
        $page          = 1;

        do {
            $result = $flickr->search_by_tag( $tag, $page, 100 );
            if ( is_wp_error( $result ) || empty( $result['photos'] ) ) break;

            foreach ( $result['photos'] as $photo ) {
                // Stop zodra we een al-geziene foto tegenkomen
                if ( $last_seen_id && $photo['flickr_id'] === $last_seen_id ) {
                    $stop = true;
                    break;
                }
                // Eerste foto van de hele batch = meest recente
                if ( ! $newest_id ) $newest_id = $photo['flickr_id'];

                // Match foto aan atleten via titel + tags
                $haystack   = $normalize( $photo['title'] . ' ' . $photo['tags_raw'] );
                $condensed  = str_replace( [ ' ', '-', '_' ], '', $haystack );

                foreach ( $roster as $athlete ) {
                    if (
                        str_contains( $haystack, $athlete['full'] )
                        || str_contains( $condensed, $athlete['condensed'] )
                    ) {
                        $assignments[ $athlete['id'] ][] = $photo;
                    }
                }
            }

            $page++;
        } while ( ! $stop && $page <= ( $result['pages'] ?? 1 ) );

        // Sla foto's op per atleet
        foreach ( $assignments as $post_id => $photos ) {
            self::store_photos( $post_id, $photos );
        }

        // Onthoud meest recente foto-ID voor volgende scan
        if ( $newest_id ) {
            update_option( 'sta_tournament_last_photo_id', $newest_id );
        }

        update_option( 'sta_last_photo_scan', time() );
    }


    // Sla maximaal 6 foto's per atleet op; verwijder oudste bij overflow.
    static function store_photos( int $post_id, array $new_photos ): void {
        $existing    = get_post_meta( $post_id, 'sta_tournament_photos', true ) ?: [];
        $existing_ids = array_column( $existing, 'flickr_id' );

        $to_add = [];
        foreach ( $new_photos as $p ) {
            if ( ! in_array( $p['flickr_id'], $existing_ids, true ) ) {
                $to_add[] = $p;
            }
        }

        if ( empty( $to_add ) ) return; // niets nieuws

        // Voeg toe vooraan (nieuwst eerst), trim tot 6
        $merged = array_slice( array_merge( $to_add, $existing ), 0, 6 );
        update_post_meta( $post_id, 'sta_tournament_photos', $merged );
        update_post_meta( $post_id, 'sta_tournament_photos_updated', time() );
    }

    static function ajax_scan_now() {
        check_ajax_referer( 'sta_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang.' );

        self::run_scan();
        $count = self::count_athletes_with_photos();
        wp_send_json_success( [
            'time'  => wp_date( 'H:i:s' ),
            'count' => $count,
        ] );
    }

    static function ajax_search_athlete(): void {
        check_ajax_referer( 'sta_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Geen toegang.' );

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( 'Geen post_id.' );

        $tag = get_option( 'sta_tournament_tag', '' );
        if ( ! $tag ) wp_send_json_error( 'Geen toernooi-tag ingesteld.' );

        $name = get_the_title( $post_id );
        if ( ! $name ) wp_send_json_error( 'Atleet niet gevonden.' );

        $normalize = fn( $s ) => mb_strtolower( strtr( $s, ['ä'=>'a','á'=>'a','à'=>'a','ö'=>'o','ó'=>'o','ò'=>'o','ü'=>'u','ú'=>'u','ë'=>'e','é'=>'e','è'=>'e','ï'=>'i','í'=>'i','ñ'=>'n','ß'=>'ss','ç'=>'c'] ), 'UTF-8' );
        $norm_full      = $normalize( $name );
        $norm_condensed = str_replace( ' ', '', $norm_full );

        $flickr  = new STA_Flickr_Client();
        $matched = [];
        $page    = 1;
        do {
            $result = $flickr->search_by_tag( $tag, $page, 100 );
            if ( is_wp_error( $result ) || empty( $result['photos'] ) ) break;
            foreach ( $result['photos'] as $photo ) {
                $haystack  = $normalize( $photo['title'] . ' ' . $photo['tags_raw'] );
                $condensed = str_replace( [' ','-','_'], '', $haystack );
                if ( str_contains( $haystack, $norm_full ) || str_contains( $condensed, $norm_condensed ) ) {
                    $matched[] = [
                        'flickr_id' => $photo['flickr_id'],
                        'thumb'     => $photo['thumb'],
                        'page'      => $photo['page'] ?? '',
                        'title'     => $photo['title'],
                        'tags_raw'  => $photo['tags_raw'],
                    ];
                    if ( count( $matched ) >= 12 ) break 2;
                }
            }
            $page++;
        } while ( $page <= ( $result['pages'] ?? 1 ) );

        wp_send_json_success( [ 'photos' => $matched, 'name' => $name ] );
    }

    static function ajax_save_tournament_photos(): void {
        check_ajax_referer( 'sta_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Geen toegang.' );

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $ids_raw = $_POST['flickr_ids'] ?? '';
        if ( ! $post_id || ! $ids_raw ) wp_send_json_error( 'Ontbrekende parameters.' );

        $selected_ids = array_filter( array_map( 'trim', explode( ',', $ids_raw ) ) );
        if ( empty( $selected_ids ) ) wp_send_json_error( 'Geen foto\'s geselecteerd.' );

        // Re-fetch photo data from Flickr for the selected IDs
        $tag = get_option( 'sta_tournament_tag', '' );
        $name = get_the_title( $post_id );
        $normalize = fn( $s ) => mb_strtolower( strtr( $s, ['ä'=>'a','á'=>'a','à'=>'a','ö'=>'o','ó'=>'o','ò'=>'o','ü'=>'u','ú'=>'u','ë'=>'e','é'=>'e','è'=>'e','ï'=>'i','í'=>'i','ñ'=>'n','ß'=>'ss','ç'=>'c'] ), 'UTF-8' );
        $norm_full      = $normalize( $name );
        $norm_condensed = str_replace( ' ', '', $norm_full );

        $flickr  = new STA_Flickr_Client();
        $found   = [];
        $page    = 1;
        do {
            $result = $flickr->search_by_tag( $tag, $page, 100 );
            if ( is_wp_error( $result ) || empty( $result['photos'] ) ) break;
            foreach ( $result['photos'] as $photo ) {
                if ( ! in_array( $photo['flickr_id'], $selected_ids, true ) ) continue;
                $found[] = $photo;
                if ( count( $found ) >= count( $selected_ids ) ) break 2;
            }
            $page++;
        } while ( $page <= ( $result['pages'] ?? 1 ) );

        if ( ! empty( $found ) ) {
            update_post_meta( $post_id, 'sta_tournament_photos', array_slice( $found, 0, 6 ) );
            update_post_meta( $post_id, 'sta_tournament_photos_updated', time() );
        }
        wp_send_json_success( [ 'saved' => count( $found ) ] );
    }

    static function count_athletes_with_photos(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'sta_tournament_photos'"
        );
    }

    // ------------------------------------------------------------------ //
    // Enqueue                                                              //
    // ------------------------------------------------------------------ //
    static function enqueue( $hook ) {
        if ( strpos( $hook, 'swimming-trials-fotointegratie' ) === false ) return;
        wp_enqueue_media();
        add_action( 'admin_footer', [ __CLASS__, 'footer_js' ] );
    }

    static function footer_js() {
        $nonce    = wp_create_nonce( 'sta_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );

        // Fallback foto URLs doorgeven aan JS
        $fb_m_id    = intval( get_option( 'sta_fallback_photo_m', 0 ) );
        $fb_f_id    = intval( get_option( 'sta_fallback_photo_f', 0 ) );
        $fb_thumbs  = [
            'm' => $fb_m_id ? wp_get_attachment_image_url( $fb_m_id, 'thumbnail' ) : '',
            'f' => $fb_f_id ? wp_get_attachment_image_url( $fb_f_id, 'thumbnail' ) : '',
        ];
        $fb_urls    = [
            'm' => $fb_m_id ? wp_get_attachment_image_url( $fb_m_id, 'large' ) : '',
            'f' => $fb_f_id ? wp_get_attachment_image_url( $fb_f_id, 'large' ) : '',
        ];
        ?>
        <script>
        jQuery(function($) {
            // Media upload voor fallback foto's
            $(document).on('click', '.sta-media-upload', function() {
                var target = $(this).data('target');
                var frame = wp.media({ title: 'Kies fallback foto', button: { text: 'Selecteer' }, multiple: false });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#' + target).val(att.id);
                    $('#' + target + '_preview').html('<img src="' + att.sizes.thumbnail.url + '" style="width:100%;height:100%;object-fit:cover;">');
                });
                frame.open();
            });
            $(document).on('click', '.sta-media-remove', function() {
                var target = $(this).data('target');
                $('#' + target).val(0);
                $('#' + target + '_preview').html('<span style="color:#999;font-size:11px;">Geen foto</span>');
            });
        });

        // Nu scannen
        document.getElementById('sta-scan-now-btn')?.addEventListener('click', async function() {
            var btn = this;
            var status = document.getElementById('sta-scan-status');
            btn.disabled = true;
            status.textContent = 'Bezig met scannen...';
            status.style.color = '#666';

            var fd = new FormData();
            fd.append('action', 'sta_scan_tournament_photos');
            fd.append('nonce', '<?php echo esc_js( $nonce ); ?>');

            try {
                var resp = await fetch('<?php echo esc_js( $ajax_url ); ?>', { method: 'POST', body: fd });
                var data = await resp.json();
                if (data.success) {
                    status.textContent = 'Klaar om ' + data.data.time + ' — ' + data.data.count + ' atleten hebben foto\'s.';
                    status.style.color = '#46b450';
                } else {
                    status.textContent = 'Fout: ' + (data.data || 'onbekend');
                    status.style.color = '#c00';
                }
            } catch(e) {
                status.textContent = 'Verbindingsfout.';
                status.style.color = '#c00';
            }
            btn.disabled = false;
        });

        // Foto zoeker
        (function () {
            var nonce   = '<?php echo esc_js( $nonce ); ?>';
            var ajaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
            var fbThumbs = <?php echo wp_json_encode( $fb_thumbs ); ?>;
            var fbUrls   = <?php echo wp_json_encode( $fb_urls ); ?>;
            var lastCb   = null;
            var searching = false;

            function updateCount() {
                var n = document.querySelectorAll('.fb-cb:checked').length;
                var el = document.getElementById('fb-count');
                var elBottom = document.querySelector('.fb-count-bottom');
                var btnBottom = document.querySelector('.fb-find-bottom-btn');
                var wrap = document.getElementById('fb-find-bottom');
                var btn = document.getElementById('fb-find-btn');
                if (el) el.textContent = n;
                if (btn) btn.disabled = n === 0;
                var all = document.querySelectorAll('.fb-cb');
                var sa = document.getElementById('fb-select-all');
                if (sa) sa.checked = n === all.length && all.length > 0;
                if (elBottom) elBottom.textContent = n;
                if (btnBottom) btnBottom.disabled = n === 0;
                if (wrap) wrap.style.display = n > 0 ? '' : 'none';
            }

            document.querySelectorAll('.fb-cb').forEach(function(cb) {
                cb.addEventListener('click', function(e) {
                    if (e.shiftKey && lastCb && lastCb !== cb) {
                        var all = Array.from(document.querySelectorAll('.fb-cb'));
                        var a = all.indexOf(lastCb), b = all.indexOf(cb);
                        var from = Math.min(a, b), to = Math.max(a, b);
                        for (var i = from; i <= to; i++) all[i].checked = cb.checked;
                    }
                    lastCb = cb;
                    updateCount();
                });
            });

            document.getElementById('fb-select-all')?.addEventListener('change', function() {
                document.querySelectorAll('.fb-cb').forEach(function(cb) { cb.checked = this.checked; }, this);
                updateCount();
            });

            // Globale hover-preview popup
            var _hoverEl = (function() {
                var el = document.createElement('div');
                el.style.cssText = 'position:fixed;z-index:99999;pointer-events:none;display:none;background:#000;border-radius:6px;box-shadow:0 8px 32px rgba(0,0,0,.55);padding:4px;max-width:420px;max-height:420px;';
                var img = document.createElement('img');
                img.style.cssText = 'display:block;max-width:412px;max-height:412px;object-fit:contain;border-radius:3px;';
                el.appendChild(img);
                document.body.appendChild(el);
                return { wrap: el, img: img };
            })();

            function showHoverPreview(fullUrl, rect) {
                _hoverEl.img.src = fullUrl;
                _hoverEl.wrap.style.display = 'block';
                var pw = 420;
                var left = rect.right + 10;
                if (left + pw > window.innerWidth) left = rect.left - pw - 10;
                var top = rect.top - 10;
                if (top + 420 > window.innerHeight) top = window.innerHeight - 430;
                _hoverEl.wrap.style.left = Math.max(8, left) + 'px';
                _hoverEl.wrap.style.top  = Math.max(8, top)  + 'px';
            }
            function hideHoverPreview() { _hoverEl.wrap.style.display = 'none'; }

            // Focuspunt-overlay
            var _focusOverlay = (function() {
                var overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.75);display:none;align-items:center;justify-content:center;flex-direction:column;gap:12px;';
                overlay.innerHTML =
                    '<p style="color:#fff;margin:0;font-size:13px;">Klik op de foto om het focuspunt in te stellen.' +
                    '<button id="_fo-cancel" style="margin-left:10px;padding:2px 10px;cursor:pointer;">Annuleren</button></p>' +
                    '<div id="_fo-wrap" style="position:relative;cursor:crosshair;">' +
                    '<img id="_fo-img" style="max-width:80vw;max-height:75vh;display:block;border-radius:4px;">' +
                    '<div id="_fo-dot" style="position:absolute;width:22px;height:22px;border-radius:50%;background:rgba(255,60,60,.9);border:3px solid #fff;transform:translate(-50%,-50%);pointer-events:none;box-shadow:0 2px 8px rgba(0,0,0,.6);"></div>' +
                    '</div>' +
                    '<button id="_fo-save" style="padding:5px 18px;cursor:pointer;background:#0073aa;color:#fff;border:none;border-radius:3px;font-size:13px;">Opslaan</button>';
                document.body.appendChild(overlay);

                var state = { postId: 0, x: 50, y: 50, nonce: '' };
                var foImg  = overlay.querySelector('#_fo-img');
                var foDot  = overlay.querySelector('#_fo-dot');
                var foWrap = overlay.querySelector('#_fo-wrap');

                foWrap.addEventListener('click', function(e) {
                    var r = foImg.getBoundingClientRect();
                    state.x = Math.round(Math.max(0, Math.min(100, (e.clientX - r.left) / r.width  * 100)));
                    state.y = Math.round(Math.max(0, Math.min(100, (e.clientY - r.top)  / r.height * 100)));
                    foDot.style.left = state.x + '%';
                    foDot.style.top  = state.y + '%';
                });
                overlay.querySelector('#_fo-cancel').addEventListener('click', function() { overlay.style.display = 'none'; });
                overlay.querySelector('#_fo-save').addEventListener('click', async function() {
                    var btn = this; btn.disabled = true; btn.textContent = 'Opslaan…';
                    var fd = new FormData();
                    fd.append('action','sta_save_focus'); fd.append('nonce', state.nonce);
                    fd.append('post_id', state.postId); fd.append('x', state.x); fd.append('y', state.y);
                    await fetch(ajaxUrl, {method:'POST', body:fd});
                    btn.textContent = '✓ Opgeslagen';
                    setTimeout(function() { overlay.style.display = 'none'; btn.disabled = false; btn.textContent = 'Opslaan'; }, 700);
                });
                return {
                    open: function(postId, fullUrl, fx, fy, n) {
                        state.postId = postId; state.x = fx||50; state.y = fy||50; state.nonce = n;
                        foImg.src = fullUrl;
                        foDot.style.left = state.x + '%'; foDot.style.top = state.y + '%';
                        overlay.style.display = 'flex';
                        overlay.querySelector('#_fo-save').textContent = 'Opslaan';
                        overlay.querySelector('#_fo-save').disabled = false;
                    }
                };
            })();

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('._fo-open-btn');
                if (!btn) return;
                _focusOverlay.open(btn.dataset.postId, btn.dataset.url,
                    parseFloat(btn.dataset.x||50), parseFloat(btn.dataset.y||50), nonce);
            });

            // Maak een klikbare thumbnail aan en koppel de opslaan-actie
            function makeThumb(postId, name, photoUrl, thumbUrl, title, isFallback) {
                var wrap = document.createElement('div');
                wrap.style.cssText = 'position:relative;display:inline-flex;flex-direction:column;align-items:center;gap:4px;';
                var img = document.createElement('img');
                img.src = thumbUrl; img.title = title || '';
                img.style.cssText = 'width:90px;height:90px;object-fit:cover;border-radius:5px;cursor:pointer;border:3px solid '+(isFallback?'#dba617':'transparent')+';transition:border-color .15s,transform .15s;';
                if (isFallback) {
                    var lbl = document.createElement('span');
                    lbl.textContent = 'Fallback';
                    lbl.style.cssText = 'font-size:10px;color:#888;';
                    wrap.appendChild(img);
                    wrap.appendChild(lbl);
                } else {
                    wrap.appendChild(img);
                }
                img.addEventListener('mouseenter', function() {
                    if (!searching) { img.style.borderColor=isFallback?'#c9930e':'#0073aa'; img.style.transform='scale(1.05)'; }
                    showHoverPreview(photoUrl, img.getBoundingClientRect());
                });
                img.addEventListener('mouseleave', function() {
                    img.style.borderColor=isFallback?'#dba617':'transparent'; img.style.transform='';
                    hideHoverPreview();
                });
                img.addEventListener('click', async function() {
                    if (searching || img.dataset.busy) return;
                    img.dataset.busy = '1'; img.style.opacity = '.5';
                    hideHoverPreview();
                    var fd2 = new FormData();
                    fd2.append('action','sta_switch_photo'); fd2.append('nonce',nonce); fd2.append('post_id',postId); fd2.append('photo_url',photoUrl);
                    if (isFallback) fd2.append('is_fallback','1');
                    try {
                        var r2 = await fetch(ajaxUrl,{method:'POST',body:fd2}), d2 = await r2.json();
                        if (d2.success) {
                            var row = document.getElementById('fb-row-'+postId);
                            if (row) {
                                row.style.borderLeftColor='#46b450';
                                var fx = d2.data.focus_x||50, fy = d2.data.focus_y||50;
                                row.innerHTML =
                                    '<div style="display:flex;align-items:center;gap:10px;">' +
                                    '<img src="'+d2.data.thumb+'" style="width:54px;height:54px;object-fit:cover;border-radius:4px;">' +
                                    '<div><strong>'+name+'</strong><br>' +
                                    '<span style="color:#46b450;font-size:13px;">Foto opgeslagen</span> ' +
                                    '<button class="_fo-open-btn button button-small" style="margin-left:6px;font-size:11px;" ' +
                                    'data-post-id="'+postId+'" data-url="'+photoUrl+'" data-x="'+fx+'" data-y="'+fy+'">' +
                                    'Focuspunt instellen</button></div></div>';
                            }
                            var li=document.getElementById('fb-li-'+postId); if(li) li.style.opacity='.4';
                        } else { img.style.opacity='1'; delete img.dataset.busy; }
                    } catch(e) { img.style.opacity='1'; delete img.dataset.busy; }
                });
                return wrap;
            }

            // Vul een rij met kandidaten + optionele fallback + alternatieve-naam-zoeker
            async function fillRow(row, postId, name, gender, maxPhotos, searchName) {
                var statusEl = row.querySelector('.fb-status');
                var thumbsEl = row.querySelector('.fb-thumbs');
                var altWrap  = row.querySelector('.fb-alt-wrap');
                thumbsEl.innerHTML = '';
                // Verwijder eerder toegevoegde "meer laden" knop
                row.querySelectorAll('.fb-more-btn-wrap').forEach(function(el){ el.remove(); });
                statusEl.textContent = '— zoeken...';
                statusEl.style.color = '#999';

                var fd = new FormData();
                fd.append('action', 'sta_find_photos'); fd.append('nonce', nonce);
                fd.append('post_id', postId); fd.append('max', maxPhotos || 5);
                if (searchName) fd.append('alt_name', searchName);
                var candidates = [];
                try {
                    var resp = await fetch(ajaxUrl, { method: 'POST', body: fd });
                    var data = await resp.json();
                    if (data.success) candidates = data.data.candidates || [];
                } catch(e) { statusEl.textContent = '— fout'; statusEl.style.color = '#c00'; return; }

                thumbsEl.style.pointerEvents = 'none';

                if (candidates.length) {
                    row.style.borderLeftColor = '#00a0d2';
                    candidates.forEach(function(c) {
                        thumbsEl.appendChild(makeThumb(postId, name, c.url, c.thumb, c.title, false));
                    });
                } else {
                    row.style.borderLeftColor = '#c00';
                }

                // Altijd fallback foto toevoegen als die bestaat
                var fbThumb = fbThumbs[gender] || fbThumbs['m'];
                var fbUrl   = fbUrls[gender]   || fbUrls['m'];
                if (fbUrl) {
                    thumbsEl.appendChild(makeThumb(postId, name, fbUrl, fbThumb, 'Fallback foto', true));
                }

                // "Meer foto's laden" knop — altijd tonen na de fallback
                var moreBtnWrap = document.createElement('div');
                moreBtnWrap.className = 'fb-more-btn-wrap';
                moreBtnWrap.style.cssText = 'display:inline-flex;align-items:flex-end;padding-bottom:4px;';
                var moreBtn = document.createElement('button');
                moreBtn.className = 'button';
                moreBtn.textContent = 'Meer foto\'s laden';
                moreBtn.style.cssText = 'font-size:12px;height:28px;line-height:26px;';
                moreBtnWrap.appendChild(moreBtn);
                thumbsEl.appendChild(moreBtnWrap);

                var currentPage = 1;
                moreBtn.addEventListener('click', async function() {
                    moreBtn.disabled = true;
                    moreBtn.textContent = 'Laden…';
                    currentPage++;
                    var fd3 = new FormData();
                    fd3.append('action', 'sta_find_photos'); fd3.append('nonce', nonce);
                    fd3.append('post_id', postId); fd3.append('max', 20); fd3.append('page', currentPage);
                    if (searchName) fd3.append('alt_name', searchName);
                    try {
                        var r3 = await fetch(ajaxUrl, { method: 'POST', body: fd3 });
                        var d3 = await r3.json();
                        var extra = (d3.success && d3.data.candidates) ? d3.data.candidates : [];
                        // Voeg nieuwe foto's in vóór de moreBtnWrap
                        extra.forEach(function(c) {
                            thumbsEl.insertBefore(makeThumb(postId, name, c.url, c.thumb, c.title, false), moreBtnWrap);
                        });
                        if (extra.length < 20) {
                            moreBtn.textContent = extra.length ? 'Meer foto\'s laden' : 'Geen meer gevonden';
                            moreBtn.disabled = extra.length < 20;
                        } else {
                            moreBtn.textContent = 'Meer foto\'s laden';
                            moreBtn.disabled = false;
                        }
                    } catch(e) { moreBtn.textContent = 'Fout — probeer opnieuw'; moreBtn.disabled = false; }
                    thumbsEl.style.pointerEvents = '';
                });

                if (!candidates.length) {
                    statusEl.textContent = '— geen foto gevonden';
                    statusEl.style.color = '#c00';
                    if (!altWrap) {
                        var aw = document.createElement('div');
                        aw.className = 'fb-alt-wrap';
                        aw.style.cssText = 'margin-top:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;';
                        aw.innerHTML = '<input type="text" class="fb-alt-input" placeholder="Alternatieve naam…" style="padding:4px 8px;border:1px solid #ccc;border-radius:3px;font-size:13px;width:200px;">'
                            + '<button class="button fb-alt-btn" style="font-size:13px;">Opnieuw zoeken</button>';
                        row.appendChild(aw);
                        aw.querySelector('.fb-alt-btn').addEventListener('click', function() {
                            var altName = aw.querySelector('.fb-alt-input').value.trim();
                            if (!altName) return;
                            fillRow(row, postId, name, gender, maxPhotos, altName);
                        });
                        aw.querySelector('.fb-alt-input').addEventListener('keydown', function(e) {
                            if (e.key === 'Enter') aw.querySelector('.fb-alt-btn').click();
                        });
                    }
                } else {
                    statusEl.textContent = '— klik een foto om toe te passen';
                    statusEl.style.color = '#666';
                }
                thumbsEl.style.pointerEvents = '';
            }

            document.getElementById('fb-find-btn')?.addEventListener('click', async function() {
                var selected = Array.from(document.querySelectorAll('.fb-cb:checked'));
                if (!selected.length) return;
                var btn = this;
                btn.disabled = true;
                searching = true;
                var total = selected.length, current = 0;
                var results = document.getElementById('fb-results');
                results.innerHTML = '<div id="fb-progress-wrap" style="margin-bottom:16px;">'
                    + '<div style="display:flex;justify-content:space-between;font-size:13px;color:#555;margin-bottom:5px;">'
                    + '<span>Foto\'s zoeken...</span><span id="fb-progress-label">0 / ' + total + '</span></div>'
                    + '<div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;">'
                    + '<div id="fb-progress-bar" style="height:8px;background:#0073aa;border-radius:4px;width:0%;transition:width .3s;"></div>'
                    + '</div></div>';
                function setProgress(n) {
                    var lbl = document.getElementById('fb-progress-label');
                    var bar = document.getElementById('fb-progress-bar');
                    if (lbl) lbl.textContent = n + ' / ' + total;
                    if (bar) bar.style.width = Math.round(n / total * 100) + '%';
                }
                for (const cb of selected) {
                    const postId = cb.dataset.id, name = cb.dataset.name, gender = cb.dataset.gender || 'm', maxP = cb.dataset.max || '5';
                    const row = document.createElement('div');
                    row.id = 'fb-row-' + postId;
                    row.style.cssText = 'margin-bottom:12px;padding:12px 14px;background:#f6f7f7;border-radius:6px;border-left:3px solid #ddd;';
                    row.innerHTML = '<strong>' + name + '</strong><span class="fb-status" style="color:#999;font-size:13px;margin-left:8px;"></span>'
                        + '<div class="fb-thumbs" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;"></div>';
                    results.appendChild(row);
                    await fillRow(row, postId, name, gender, maxP, null);
                    setProgress(++current);
                }
                searching = false;
                var pw = document.getElementById('fb-progress-wrap'); if (pw) pw.remove();
                var hasCandidates = results.querySelector('.fb-thumbs img');
                if (hasCandidates) {
                    var acceptBtn = document.createElement('button');
                    acceptBtn.className = 'button button-secondary';
                    acceptBtn.style.cssText = 'margin-bottom:16px;';
                    acceptBtn.textContent = 'Accepteer eerste foto voor alle';
                    acceptBtn.addEventListener('click', async function() {
                        acceptBtn.disabled = true; acceptBtn.textContent = 'Bezig...';
                        var firstImgs = results.querySelectorAll('.fb-thumbs div:first-child img');
                        for (var fi of firstImgs) { fi.click(); await new Promise(function(r){ setTimeout(r, 200); }); }
                        acceptBtn.remove();
                    });
                    results.insertBefore(acceptBtn, results.firstChild);
                }
                btn.disabled = false;
                btn.innerHTML = "Foto's zoeken (<span id='fb-count'>" + document.querySelectorAll('.fb-cb:checked').length + "</span> geselecteerd)";
            });
            document.querySelector('.fb-find-bottom-btn')?.addEventListener('click', function() {
                document.getElementById('fb-find-btn')?.click();
                window.scrollTo({ top: document.getElementById('fb-results').offsetTop - 80, behavior: 'smooth' });
            });
        })();
        </script>
        <?php
    }

    // ------------------------------------------------------------------ //
    // Pagina render                                                        //
    // ------------------------------------------------------------------ //
    static function render() {
        $photographer   = get_option( 'sta_flickr_photographer', '' );
        $tag            = get_option( 'sta_tournament_tag', '' );
        $interval       = intval( get_option( 'sta_photo_scan_interval', 4 ) );
        $last_scan      = get_option( 'sta_last_photo_scan', 0 );
        $next_scan      = wp_next_scheduled( 'sta_tournament_photo_scan' );
        $athletes_count = self::count_athletes_with_photos();

        $fb_ids = array_filter( [
            'm' => intval( get_option( 'sta_fallback_photo_m', 0 ) ),
            'f' => intval( get_option( 'sta_fallback_photo_f', 0 ) ),
        ] );
        $all_fb_ids = array_values( array_unique( $fb_ids ) );

        // Atleten met fallback foto — drie detectiemethoden:
        // 1. _thumbnail_id = huidig fallback attachment-ID (via verrijking)
        // 2. sta_flickr_photo_url = URL van fallback-attachment (via foto-kiezer, sideload per atleet)
        // 3. sta_photo_is_fallback = 1 (expliciet gemarkeerd, toekomst)
        global $wpdb;
        $fb_m_url       = isset( $fb_ids['m'] ) ? wp_get_attachment_url( $fb_ids['m'] ) : '';
        $fb_f_url       = isset( $fb_ids['f'] ) ? wp_get_attachment_url( $fb_ids['f'] ) : '';
        $fb_m_url_large = isset( $fb_ids['m'] ) ? wp_get_attachment_image_url( $fb_ids['m'], 'large' ) : '';
        $fb_f_url_large = isset( $fb_ids['f'] ) ? wp_get_attachment_image_url( $fb_ids['f'], 'large' ) : '';
        $fb_in    = ! empty( $all_fb_ids ) ? implode( ',', array_map( 'intval', $all_fb_ids ) ) : '0';
        $fb_urls  = array_values( array_unique( array_filter( [ $fb_m_url, $fb_f_url, $fb_m_url_large, $fb_f_url_large ] ) ) );
        $fb_url_in = $fb_urls
            ? implode( ',', array_map( fn($u) => $wpdb->prepare( '%s', $u ), $fb_urls ) )
            : "''";
        $fallback_ids = $wpdb->get_col(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} thumb ON thumb.post_id = p.ID AND thumb.meta_key = '_thumbnail_id'
             WHERE p.post_type = 'athlete' AND p.post_status IN ('publish','draft')
               AND (
                 thumb.meta_value IN ({$fb_in})
                 OR EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta}
                   WHERE post_id = p.ID AND meta_key = 'sta_flickr_photo_url' AND meta_value IN ({$fb_url_in})
                 )
                 OR EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta}
                   WHERE post_id = p.ID AND meta_key = 'sta_photo_is_fallback' AND meta_value = '1'
                 )
               )
             ORDER BY p.post_title ASC"
        );
        $athletes_with_fallback = $fallback_ids ? get_posts( [
            'post_type'   => 'athlete',
            'post_status' => [ 'publish', 'draft' ],
            'include'     => $fallback_ids,
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ] ) : [];

        // Atleten zonder enige foto (geen _thumbnail_id meta)
        $no_photo_ids = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
             WHERE p.post_type = 'athlete' AND p.post_status IN ('publish','draft') AND pm.meta_value IS NULL
             ORDER BY p.post_title ASC"
        );
        $athletes_no_photo = $no_photo_ids ? get_posts( [
            'post_type'   => 'athlete',
            'post_status' => [ 'publish', 'draft' ],
            'include'     => $no_photo_ids,
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ] ) : [];

        $active_tab = $_GET['foto_tab'] ?? 'fallback';
        ?>
        <div class="wrap sta-wrap">
        <h1>Foto integratie</h1>

        <form method="post" action="options.php">
            <?php settings_fields( 'sta_foto_settings' ); ?>

            <div class="sta-card">
                <h2>Flickr fotograaf</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="sta_flickr_photographer">Gebruikersnaam</label></th>
                        <td>
                            <input type="text" id="sta_flickr_photographer" name="sta_flickr_photographer"
                                   value="<?php echo esc_attr( $photographer ); ?>" class="regular-text">
                            <p class="description">De Flickr gebruikersnaam van de toernooifotograaf.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="sta-card">
                <h2>Toernooi foto's</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="sta_tournament_tag">Toernooi tag</label></th>
                        <td>
                            <input type="text" id="sta_tournament_tag" name="sta_tournament_tag"
                                   value="<?php echo esc_attr( $tag ); ?>" class="regular-text"
                                   placeholder="bijv. est2026">
                            <p class="description">De Flickr-tag die de fotograaf gebruikt voor dit toernooi. Per atleet wordt gezocht op <code>[naam] + [tag]</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sta_photo_scan_interval">Scaninterval</label></th>
                        <td>
                            <input type="number" id="sta_photo_scan_interval" name="sta_photo_scan_interval"
                                   value="<?php echo $interval; ?>" min="1" max="168" style="width:80px;"> uur
                            <p class="description">
                                Hoe vaak nieuwe foto's worden opgehaald. Maximaal 6 per atleet; oudste worden verwijderd.
                                <?php if ( $last_scan ) : ?>
                                    Laatste scan: <strong><?php echo wp_date( 'd-m-Y H:i', $last_scan ); ?></strong>.
                                <?php endif; ?>
                                <?php if ( $next_scan ) : ?>
                                    Volgende scan: <strong><?php echo wp_date( 'H:i', $next_scan ); ?></strong>.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin-top:8px;">
                    <button type="button" id="sta-scan-now-btn" class="button button-secondary"
                            <?php echo ( $tag && $photographer ) ? '' : 'disabled title="Vul eerst fotograaf en tag in"'; ?>>
                        Nu scannen
                    </button>
                    <span id="sta-scan-status" style="margin-left:12px;font-size:13px;color:#666;">
                        <?php if ( $athletes_count ) echo $athletes_count . ' atleten hebben momenteel toernooifoto\'s.'; ?>
                    </span>
                </p>
            </div>

            <?php submit_button( 'Instellingen opslaan' ); ?>
        </form>

        <?php
        $base_url = admin_url( 'admin.php?page=swimming-trials-fotointegratie' );
        $tabs = [
            'fallback'  => 'Atleten met fallback foto (' . count( $athletes_with_fallback ) . ')',
            'no_photo'  => 'Atleten zonder foto (' . count( $athletes_no_photo ) . ')',
        ];
        ?>
        <nav class="nav-tab-wrapper" style="margin-bottom:0;">
            <?php foreach ( $tabs as $key => $label ) : ?>
            <a href="<?php echo esc_url( $base_url . '&foto_tab=' . $key ); ?>"
               class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <?php if ( $active_tab === 'fallback' ) : ?>
        <?php if ( empty( $all_fb_ids ) ) : ?>
        <div class="sta-card"><p style="color:#c00;">Stel eerst een fallback foto in via <a href="<?php echo admin_url('admin.php?page=swimming-trials-ai'); ?>">Instellingen → Fallback foto</a>.</p></div>
        <?php elseif ( empty( $athletes_with_fallback ) ) : ?>
        <div class="sta-card"><p style="color:#666;">Geen atleten met fallback foto gevonden. Atleten krijgen de fallback na het uitvoeren van "Verrijk atleten" wanneer er geen Flickr-foto beschikbaar is.</p></div>
        <?php else :
            $by_gender = [ 'm' => [], 'f' => [] ];
            foreach ( $athletes_with_fallback as $a ) {
                $g = get_post_meta( $a->ID, 'sta_gender', true );
                $by_gender[ $g === 'f' ? 'f' : 'm' ][] = $a;
            }
        ?>
        <div class="sta-card" style="border-top:none;border-radius:0 0 4px 4px;">
            <p style="color:#666;font-size:13px;">Atleten met een fallback foto. Zoek hieronder de 5 meest recente Flickr-foto's om te koppelen.</p>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;">
                    <input type="checkbox" id="fb-select-all"> Alles selecteren
                </label>
                <button id="fb-find-btn" class="button button-primary" disabled>
                    Foto's zoeken (<span id="fb-count">0</span> geselecteerd)
                </button>
            </div>

            <?php foreach ( [ 'm' => 'Mannen', 'f' => 'Vrouwen' ] as $g => $label ) :
                if ( empty( $by_gender[ $g ] ) ) continue; ?>
            <details style="margin-top:12px;" open>
                <summary style="cursor:pointer;font-weight:600;font-size:14px;">
                    <?php echo $label; ?> (<?php echo count( $by_gender[ $g ] ); ?>)
                </summary>
                <ul style="margin:10px 0 0 0;list-style:none;padding:0;columns:3;column-gap:24px;line-height:2.2;">
                    <?php foreach ( $by_gender[ $g ] as $a ) : ?>
                    <li id="fb-li-<?php echo $a->ID; ?>" style="break-inside:avoid;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" class="fb-cb"
                                   data-id="<?php echo $a->ID; ?>"
                                   data-name="<?php echo esc_attr( $a->post_title ); ?>"
                                   data-gender="<?php echo esc_attr( $g ); ?>"
                                   data-max="5">
                            <a href="<?php echo get_edit_post_link( $a->ID ); ?>" target="_blank"
                               style="color:inherit;text-decoration:none;"><?php echo esc_html( $a->post_title ); ?></a>
                        </label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endforeach; ?>

            <div id="fb-results" style="margin-top:20px;"></div>
            <div id="fb-find-bottom" style="margin-top:16px;display:none;">
                <button class="button button-primary fb-find-bottom-btn" disabled>
                    Foto's zoeken (<span class="fb-count-bottom">0</span> geselecteerd)
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ( $active_tab === 'no_photo' ) : ?>
        <?php if ( empty( $athletes_no_photo ) ) : ?>
        <div class="sta-card" style="border-top:none;"><p style="color:#666;">Alle atleten hebben al een foto.</p></div>
        <?php else :
            $by_gender_np = [ 'm' => [], 'f' => [] ];
            foreach ( $athletes_no_photo as $a ) {
                $g = get_post_meta( $a->ID, 'sta_gender', true );
                $by_gender_np[ $g === 'f' ? 'f' : 'm' ][] = $a;
            }
        ?>
        <div class="sta-card" style="border-top:none;border-radius:0 0 4px 4px;">
            <p style="color:#666;font-size:13px;">Atleten zonder enige profielfoto. Zoek hieronder tot 10 Flickr-foto's om te koppelen.</p>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;">
                    <input type="checkbox" id="fb-select-all"> Alles selecteren
                </label>
                <button id="fb-find-btn" class="button button-primary" disabled>
                    Foto's zoeken (<span id="fb-count">0</span> geselecteerd)
                </button>
            </div>

            <?php foreach ( [ 'm' => 'Mannen', 'f' => 'Vrouwen' ] as $g => $label ) :
                if ( empty( $by_gender_np[ $g ] ) ) continue; ?>
            <details style="margin-top:12px;" open>
                <summary style="cursor:pointer;font-weight:600;font-size:14px;">
                    <?php echo $label; ?> (<?php echo count( $by_gender_np[ $g ] ); ?>)
                </summary>
                <ul style="margin:10px 0 0 0;list-style:none;padding:0;columns:3;column-gap:24px;line-height:2.2;">
                    <?php foreach ( $by_gender_np[ $g ] as $a ) : ?>
                    <li id="fb-li-<?php echo $a->ID; ?>" style="break-inside:avoid;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" class="fb-cb"
                                   data-id="<?php echo $a->ID; ?>"
                                   data-name="<?php echo esc_attr( $a->post_title ); ?>"
                                   data-gender="<?php echo esc_attr( $g ); ?>"
                                   data-max="10">
                            <a href="<?php echo get_edit_post_link( $a->ID ); ?>" target="_blank"
                               style="color:inherit;text-decoration:none;"><?php echo esc_html( $a->post_title ); ?></a>
                        </label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endforeach; ?>

            <div id="fb-results" style="margin-top:20px;"></div>
            <div id="fb-find-bottom" style="margin-top:16px;display:none;">
                <button class="button button-primary fb-find-bottom-btn" disabled>
                    Foto's zoeken (<span class="fb-count-bottom">0</span> geselecteerd)
                </button>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        </div>
        <?php
    }
}
