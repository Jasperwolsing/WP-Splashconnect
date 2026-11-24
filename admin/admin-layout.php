<?php
defined('ABSPATH') || exit;

class STA_Admin_Layout {

    public static function register(): void {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        register_setting('sta_layout', 'sta_layout', [
            'sanitize_callback' => [__CLASS__, 'sanitize'],
        ]);
    }

    public static function add_menu(): void {
        add_submenu_page(
            'swimming-trials-ai',
            'Pagina-opmaak',
            'Pagina-opmaak',
            'manage_options',
            'sta-layout',
            [__CLASS__, 'render']
        );
    }

    public static function sanitize($input): array {
        $clean = [];
        $clean['primary_color']    = sanitize_hex_color($input['primary_color'] ?? '#13315c') ?: '#13315c';
        // Ontbreekt de sleutel (bv. opslag buiten het formulier), dan blijft de balk zichtbaar.
        $clean['show_header']      = array_key_exists('show_header', (array) $input) ? (empty($input['show_header']) ? 0 : 1) : 1;
        $clean['header_image_id']  = absint($input['header_image_id'] ?? 0);
        $clean['header_height']    = max(80, min(600, absint($input['header_height'] ?? 220)));
        $clean['flag_shape']       = in_array($input['flag_shape'] ?? '', ['circle', 'rounded', 'square']) ? $input['flag_shape'] : 'circle';
        $clean['card_radius']      = max(0, min(40, absint($input['card_radius'] ?? 12)));
        $clean['card_bg']          = sanitize_hex_color($input['card_bg'] ?? '#ffffff') ?: '#ffffff';
        $clean['label_radius']     = max(0, min(40, absint($input['label_radius'] ?? 50)));
        $clean['photo_ratio']      = in_array($input['photo_ratio'] ?? '', ['1/1', '3/4', '4/3']) ? $input['photo_ratio'] : '3/4';
        $clean['name_badge_bg']    = sanitize_hex_color($input['name_badge_bg'] ?? '#0d2d63') ?: '#0d2d63';
        $clean['name_badge_radius'] = max(0, min(50, absint($input['name_badge_radius'] ?? 50)));
        $clean['club_badge_bg']     = sanitize_hex_color($input['club_badge_bg'] ?? '#13315C') ?: '#13315C';
        $clean['club_badge_radius'] = max(0, min(50, absint($input['club_badge_radius'] ?? 16)));
        $clean['dist_badge_bg']     = sanitize_hex_color($input['dist_badge_bg'] ?? '#0d2d63') ?: '#0d2d63';
        $clean['dist_badge_radius'] = max(0, min(50, absint($input['dist_badge_radius'] ?? 50)));
        // Profielpagina: naam, vlag, landnaam en deel-knop
        $clean['name_badge_color']  = sanitize_hex_color($input['name_badge_color'] ?? '#ffffff') ?: '#ffffff';
        $clean['name_badge_size']   = max(14, min(80, absint($input['name_badge_size'] ?? 34)));
        $clean['flag_profile_size'] = max(16, min(120, absint($input['flag_profile_size'] ?? 46)));
        $clean['country_show']      = empty($input['country_show']) ? 0 : 1;
        $clean['country_color']     = sanitize_hex_color($input['country_color'] ?? '#555555') ?: '#555555';
        $clean['country_size']      = max(12, min(90, absint($input['country_size'] ?? 44)));
        $clean['share_btn_bg']      = sanitize_hex_color($input['share_btn_bg'] ?? '#13315c') ?: '#13315c';
        $clean['share_btn_color']   = sanitize_hex_color($input['share_btn_color'] ?? '#ffffff') ?: '#ffffff';
        $clean['share_btn_radius']  = max(0, min(50, absint($input['share_btn_radius'] ?? 50)));
        $clean['share_btn_text']    = mb_substr(sanitize_text_field($input['share_btn_text'] ?? ''), 0, 40);
        $contact_raw = $input['contact_url'] ?? '';
        $contact_pid = absint($contact_raw);
        $clean['contact_url']  = $contact_pid ? get_permalink($contact_pid) : esc_url_raw($contact_raw);
        $clean['contact_text'] = sanitize_textarea_field($input['contact_text'] ?? '');
        return $clean;
    }

    public static function render(): void {
        wp_enqueue_media();
        $s   = get_option('sta_layout', []);
        $def = [
            'primary_color'   => '#13315c',
            'header_image_id' => 0,
            'header_height'   => 220,
            'flag_shape'      => 'circle',
            'card_radius'     => 12,
            'card_bg'         => '#ffffff',
            'label_radius'    => 50,
            'photo_ratio'       => '3/4',
            'name_badge_bg'     => '#0d2d63',
            'name_badge_radius' => 50,
            'club_badge_bg'     => '#13315C',
            'club_badge_radius' => 16,
            'dist_badge_bg'     => '#0d2d63',
            'dist_badge_radius' => 50,
            'contact_url'       => '',
            'contact_text'      => '',
            'name_badge_color'  => '#ffffff',
            'name_badge_size'   => 34,
            'flag_profile_size' => 46,
            'country_show'      => 1,
            'country_color'     => '#555555',
            'country_size'      => 44,
            'share_btn_bg'      => '#13315c',
            'share_btn_color'   => '#ffffff',
            'share_btn_radius'  => 50,
            'share_btn_text'    => '',
        ];
        $s = array_merge($def, $s);

        $header_url = $s['header_image_id'] ? wp_get_attachment_image_url($s['header_image_id'], 'large') : '';
        $saved = isset($_GET['settings-updated']);
        ?>
        <div class="wrap">
            <h1>Pagina-opmaak</h1>
            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('sta_layout'); ?>

                <div style="display:grid;grid-template-columns:340px 1fr;gap:32px;align-items:start;margin-top:16px;">

                    <!-- Controls -->
                    <div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Header</h3>

                            <input type="hidden" name="sta_layout[show_header]" value="0">
                            <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-weight:600;font-size:13px;">
                                <input type="checkbox" name="sta_layout[show_header]" value="1" <?php checked( ! array_key_exists( 'show_header', (array) $s ) || ! empty( $s['show_header'] ) ); ?>>
                                Headerbalk tonen bovenaan de pagina's
                            </label>
                            <p class="description" style="margin:-8px 0 14px;">Geldt voor atleten, afstanden, clubs en het clubs-overzicht.</p>

                            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;">Afbeelding</label>
                            <input type="hidden" name="sta_layout[header_image_id]" id="sta-header-img-id" value="<?php echo $s['header_image_id']; ?>">
                            <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
                                <div id="sta-header-preview" style="width:80px;height:50px;background:#e5e7eb;border-radius:4px;overflow:hidden;flex-shrink:0;">
                                    <?php if ($header_url) : ?>
                                        <img src="<?php echo esc_url($header_url); ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" class="button" id="sta-header-upload">Afbeelding kiezen</button>
                                    <button type="button" class="button" id="sta-header-remove" style="margin-top:4px;display:block;">Verwijderen</button>
                                </div>
                            </div>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Hoogte (px)</label>
                            <input type="number" name="sta_layout[header_height]" id="sta-header-height"
                                   value="<?php echo $s['header_height']; ?>" min="80" max="600"
                                   style="width:100px;" oninput="updatePreview()">
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Kleuren</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Primaire kleur</label>
                            <input type="color" name="sta_layout[primary_color]" id="sta-primary-color"
                                   value="<?php echo esc_attr($s['primary_color']); ?>"
                                   oninput="updatePreview()">
                            <span id="sta-color-hex" style="margin-left:8px;font-size:13px;color:#666;"><?php echo esc_html($s['primary_color']); ?></span>

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Kaart achtergrond</label>
                            <input type="color" name="sta_layout[card_bg]" id="sta-card-bg"
                                   value="<?php echo esc_attr($s['card_bg']); ?>"
                                   oninput="updatePreview()">
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Vlag</h3>
                            <div style="display:flex;gap:12px;">
                                <?php foreach (['circle' => 'Rond', 'rounded' => 'Afgerond', 'square' => 'Vierkant'] as $val => $lbl) : ?>
                                <label style="display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="radio" name="sta_layout[flag_shape]" value="<?php echo $val; ?>"
                                           <?php checked($s['flag_shape'], $val); ?>
                                           onchange="updatePreview()">
                                    <img src="https://hatscripts.github.io/circle-flags/flags/nl.svg"
                                         style="width:24px;height:24px;border-radius:<?php echo $val === 'circle' ? '50%' : ($val === 'rounded' ? '4px' : '0'); ?>;">
                                    <span style="font-size:12px;"><?php echo $lbl; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <label style="display:block;margin-top:14px;margin-bottom:4px;font-weight:600;font-size:13px;">Grootte op profielpagina (px)</label>
                            <input type="range" name="sta_layout[flag_profile_size]" min="16" max="120"
                                   value="<?php echo (int) $s['flag_profile_size']; ?>" style="width:100%;"
                                   oninput="document.getElementById('sta-flag-size-val').textContent=this.value">
                            <span id="sta-flag-size-val"><?php echo (int) $s['flag_profile_size']; ?></span>px
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Kaart stijl</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Afronding kaart (px)</label>
                            <input type="range" name="sta_layout[card_radius]" id="sta-card-radius"
                                   value="<?php echo $s['card_radius']; ?>" min="0" max="40"
                                   style="width:100%;" oninput="document.getElementById('sta-card-radius-val').textContent=this.value;updatePreview()">
                            <span id="sta-card-radius-val"><?php echo $s['card_radius']; ?></span>px

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Afronding naam-label (px)</label>
                            <input type="range" name="sta_layout[label_radius]" id="sta-label-radius"
                                   value="<?php echo $s['label_radius']; ?>" min="0" max="50"
                                   style="width:100%;" oninput="document.getElementById('sta-label-radius-val').textContent=this.value;updatePreview()">
                            <span id="sta-label-radius-val"><?php echo $s['label_radius']; ?></span>px

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Foto verhouding</label>
                            <select name="sta_layout[photo_ratio]" id="sta-photo-ratio" onchange="updatePreview()">
                                <?php foreach (['1/1' => 'Vierkant (1:1)', '3/4' => 'Portret (3:4)', '4/3' => 'Liggend (4:3)'] as $val => $lbl) : ?>
                                    <option value="<?php echo $val; ?>" <?php selected($s['photo_ratio'], $val); ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Naam badge (profiel pagina)</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Achtergrondkleur</label>
                            <input type="color" name="sta_layout[name_badge_bg]" id="sta-badge-bg"
                                   value="<?php echo esc_attr($s['name_badge_bg']); ?>"
                                   oninput="updatePreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Afronding (px)</label>
                            <input type="range" name="sta_layout[name_badge_radius]" id="sta-badge-radius"
                                   value="<?php echo $s['name_badge_radius']; ?>" min="0" max="50"
                                   style="width:100%;" oninput="document.getElementById('sta-badge-radius-val').textContent=this.value;updatePreview()">
                            <span id="sta-badge-radius-val"><?php echo $s['name_badge_radius']; ?></span>px

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Tekstkleur</label>
                            <input type="color" name="sta_layout[name_badge_color]"
                                   value="<?php echo esc_attr($s['name_badge_color']); ?>">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Tekstgrootte (px)</label>
                            <input type="range" name="sta_layout[name_badge_size]" min="14" max="80"
                                   value="<?php echo (int) $s['name_badge_size']; ?>" style="width:100%;"
                                   oninput="document.getElementById('sta-name-size-val').textContent=this.value">
                            <span id="sta-name-size-val"><?php echo (int) $s['name_badge_size']; ?></span>px
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Landnaam (profielpagina)</h3>

                            <input type="hidden" name="sta_layout[country_show]" value="0">
                            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-weight:600;font-size:13px;">
                                <input type="checkbox" name="sta_layout[country_show]" value="1" <?php checked(!empty($s['country_show'])); ?>>
                                Landnaam tonen naast de naam
                            </label>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Tekstkleur</label>
                            <input type="color" name="sta_layout[country_color]"
                                   value="<?php echo esc_attr($s['country_color']); ?>">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Tekstgrootte (px)</label>
                            <input type="range" name="sta_layout[country_size]" min="12" max="90"
                                   value="<?php echo (int) $s['country_size']; ?>" style="width:100%;"
                                   oninput="document.getElementById('sta-country-size-val').textContent=this.value">
                            <span id="sta-country-size-val"><?php echo (int) $s['country_size']; ?></span>px
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Deel-knop (profielpagina)</h3>
                            <p style="margin:0 0 10px;font-size:12px;color:#666;">De knop "Share profile card". Zichtbaar als de module Share card aan staat en is ingesteld.</p>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Tekst</label>
                            <input type="text" name="sta_layout[share_btn_text]" maxlength="40" style="width:100%;"
                                   value="<?php echo esc_attr($s['share_btn_text']); ?>" placeholder="Share profile card">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Achtergrondkleur</label>
                            <input type="color" name="sta_layout[share_btn_bg]"
                                   value="<?php echo esc_attr($s['share_btn_bg']); ?>">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Tekstkleur</label>
                            <input type="color" name="sta_layout[share_btn_color]"
                                   value="<?php echo esc_attr($s['share_btn_color']); ?>">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Afronding (px)</label>
                            <input type="range" name="sta_layout[share_btn_radius]" min="0" max="50"
                                   value="<?php echo (int) $s['share_btn_radius']; ?>" style="width:100%;"
                                   oninput="document.getElementById('sta-share-radius-val').textContent=this.value">
                            <span id="sta-share-radius-val"><?php echo (int) $s['share_btn_radius']; ?></span>px
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Club logo tegel (profielpagina)</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Achtergrondkleur</label>
                            <input type="color" name="sta_layout[club_badge_bg]" id="sta-club-badge-bg"
                                   value="<?php echo esc_attr($s['club_badge_bg']); ?>"
                                   oninput="updatePreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Afronding (px)</label>
                            <input type="range" name="sta_layout[club_badge_radius]" id="sta-club-badge-radius"
                                   value="<?php echo $s['club_badge_radius']; ?>" min="0" max="50"
                                   style="width:100%;" oninput="document.getElementById('sta-club-badge-radius-val').textContent=this.value;updatePreview()">
                            <span id="sta-club-badge-radius-val"><?php echo $s['club_badge_radius']; ?></span>px
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Afstand tegels (profielpagina)</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Achtergrondkleur</label>
                            <input type="color" name="sta_layout[dist_badge_bg]" id="sta-dist-badge-bg"
                                   value="<?php echo esc_attr($s['dist_badge_bg']); ?>"
                                   oninput="updatePreview()">

                            <label style="display:block;margin-top:12px;margin-bottom:4px;font-weight:600;font-size:13px;">Afronding (px)</label>
                            <input type="range" name="sta_layout[dist_badge_radius]" id="sta-dist-badge-radius"
                                   value="<?php echo $s['dist_badge_radius']; ?>" min="0" max="50"
                                   style="width:100%;" oninput="document.getElementById('sta-dist-badge-radius-val').textContent=this.value;updatePreview()">
                            <span id="sta-dist-badge-radius-val"><?php echo $s['dist_badge_radius']; ?></span>px
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                            <h3 style="margin-top:0;">Knoppen</h3>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Tekst boven de knop</label>
                            <textarea name="sta_layout[contact_text]" rows="3"
                                      style="width:100%;margin-bottom:16px;font-size:13px;"
                                      placeholder="The photo and biography on this page were generated automatically. Spotted a mistake or have something to add?&#10;Feel free to send us a message."><?php echo esc_textarea($s['contact_text']); ?></textarea>

                            <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Contact-knop URL</label>
                            <p style="margin:0 0 8px;font-size:12px;color:#666;">Kies een pagina of vul een URL in. Leeg = knop verborgen.</p>

                            <?php
                            $contact_val = $s['contact_url'];
                            // Try to find a matching page id for the stored URL
                            $contact_pid = $contact_val ? url_to_postid($contact_val) : 0;
                            $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
                            ?>
                            <select id="sta-contact-page" style="width:100%;margin-bottom:8px;" onchange="
                                var v = this.value;
                                document.getElementById('sta-contact-url-input').value = v ? '' : document.getElementById('sta-contact-url-input').value;
                                document.getElementById('sta-contact-url').value = v || document.getElementById('sta-contact-url-input').value;
                            ">
                                <option value="">— Handmatige URL —</option>
                                <?php foreach ($pages as $page) : ?>
                                    <option value="<?php echo esc_attr(get_permalink($page->ID)); ?>"
                                        <?php selected($contact_pid, $page->ID); ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="url" id="sta-contact-url-input" placeholder="https://…"
                                   style="width:100%;"
                                   value="<?php echo $contact_pid ? '' : esc_attr($contact_val); ?>"
                                   oninput="document.getElementById('sta-contact-url').value = this.value; document.getElementById('sta-contact-page').value = '';">

                            <input type="hidden" name="sta_layout[contact_url]" id="sta-contact-url"
                                   value="<?php echo esc_attr($contact_val); ?>">
                        </div>

                        <?php submit_button('Opslaan'); ?>
                    </div>

                    <!-- Preview -->
                    <div style="position:sticky;top:32px;">
                        <p style="font-size:12px;color:#999;margin-bottom:8px;">Live preview</p>

                        <div id="sta-preview-wrap" style="border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#f9f9f9;">

                            <!-- Header preview -->
                            <div id="prev-header" style="
                                height:<?php echo $s['header_height']; ?>px;
                                background-color:<?php echo esc_attr($s['primary_color']); ?>;
                                background-image:<?php echo $header_url ? 'url(' . esc_url($header_url) . ')' : 'none'; ?>;
                                background-size:cover;
                                background-position:center;
                                transition:height .2s;
                            "></div>

                            <!-- Content preview -->
                            <div style="padding:20px;">
                                <h2 id="prev-title" style="text-align:center;color:<?php echo esc_attr($s['primary_color']); ?>;margin:0 0 8px;font-size:20px;">Athletes</h2>
                                <p style="text-align:center;color:#666;font-size:13px;margin:0 0 16px;">3 athletes on the start list</p>

                                <!-- Gender label -->
                                <span id="prev-label" style="
                                    display:inline-block;
                                    background:<?php echo esc_attr($s['primary_color']); ?>;
                                    color:#fff;
                                    padding:4px 16px;
                                    border-radius:<?php echo $s['label_radius']; ?>px;
                                    font-size:13px;
                                    font-weight:700;
                                    margin-bottom:12px;
                                ">Women <span style="opacity:.7;">(3)</span></span>

                                <!-- Name badge preview -->
                                <div style="margin-bottom:16px;">
                                    <p style="font-size:11px;color:#999;margin:0 0 6px;">Naam badge (profielpagina)</p>
                                    <div id="prev-badge" style="
                                        display:inline-flex;align-items:center;gap:12px;
                                        background:<?php echo esc_attr($s['name_badge_bg']); ?>;
                                        color:#fff;padding:10px 20px;
                                        border-radius:<?php echo $s['name_badge_radius']; ?>px;
                                        font-size:18px;font-weight:700;text-transform:uppercase;
                                    ">Anna de Vries</div>
                                </div>

                                <!-- Club badge preview -->
                                <div style="margin-bottom:16px;">
                                    <p style="font-size:11px;color:#999;margin:0 0 6px;">Club logo tegel (profielpagina)</p>
                                    <span id="prev-club-badge" style="
                                        display:inline-flex;flex-direction:column;align-items:center;
                                        background:<?php echo esc_attr($s['club_badge_bg']); ?>;
                                        border-radius:<?php echo $s['club_badge_radius']; ?>px;
                                        padding:12px 20px;gap:8px;
                                    ">
                                        <span style="font-size:24px;opacity:.6;">🏊</span>
                                        <span style="font-size:13px;font-weight:600;color:#fff;">Zwemclub</span>
                                    </span>
                                </div>

                                <!-- Distance badge preview -->
                                <div style="margin-bottom:16px;">
                                    <p style="font-size:11px;color:#999;margin:0 0 6px;">Afstand tegels (profielpagina)</p>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                        <?php foreach (['100m Vrije slag', '200m Rugslag', '4x100m Wisselslag'] as $d) : ?>
                                        <span id="prev-dist-badge" style="
                                            background:<?php echo esc_attr($s['dist_badge_bg']); ?>;
                                            color:#fff;
                                            padding:6px 18px;
                                            border-radius:<?php echo $s['dist_badge_radius']; ?>px;
                                            font-size:14px;
                                            font-weight:600;
                                        "><?php echo $d; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Card grid -->
                                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                                    <?php foreach (['Roos', 'Emma', 'Lisa'] as $name) : ?>
                                    <div id="prev-card" style="
                                        background:<?php echo esc_attr($s['card_bg']); ?>;
                                        border:1px solid #e5e7eb;
                                        border-radius:<?php echo $s['card_radius']; ?>px;
                                        overflow:hidden;
                                    ">
                                        <div style="aspect-ratio:<?php echo esc_attr($s['photo_ratio']); ?>;background:#d1d5db;display:flex;align-items:center;justify-content:center;">
                                            <span style="font-size:28px;opacity:.3;">🏊</span>
                                        </div>
                                        <div style="padding:8px;">
                                            <div style="display:flex;align-items:center;gap:4px;">
                                                <img src="https://hatscripts.github.io/circle-flags/flags/nl.svg"
                                                     id="prev-flag"
                                                     style="width:14px;height:14px;border-radius:<?php echo $s['flag_shape'] === 'circle' ? '50%' : ($s['flag_shape'] === 'rounded' ? '3px' : '0'); ?>;flex-shrink:0;">
                                                <strong style="font-size:11px;color:<?php echo esc_attr($s['primary_color']); ?>;"><?php echo $name; ?></strong>
                                            </div>
                                            <div style="font-size:10px;color:#666;">Zwemclub</div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <script>
        jQuery(function($) {

            // Media uploader
            $('#sta-header-upload').on('click', function() {
                var frame = wp.media({ title: 'Header afbeelding', button: { text: 'Selecteer' }, multiple: false });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#sta-header-img-id').val(att.id);
                    var url = att.sizes && att.sizes.large ? att.sizes.large.url : att.url;
                    $('#sta-header-preview').html('<img src="' + url + '" style="width:100%;height:100%;object-fit:cover;">');
                    document.getElementById('prev-header').style.backgroundImage = 'url(' + url + ')';
                });
                frame.open();
            });

            $('#sta-header-remove').on('click', function() {
                $('#sta-header-img-id').val(0);
                $('#sta-header-preview').html('');
                document.getElementById('prev-header').style.backgroundImage = 'none';
            });
        });

        function updatePreview() {
            var color      = document.getElementById('sta-primary-color').value;
            var cardBg     = document.getElementById('sta-card-bg').value;
            var cardRadius = document.getElementById('sta-card-radius').value;
            var lblRadius  = document.getElementById('sta-label-radius').value;
            var ratio      = document.getElementById('sta-photo-ratio').value;
            var height     = document.getElementById('sta-header-height').value;
            var flagShape  = document.querySelector('input[name="sta_layout[flag_shape]"]:checked')?.value || 'circle';
            var flagRadius = flagShape === 'circle' ? '50%' : (flagShape === 'rounded' ? '3px' : '0');

            document.getElementById('sta-color-hex').textContent = color;
            document.getElementById('prev-header').style.height = height + 'px';
            document.getElementById('prev-header').style.backgroundColor = color;
            document.getElementById('prev-title').style.color = color;
            document.getElementById('prev-label').style.background = color;
            document.getElementById('prev-label').style.borderRadius = lblRadius + 'px';

            document.querySelectorAll('#prev-card').forEach(function(card) {
                card.style.background = cardBg;
                card.style.borderRadius = cardRadius + 'px';
                card.querySelector('div[style*="aspect-ratio"]').style.aspectRatio = ratio;
                card.querySelector('strong').style.color = color;
            });
            document.querySelectorAll('#prev-flag').forEach(function(flag) {
                flag.style.borderRadius = flagRadius;
            });

            var badgeBg     = document.getElementById('sta-badge-bg').value;
            var badgeRadius = document.getElementById('sta-badge-radius').value;
            var badge = document.getElementById('prev-badge');
            if (badge) {
                badge.style.background    = badgeBg;
                badge.style.borderRadius  = badgeRadius + 'px';
            }

            var clubBadgeBg     = document.getElementById('sta-club-badge-bg').value;
            var clubBadgeRadius = document.getElementById('sta-club-badge-radius').value;
            document.getElementById('sta-club-badge-radius-val').textContent = clubBadgeRadius;
            var clubBadge = document.getElementById('prev-club-badge');
            if (clubBadge) {
                clubBadge.style.background   = clubBadgeBg;
                clubBadge.style.borderRadius = clubBadgeRadius + 'px';
            }

            var distBg     = document.getElementById('sta-dist-badge-bg').value;
            var distRadius = document.getElementById('sta-dist-badge-radius').value;
            document.getElementById('sta-dist-badge-radius-val').textContent = distRadius;
            document.querySelectorAll('#prev-dist-badge').forEach(function(el) {
                el.style.background   = distBg;
                el.style.borderRadius = distRadius + 'px';
            });
        }
        </script>
        <?php
    }
}
