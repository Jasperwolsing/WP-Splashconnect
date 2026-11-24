<?php
defined('ABSPATH') || exit;

class STA_Admin_Livestream {

    public static function register(): void {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('wp_footer',  [__CLASS__, 'render_popup']);
        add_shortcode('livestream_melding', [__CLASS__, 'shortcode_melding']);
    }

    public static function add_menu(): void {
        add_submenu_page(
            'swimming-trials-ai',
            'Livestream notificaties',
            'Livestream',
            'manage_options',
            'sta-livestream',
            [__CLASS__, 'render']
        );
    }

    // ── Default options ───────────────────────────────────────────────────────
    private static function defaults(): array {
        return [
            'sessions'   => [],
            'title'      => '🔴 Nu live',
            'body'       => 'De livestream loopt nu. Kijk mee!',
            'btn_label'  => 'Bekijk livestream',
            'bg_color'   => '#13315c',
            'text_color' => '#ffffff',
            'btn_bg'     => '#079cdd',
            'btn_text'   => '#ffffff',
            'delay'      => 3,
        ];
    }

    private static function get(): array {
        return array_merge(self::defaults(), (array) get_option('sta_livestream', []));
    }

    // ── Save ─────────────────────────────────────────────────────────────────
    public static function render(): void {
        if (isset($_POST['sta_ls_nonce']) && wp_verify_nonce($_POST['sta_ls_nonce'], 'sta_livestream_save')) {
            $raw = $_POST['sta_ls'] ?? [];

            $sessions = [];
            foreach ((array)($raw['sessions'] ?? []) as $s) {
                $start = sanitize_text_field($s['start'] ?? '');
                $end   = sanitize_text_field($s['end']   ?? '');
                $url   = esc_url_raw($s['url'] ?? '');
                $label = sanitize_text_field($s['label'] ?? '');
                if ($start && $url) {
                    $sessions[] = compact('start', 'end', 'url', 'label');
                }
            }

            $data = [
                'sessions'   => $sessions,
                'title'      => sanitize_text_field($raw['title']      ?? ''),
                'body'       => sanitize_textarea_field($raw['body']    ?? ''),
                'btn_label'  => sanitize_text_field($raw['btn_label']   ?? ''),
                'bg_color'   => sanitize_hex_color($raw['bg_color']     ?? '') ?: '#13315c',
                'text_color' => sanitize_hex_color($raw['text_color']   ?? '') ?: '#ffffff',
                'btn_bg'     => sanitize_hex_color($raw['btn_bg']       ?? '') ?: '#079cdd',
                'btn_text'   => sanitize_hex_color($raw['btn_text']     ?? '') ?: '#ffffff',
                'delay'      => max(0, min(60, absint($raw['delay'] ?? 3))),
            ];

            update_option('sta_livestream', $data);
            $s = $data;
            echo '<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>';
        } else {
            $s = self::get();
        }

        $sessions_json = wp_json_encode($s['sessions']);
        ?>
        <div class="wrap">
            <h1>Livestream notificaties</h1>
            <p style="color:#555;max-width:600px;">Stel hier in wanneer de livestream actief is. Bezoekers zien dan automatisch een popup met een link naar de stream.</p>

            <form method="post">
                <?php wp_nonce_field('sta_livestream_save', 'sta_ls_nonce'); ?>

                <div style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start;margin-top:20px;">

                    <!-- LEFT: sessions + text -->
                    <div>

                        <!-- Sessions -->
                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Sessies</h3>
                            <p style="color:#666;font-size:13px;margin-top:-8px;">Voeg een rij toe per blok waarin de livestream actief is.</p>

                            <table style="width:100%;border-collapse:collapse;" id="sta-ls-sessions">
                                <thead>
                                    <tr style="font-size:12px;color:#666;text-align:left;">
                                        <th style="padding:4px 8px 8px 0;width:170px;">Start (datum + tijd)</th>
                                        <th style="padding:4px 8px 8px;width:170px;">Einde (datum + tijd)</th>
                                        <th style="padding:4px 8px 8px;">URL</th>
                                        <th style="padding:4px 8px 8px;width:140px;">Label (optioneel)</th>
                                        <th style="width:32px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="sta-ls-body">
                                    <!-- rows injected by JS -->
                                </tbody>
                            </table>

                            <button type="button" id="sta-ls-add" class="button" style="margin-top:10px;">+ Sessie toevoegen</button>
                        </div>

                        <!-- Text -->
                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Tekst</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Titel</label>
                            <input type="text" name="sta_ls[title]" value="<?php echo esc_attr($s['title']); ?>" style="width:100%;margin-bottom:4px;" oninput="lsPreview()">
                            <p style="font-size:11px;color:#999;margin:0 0 12px;">Gebruik <code>{label}</code> voor het sessielabel, bijv. <em>🔴 Nu live: {label}</em></p>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Berichttekst</label>
                            <textarea name="sta_ls[body]" rows="2" style="width:100%;margin-bottom:12px;" oninput="lsPreview()"><?php echo esc_textarea($s['body']); ?></textarea>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Knoptekst</label>
                            <input type="text" name="sta_ls[btn_label]" value="<?php echo esc_attr($s['btn_label']); ?>" style="width:100%;" oninput="lsPreview()">
                        </div>

                        <?php submit_button('Opslaan'); ?>
                    </div>

                    <!-- RIGHT: appearance + preview -->
                    <div>
                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Weergave</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Achtergrond popup</label>
                            <input type="color" name="sta_ls[bg_color]" id="ls-bg" value="<?php echo esc_attr($s['bg_color']); ?>" oninput="lsPreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Tekstkleur</label>
                            <input type="color" name="sta_ls[text_color]" id="ls-text" value="<?php echo esc_attr($s['text_color']); ?>" oninput="lsPreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Knop achtergrond</label>
                            <input type="color" name="sta_ls[btn_bg]" id="ls-btn-bg" value="<?php echo esc_attr($s['btn_bg']); ?>" oninput="lsPreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Knop tekstkleur</label>
                            <input type="color" name="sta_ls[btn_text]" id="ls-btn-text" value="<?php echo esc_attr($s['btn_text']); ?>" oninput="lsPreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Vertraging (seconden)</label>
                            <input type="number" name="sta_ls[delay]" id="ls-delay" value="<?php echo (int)$s['delay']; ?>" min="0" max="60" style="width:80px;">
                        </div>

                        <!-- Preview -->
                        <p style="font-size:12px;color:#999;margin-bottom:6px;">Voorbeeld popup</p>
                        <div id="ls-preview" style="
                            background:<?php echo esc_attr($s['bg_color']); ?>;
                            color:<?php echo esc_attr($s['text_color']); ?>;
                            border-radius:12px;
                            padding:16px 18px;
                            box-shadow:0 4px 20px rgba(0,0,0,.2);
                            max-width:340px;
                            font-family:Arial,sans-serif;
                        ">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px;">
                                <strong id="ls-prev-title" style="font-size:15px;"><?php echo esc_html($s['title']); ?></strong>
                                <span style="opacity:.5;cursor:pointer;font-size:18px;line-height:1;">✕</span>
                            </div>
                            <p id="ls-prev-body" style="margin:0 0 12px;font-size:13px;opacity:.85;line-height:1.5;"><?php echo nl2br(esc_html($s['body'])); ?></p>
                            <a id="ls-prev-btn" href="#" onclick="return false;" style="
                                display:inline-block;
                                background:<?php echo esc_attr($s['btn_bg']); ?>;
                                color:<?php echo esc_attr($s['btn_text']); ?>;
                                padding:9px 18px;
                                border-radius:0;
                                text-decoration:none;
                                font-size:13px;
                                font-weight:700;
                            "><?php echo esc_html($s['btn_label']); ?></a>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <script>
        // ── Session row template ──────────────────────────────────────────────
        function lsRowHtml(idx, s) {
            s = s || {};
            var fieldStyle = 'padding:5px 6px;border:1px solid #ccc;border-radius:4px;font-size:12px;width:100%;box-sizing:border-box;';
            return '<tr>' +
                '<td style="padding:4px 8px 4px 0;"><input type="datetime-local" name="sta_ls[sessions]['+idx+'][start]" value="'+(s.start||'')+'" style="'+fieldStyle+'" required></td>' +
                '<td style="padding:4px 8px;"><input type="datetime-local" name="sta_ls[sessions]['+idx+'][end]" value="'+(s.end||'')+'" style="'+fieldStyle+'"></td>' +
                '<td style="padding:4px 8px;"><input type="url" name="sta_ls[sessions]['+idx+'][url]" value="'+(s.url||'')+'" placeholder="https://zwemsport.tv/" style="'+fieldStyle+'" required></td>' +
                '<td style="padding:4px 8px;"><input type="text" name="sta_ls[sessions]['+idx+'][label]" value="'+(s.label||'')+'" placeholder="Dag 1 ochtend" style="'+fieldStyle+'"></td>' +
                '<td style="padding:4px 0 4px 4px;text-align:center;"><button type="button" class="button sta-ls-remove" style="padding:2px 6px;font-size:16px;line-height:1;" title="Verwijder">✕</button></td>' +
                '</tr>';
        }

        var lsData = <?php echo $sessions_json; ?>;
        var lsIdx  = lsData.length;

        (function() {
            var tbody = document.getElementById('sta-ls-body');
            lsData.forEach(function(s, i) { tbody.insertAdjacentHTML('beforeend', lsRowHtml(i, s)); });

            document.getElementById('sta-ls-add').addEventListener('click', function() {
                tbody.insertAdjacentHTML('beforeend', lsRowHtml(lsIdx++, {}));
            });

            tbody.addEventListener('click', function(e) {
                if (e.target.closest('.sta-ls-remove')) {
                    e.target.closest('tr').remove();
                }
            });
        })();

        // ── Live preview ──────────────────────────────────────────────────────
        function lsPreview() {
            var p = document.getElementById('ls-preview');
            p.style.background = document.getElementById('ls-bg').value;
            p.style.color      = document.getElementById('ls-text').value;
            document.getElementById('ls-prev-title').textContent = document.querySelector('[name="sta_ls[title]"]').value;
            document.getElementById('ls-prev-body').innerHTML    = document.querySelector('[name="sta_ls[body]"]').value.replace(/\n/g,'<br>');
            document.getElementById('ls-prev-btn').textContent   = document.querySelector('[name="sta_ls[btn_label]"]').value;
            document.getElementById('ls-prev-btn').style.background = document.getElementById('ls-btn-bg').value;
            document.getElementById('ls-prev-btn').style.color      = document.getElementById('ls-btn-text').value;
        }
        </script>
        <?php
    }

    // ── Shortcode [livestream_melding] ────────────────────────────────────────
    public static function shortcode_melding(): string {
        $s = self::get();
        if (empty($s['sessions'])) return '';

        $sessions_js = wp_json_encode(array_map(function($sess) {
            return [
                'start' => $sess['start'],
                'end'   => $sess['end'] ?: '',
                'url'   => $sess['url'],
                'label' => $sess['label'] ?? '',
            ];
        }, $s['sessions']));

        $id = 'sta-ls-melding-' . wp_unique_id();
        ob_start();
        ?>
        <div id="<?php echo esc_attr($id); ?>" style="display:none;background:<?php echo esc_attr($s['bg_color']); ?>;color:<?php echo esc_attr($s['text_color']); ?>;border-radius:8px;padding:12px 18px;margin:12px 0;align-items:center;gap:12px;flex-wrap:wrap;">
            <span id="<?php echo esc_attr($id); ?>-text" style="flex:1;font-size:14px;font-weight:600;"></span>
            <a id="<?php echo esc_attr($id); ?>-btn" href="#" target="_blank" rel="noopener" style="background:<?php echo esc_attr($s['btn_bg']); ?>;color:<?php echo esc_attr($s['btn_text']); ?>;padding:7px 16px;text-decoration:none;font-size:13px;font-weight:700;border-radius:4px;white-space:nowrap;"><?php echo esc_html($s['btn_label']); ?></a>
        </div>
        <script>
        (function() {
            var sessions = <?php echo $sessions_js; ?>;
            var id = <?php echo wp_json_encode($id); ?>;
            function parseLocal(dt) {
                if (!dt) return null;
                var p = dt.split(/[^0-9]/);
                return new Date(+p[0], (+p[1])-1, +p[2], +p[3]||0, +p[4]||0);
            }
            var now = new Date();
            for (var i = 0; i < sessions.length; i++) {
                var s = sessions[i];
                var start = parseLocal(s.start);
                var end   = s.end ? parseLocal(s.end) : null;
                if (!start || now < start || (end && now > end)) continue;
                var lbl = s.label || '';
                var title = <?php echo wp_json_encode($s['title']); ?>.replace(/\{label\}/g, lbl);
                document.getElementById(id + '-text').textContent = title;
                document.getElementById(id + '-btn').href = s.url;
                document.getElementById(id).style.display = 'flex';
                break;
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // ── Frontend popup ────────────────────────────────────────────────────────
    public static function render_popup(): void {
        $s = self::get();
        if (empty($s['sessions'])) return;

        $sessions_js = wp_json_encode(array_map(function($sess) {
            return [
                'start' => $sess['start'],
                'end'   => $sess['end'] ?: '',
                'url'   => $sess['url'],
                'label' => $sess['label'] ?? '',
            ];
        }, $s['sessions']));
        ?>
        <style>
        #sta-ls-popup {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            max-width: 320px;
            width: calc(100vw - 32px);
            background: <?php echo esc_attr($s['bg_color']); ?>;
            color: <?php echo esc_attr($s['text_color']); ?>;
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,.25);
            font-family: Arial, sans-serif;
            opacity: 0;
            transform: translateY(16px);
            transition: opacity .35s, transform .35s;
            pointer-events: none;
        }
        #sta-ls-popup.sta-ls-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        @media (max-width: 600px) {
            #sta-ls-popup {
                right: 16px;
                left: 16px;
                width: auto;
                max-width: none;
                bottom: 16px;
                border-radius: 10px;
            }
        }
        #sta-ls-popup-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }
        #sta-ls-popup-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }
        #sta-ls-popup-close {
            background: none;
            border: none;
            color: inherit;
            opacity: .55;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            padding: 0;
            flex-shrink: 0;
        }
        #sta-ls-popup-close:hover { opacity: 1; }
        #sta-ls-popup-body {
            font-size: 13px;
            opacity: .85;
            line-height: 1.5;
            margin: 0 0 12px;
        }
        #sta-ls-popup-btn {
            display: inline-block;
            background: <?php echo esc_attr($s['btn_bg']); ?>;
            color: <?php echo esc_attr($s['btn_text']); ?>;
            padding: 9px 18px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            border-radius: 0;
            transition: opacity .15s;
        }
        #sta-ls-popup-btn:hover { opacity: .85; }
        </style>

        <div id="sta-ls-popup" role="status" aria-live="polite">
            <div id="sta-ls-popup-header">
                <p id="sta-ls-popup-title"><?php echo esc_html($s['title']); ?></p>
                <button id="sta-ls-popup-close" aria-label="Sluiten">✕</button>
            </div>
            <p id="sta-ls-popup-body"><?php echo nl2br(esc_html($s['body'])); ?></p>
            <a id="sta-ls-popup-btn" href="#" target="_blank" rel="noopener"><?php echo esc_html($s['btn_label']); ?></a>
        </div>

        <script>
        (function() {
            var sessions = <?php echo $sessions_js; ?>;
            var delay    = <?php echo (int)$s['delay']; ?> * 1000;

            function parseLocal(dt) {
                if (!dt) return null;
                var p = dt.split(/[^0-9]/);
                return new Date(+p[0], (+p[1])-1, +p[2], +p[3]||0, +p[4]||0);
            }

            function activeSession() {
                var now = new Date();
                for (var i = 0; i < sessions.length; i++) {
                    var s = sessions[i];
                    var start = parseLocal(s.start);
                    var end   = s.end ? parseLocal(s.end) : null;
                    if (!start) continue;
                    if (now >= start && (!end || now <= end)) return s;
                }
                return null;
            }

            var popup  = document.getElementById('sta-ls-popup');
            var btn    = document.getElementById('sta-ls-popup-btn');
            var close  = document.getElementById('sta-ls-popup-close');

            function tryShow() {
                var sess = activeSession();
                if (!sess) return;

                var shownKey = 'sta_ls_shown_' + sess.start;
                if (localStorage.getItem(shownKey)) return;

                btn.href = sess.url;
                var lbl = sess.label || '';
                document.getElementById('sta-ls-popup-title').textContent =
                    <?php echo wp_json_encode($s['title']); ?>.replace(/\{label\}/g, lbl);
                document.getElementById('sta-ls-popup-body').innerHTML =
                    <?php echo wp_json_encode($s['body']); ?>.replace(/\{label\}/g, lbl).replace(/\n/g, '<br>');
                localStorage.setItem(shownKey, '1');
                setTimeout(function() {
                    popup.classList.add('sta-ls-visible');
                }, delay);
            }

            close.addEventListener('click', function() {
                popup.classList.remove('sta-ls-visible');
            });

            tryShow();
        })();
        </script>
        <?php
    }
}
