<?php

class STA_Admin_Menu {

    /**
     * Menu-icoon: zwemmer op golven. Pad = Material Design "pool"-icoon (Google, Apache-2.0).
     * Als base64-SVG; WordPress kleurt het automatisch mee met het kleurenschema van de admin.
     */
    public static function menu_icon(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#a7aaad">'
             . '<path d="M22 21c-1.11 0-1.73-.37-2.18-.64-.37-.22-.6-.36-1.15-.36-.56 0-.78.13-1.15.36-.46.27-1.08.64-2.19.64-1.11 0-1.73-.37-2.18-.64-.37-.22-.6-.36-1.15-.36-.56 0-.78.13-1.15.36-.46.27-1.08.64-2.19.64-1.11 0-1.73-.37-2.18-.64-.37-.22-.6-.36-1.15-.36-.56 0-.78.13-1.15.36-.45.27-1.07.64-2.18.64v-2c.56 0 .78-.13 1.15-.36.45-.27 1.07-.64 2.18-.64s1.73.37 2.18.64c.37.23.59.36 1.15.36.56 0 .78-.13 1.15-.36.46-.27 1.08-.64 2.19-.64 1.11 0 1.73.37 2.18.64.37.22.6.36 1.15.36s.78-.13 1.15-.36c.45-.27 1.07-.64 2.18-.64s1.73.37 2.18.64c.37.23.59.36 1.15.36v2zm0-4.5c-1.11 0-1.73-.37-2.18-.64-.37-.22-.6-.36-1.15-.36-.56 0-.78.13-1.15.36-.45.27-1.07.64-2.18.64s-1.73-.37-2.18-.64c-.37-.22-.6-.36-1.15-.36-.56 0-.78.13-1.15.36-.45.27-1.07.64-2.18.64s-1.73-.37-2.18-.64c-.37-.22-.6-.36-1.15-.36-.56 0-.78.13-1.15.36-.45.27-1.07.64-2.18.64v-2c.56 0 .78-.13 1.15-.36.45-.27 1.07-.64 2.18-.64s1.73.37 2.18.64c.37.22.6.36 1.15.36.56 0 .78-.13 1.15-.36.45-.27 1.07-.64 2.18-.64s1.73.37 2.18.64c.37.22.6.36 1.15.36s.78-.13 1.15-.36c.45-.27 1.07-.64 2.18-.64s1.73.37 2.18.64c.37.22.6.36 1.15.36v2zM8.67 12c.56 0 .78-.13 1.15-.36.46-.27 1.08-.64 2.19-.64 1.11 0 1.73.37 2.18.64.37.22.6.36 1.15.36s.78-.13 1.15-.36c.12-.07.26-.15.41-.23L10.48 5C8.93 3.45 7.5 2.99 5 3v2.5c1.82-.01 2.89.39 4 1.5l1 1-3.25 3.25c.31.12.56.27.77.39.37.23.59.36 1.15.36z"/>'
             . '<circle cx="16.5" cy="5.5" r="2"/>'
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    public static function register() {
        add_menu_page(
            'WP Splashconnect',
            'WP Splashconnect',
            'edit_posts',
            'swimming-trials-ai',
            [ 'STA_Modules', 'render' ],
            self::menu_icon(),
            30
        );

        // Eerste submenu-item hernoemen van "WP Splashconnect" naar "Overzicht".
        add_submenu_page(
            'swimming-trials-ai',
            'WP Splashconnect – overzicht en modules',
            'Overzicht',
            'edit_posts',
            'swimming-trials-ai',
            [ 'STA_Modules', 'render' ]
        );

        add_submenu_page(
            'swimming-trials-ai',
            'Atleten verrijken',
            'Verrijk atleten',
            'edit_posts',
            'swimming-trials-enrich',
            [ __CLASS__, 'render_enrich_page' ]
        );

        if ( STA_Modules::is_active( 'heroshot' ) ) {
            add_submenu_page(
                'swimming-trials-ai',
                'Heroshot video-koppeling',
                'Heroshot video',
                'manage_options',
                'swimming-trials-heroshot',
                [ 'STA_Admin_Heroshot', 'render' ]
            );
        }

        if ( STA_Modules::is_active( 'sharecard' ) ) {
            add_submenu_page(
                'swimming-trials-ai',
                'Share card – template editor',
                'Share card',
                'manage_options',
                'swimming-trials-sharecard',
                [ 'STA_Admin_Sharecard', 'render' ]
            );
        }
        add_submenu_page(
            'swimming-trials-ai',
            'Instellingen',
            'Instellingen',
            'manage_options',
            'swimming-trials-settings',
            [ __CLASS__, 'render_settings_page' ]
        );

        // Wis Flickr NSID-cache als API-sleutel of gebruikersnaam wijzigt
        add_action( 'update_option_sta_flickr_api_key',  function() { delete_transient( 'sta_flickr_nsid' ); } );
        add_action( 'update_option_sta_flickr_username', function() { delete_transient( 'sta_flickr_nsid' ); } );

        // Sla instellingen op
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function register_settings() {
        register_setting( 'sta_settings', 'sta_anthropic_api_key',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
        // Flickr-velden alleen registreren als de module aan staat; anders zou options.php
        // de (verborgen, dus niet meegestuurde) waarden bij het opslaan wissen.
        if ( STA_Modules::is_active( 'photos' ) ) {
            register_setting( 'sta_settings', 'sta_flickr_api_key',      [ 'sanitize_callback' => 'sanitize_text_field' ] );
            register_setting( 'sta_settings', 'sta_flickr_username',    [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'kjvoverbeeke' ] );
        }
        if ( STA_Modules::is_active( 'heroshot' ) ) {
            register_setting( 'sta_settings', STA_Heroshot_Paths::OPTION, [
                'sanitize_callback' => [ 'STA_Heroshot_Paths', 'sanitize' ],
                'default'           => STA_Heroshot_Paths::DEFAULT,
            ] );
        }
        // Heroshot-sleutel: alleen met module aan; leeg veld behoudt de bestaande waarde (veld wordt nooit voorgevuld).
        if ( STA_Modules::is_active( 'heroshot' ) && ! ( defined( 'STA_HEROSHOT_SECRET' ) && STA_HEROSHOT_SECRET ) ) {
            register_setting( 'sta_settings', 'sta_heroshot_secret', [
                'sanitize_callback' => function ( $value ) {
                    $value = trim( sanitize_text_field( (string) $value ) );
                    return $value !== '' ? $value : (string) get_option( 'sta_heroshot_secret', '' );
                },
            ] );
        }
        register_setting( 'sta_settings', 'sta_serper_api_key',   [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'sta_settings', 'sta_bio_prompt',        [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
        register_setting( 'sta_settings', 'sta_fallback_photo_m',  [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'sta_settings', 'sta_fallback_photo_f',  [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'sta_settings', 'sta_ticker_style',      [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'swimming_trials' ] );
    }

    public static function render_main_or_redirect() {
        wp_redirect( admin_url( 'admin.php?page=sta-swimrankings' ) );
        exit;
    }

    public static function render_enrich_page() {
        $status_filter = sanitize_text_field( $_GET['sta_status'] ?? 'all' );
        $statuses = $status_filter === 'draft' ? [ 'draft' ] : [ 'draft', 'publish' ];

        $athletes = get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <div class="wrap sta-wrap">
            <h1>🤖 Verrijk atleten met AI + Flickr</h1>
            <p>Klik op "Verrijk" om per atleet automatisch een Flickr-foto en AI-bio toe te voegen.<br>
               Of gebruik "Alles verrijken" om alle atleten in één keer te verwerken (dit duurt langer).</p>

            <?php
            $base_url   = admin_url( 'admin.php?page=swimming-trials-enrich' );
            $count_all  = count( get_posts( [ 'post_type' => 'athlete', 'post_status' => ['draft','publish'], 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
            $count_draft = count( get_posts( [ 'post_type' => 'athlete', 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
            ?>
            <ul class="subsubsub" style="margin-bottom:12px;">
                <li><a href="<?php echo $base_url; ?>" <?php echo $status_filter === 'all' ? 'class="current" style="font-weight:700;"' : ''; ?>>Alle atleten <span class="count">(<?php echo $count_all; ?>)</span></a> |</li>
                <li><a href="<?php echo $base_url . '&sta_status=draft'; ?>" <?php echo $status_filter === 'draft' ? 'class="current" style="font-weight:700;"' : ''; ?>>Niet gepubliceerd <span class="count">(<?php echo $count_draft; ?>)</span></a></li>
            </ul>

            <?php if ( empty( $athletes ) ) : ?>
                <div class="notice notice-warning"><p>Geen atleten gevonden.</p></div>
            <?php else : ?>
                <div class="sta-toolbar">
                    <button class="button button-primary" id="sta-enrich-selected-btn" disabled>
                        Verrijk selectie (<span id="sta-selected-count">0</span>)
                    </button>
                    <button class="button button-primary" id="sta-publish-selected-btn" disabled>
                        Publiceer selectie
                    </button>
                    <span class="sta-toolbar-sep">|</span>
                    <button class="button" id="sta-enrich-all-btn">
                        Alles verrijken (<?php echo count( $athletes ); ?>)
                    </button>
                    <button class="button" id="sta-publish-all-btn">
                        Alles publiceren
                    </button>
                    <span class="sta-toolbar-sep">|</span>
                    <label style="font-weight:normal; cursor:pointer;">
                        <input type="checkbox" id="sta-select-all"> Alles selecteren
                    </label>
                    <span class="sta-toolbar-sep">|</span>
                    <label style="font-weight:normal;cursor:pointer;" title="Zoek een Flickr-foto voor elke atleet">
                        <input type="checkbox" id="sta-opt-photo" <?php echo STA_Modules::is_active( 'photos' ) ? 'checked' : 'disabled title="Module Foto-integratie staat uit"'; ?>> Foto zoeken
                    </label>
                    <label style="font-weight:normal;cursor:pointer;margin-left:10px;" title="Genereer een AI-bio voor elke atleet">
                        <input type="checkbox" id="sta-opt-bio" checked> Bio genereren
                    </label>
                </div>

                <div id="sta-batch-progress" style="display:none;">
                    <p>Bezig: <span id="sta-progress-current">0</span> / <span id="sta-progress-total">0</span></p>
                    <div class="sta-progress-bar"><div id="sta-progress-fill" style="width:0%"></div></div>
                </div>

                <!-- Filter bar -->
                <?php
                $all_clubs = [];
                foreach ($athletes as $a) {
                    $c = get_post_meta($a->ID, 'sta_club', true);
                    if ($c) $all_clubs[$c] = true;
                }
                ksort($all_clubs);
                ?>
                <div id="sta-filter-bar" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;padding:10px 12px;background:#f8f9fa;border:1px solid #ddd;border-radius:6px;">
                    <input type="search" id="sta-filter-name" placeholder="Zoek op naam…" style="padding:5px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;width:180px;">
                    <select id="sta-filter-club" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                        <option value="">Alle clubs</option>
                        <?php foreach (array_keys($all_clubs) as $c) : ?>
                        <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="sta-filter-foto" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                        <option value="">Alle foto's</option>
                        <option value="1">✓ Heeft foto</option>
                        <option value="0">— Geen foto</option>
                    </select>
                    <select id="sta-filter-bio" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                        <option value="">Alle bio's</option>
                        <option value="1">✓ Heeft bio</option>
                        <option value="0">— Geen bio</option>
                    </select>
                    <select id="sta-filter-country" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                        <option value="">Alle landen</option>
                        <option value="0">— Geen land</option>
                    </select>
                    <select id="sta-filter-status" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                        <option value="">Alle statussen</option>
                        <option value="publish">Gepubliceerd</option>
                        <option value="draft">Concept</option>
                    </select>
                    <span style="color:#999;font-size:12px;margin-left:4px;">(<span id="sta-visible-count"><?php echo count($athletes); ?></span> zichtbaar)</span>
                    <button type="button" id="sta-filter-reset" class="button" style="padding:4px 10px;font-size:12px;">Reset</button>
                </div>
                <div style="margin-bottom:12px;">
                    <input type="search" id="sta-filter-bio-text" placeholder="Zoek in bio-tekst…" style="padding:5px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;width:100%;box-sizing:border-box;">
                </div>

                <table class="wp-list-table widefat fixed striped" id="sta-athletes-table">
                    <thead>
                        <tr>
                            <th style="width:30px;"></th>
                            <th style="width:36px;" title="Uitgelicht">⭐</th>
                            <th>Naam</th>
                            <th>Club</th>
                            <th style="width:80px;">Foto</th>
                            <th style="width:70px;">Bio</th>
                            <th style="width:70px;">Status</th>
                            <th style="width:220px;">Actie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $athletes as $athlete ) : ?>
                            <?php
                            $has_photo   = has_post_thumbnail( $athlete->ID );
                            $has_bio     = ! empty( get_post_meta( $athlete->ID, 'sta_bio', true ) );
                            $club        = get_post_meta( $athlete->ID, 'sta_club', true );
                            $country     = get_post_meta( $athlete->ID, 'sta_country', true );
                            $uitgelicht  = (bool) get_post_meta( $athlete->ID, 'sta_featured', true );
                            $is_published = $athlete->post_status === 'publish';
                            ?>
                            <tr id="sta-row-<?php echo $athlete->ID; ?>"
                                class="sta-athlete-data-row <?php echo implode( ' ', array_filter( [
                                    $uitgelicht   ? 'sta-is-featured'  : '',
                                    $is_published ? 'sta-is-published' : '',
                                ] ) ); ?>"
                                data-status="<?php echo esc_attr( $athlete->post_status ); ?>"
                                data-name="<?php echo esc_attr( strtolower( $athlete->post_title ) ); ?>"
                                data-club="<?php echo esc_attr( strtolower( $club ) ); ?>"
                                data-country="<?php echo esc_attr( $country ); ?>"
                                data-has-photo="<?php echo $has_photo ? '1' : '0'; ?>"
                                data-has-bio="<?php echo $has_bio ? '1' : '0'; ?>"
                                data-bio-text="<?php echo esc_attr( strtolower( get_post_meta( $athlete->ID, 'sta_bio', true ) ) ); ?>">
                                <td style="text-align:center;">
                                    <input type="checkbox"
                                           class="sta-select-row"
                                           data-id="<?php echo $athlete->ID; ?>"
                                           title="Selecteer">
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox"
                                           class="sta-featured-toggle"
                                           data-id="<?php echo $athlete->ID; ?>"
                                           <?php checked( $uitgelicht ); ?>
                                           title="Uitlichten">
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $athlete->post_title ); ?></strong><br>
                                    <small>
                                        <a href="<?php echo get_edit_post_link( $athlete->ID ); ?>">Bewerken</a>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo get_permalink( $athlete->ID ); ?>" target="_blank">Bekijken</a>
                                        &nbsp;|&nbsp;
                                        <a href="#" class="sta-bio-toggle"
                                           data-id="<?php echo $athlete->ID; ?>"
                                           data-bio="<?php echo esc_attr( get_post_meta( $athlete->ID, 'sta_bio', true ) ); ?>"
                                           data-notes="<?php echo esc_attr( get_post_meta( $athlete->ID, 'sta_notes_public', true ) ); ?>">Bio</a>
                                    </small>
                                </td>
                                <td><?php echo esc_html( $club ?: '—' ); ?></td>
                                <td class="sta-status-foto">
                                    <?php echo $has_photo ? '<span class="sta-ok">✓ Foto</span>' : '<span class="sta-empty">—</span>'; ?>
                                </td>
                                <td class="sta-status-bio">
                                    <?php echo $has_bio ? '<span class="sta-ok">✓ Bio</span>' : '<span class="sta-empty">—</span>'; ?>
                                </td>
                                <td class="sta-status-publish">
                                    <?php if ( $is_published ) : ?>
                                        <span class="sta-ok">✓ Gepubliceerd</span>
                                    <?php else : ?>
                                        <span class="sta-empty">Concept</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button sta-enrich-btn"
                                            data-id="<?php echo $athlete->ID; ?>">
                                        Verrijk
                                    </button>
                                    <button class="button sta-context-btn"
                                            data-id="<?php echo $athlete->ID; ?>"
                                            title="Bekijk de webcontext die naar Claude gestuurd wordt">
                                        Context
                                    </button>
                                    <?php if ( get_option('sta_tournament_tag') ) : ?>
                                    <?php $photos_on = STA_Modules::is_active( 'photos' ); ?>
                                    <button class="button <?php echo $photos_on ? 'sta-flickr-photos-btn' : ''; ?>"
                                            data-id="<?php echo $athlete->ID; ?>"
                                            data-name="<?php echo esc_attr( $athlete->post_title ); ?>"
                                            <?php echo $photos_on ? '' : 'disabled'; ?>
                                            title="<?php echo $photos_on ? "Toernooifoto's zoeken en instellen" : 'Module Foto-integratie staat uit'; ?>">
                                        📷 Foto's
                                    </button>
                                    <?php endif; ?>
                                    <button class="button sta-publish-btn"
                                            data-id="<?php echo $athlete->ID; ?>"
                                            <?php if ( $is_published ) echo 'style="display:none;"'; ?>>
                                        Publiceer
                                    </button>
                                    <span class="sta-row-status"></span>
                                </td>
                            </tr>
                            <tr id="sta-bio-row-<?php echo $athlete->ID; ?>" class="sta-bio-quick-edit" style="display:none;">
                                <td colspan="8" style="padding:0;">
                                    <div style="padding:12px 16px;background:#f9f9f9;border-top:1px solid #e0e0e0;">
                                        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                                            <div style="flex:1;min-width:280px;">
                                                <label style="font-weight:600;display:block;margin-bottom:4px;">Bio</label>
                                                <textarea id="sta-bio-text-<?php echo $athlete->ID; ?>"
                                                          rows="6"
                                                          style="width:100%;font-size:13px;resize:vertical;"><?php echo esc_textarea( get_post_meta( $athlete->ID, 'sta_bio', true ) ); ?></textarea>
                                            </div>
                                            <div style="flex:1;min-width:220px;">
                                                <label style="font-weight:600;display:block;margin-bottom:4px;">Publieke speaker notes</label>
                                                <textarea id="sta-notes-text-<?php echo $athlete->ID; ?>"
                                                          rows="6"
                                                          style="width:100%;font-size:13px;resize:vertical;"><?php echo esc_textarea( get_post_meta( $athlete->ID, 'sta_notes_public', true ) ); ?></textarea>
                                            </div>
                                        </div>
                                        <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                                            <button class="button button-primary sta-save-bio-btn"
                                                    data-id="<?php echo $athlete->ID; ?>">Opslaan</button>
                                            <button class="button sta-regen-bio-btn"
                                                    data-id="<?php echo $athlete->ID; ?>">Bio opnieuw genereren</button>
                                            <span class="sta-bio-status" style="font-style:italic;color:#666;"></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Flickr foto-picker modal -->
                <div id="sta-flickr-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.6);align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:8px;padding:24px;max-width:780px;width:90%;max-height:90vh;overflow-y:auto;position:relative;">
                        <button id="sta-flickr-modal-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#666;">✕</button>
                        <h2 id="sta-flickr-modal-title" style="margin:0 0 16px;font-size:18px;">Toernooifoto's</h2>
                        <p style="color:#666;font-size:13px;margin-bottom:16px;">Selecteer de foto's die je wilt bewaren (max 6). Klik op een foto om hem te (de)selecteren.</p>
                        <div id="sta-flickr-modal-body" style="min-height:80px;text-align:center;color:#999;">Laden...</div>
                        <div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
                            <button id="sta-flickr-save-btn" class="button button-primary" disabled>Bewaar selectie (<span id="sta-flickr-sel-count">0</span>)</button>
                            <span id="sta-flickr-save-status" style="font-style:italic;color:#666;font-size:13px;"></span>
                        </div>
                    </div>
                </div>

                <script>
                window._staAthleteIds = <?php echo wp_json_encode( wp_list_pluck( $athletes, 'ID' ) ); ?>;
                var _staNonce = '<?php echo esc_js( wp_create_nonce( 'sta_nonce' ) ); ?>';

                // ── Flickr foto-picker ────────────────────────────────────────
                (function() {
                    var modal      = document.getElementById('sta-flickr-modal');
                    var modalBody  = document.getElementById('sta-flickr-modal-body');
                    var modalTitle = document.getElementById('sta-flickr-modal-title');
                    var saveBtn    = document.getElementById('sta-flickr-save-btn');
                    var saveStatus = document.getElementById('sta-flickr-save-status');
                    var selCount   = document.getElementById('sta-flickr-sel-count');
                    var selected   = {};
                    var currentPostId = null;

                    document.getElementById('sta-flickr-modal-close').addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) modal.style.display = 'none';
                    });

                    function updateSaveBtn() {
                        var count = Object.keys(selected).filter(function(k) { return selected[k]; }).length;
                        selCount.textContent = count;
                        saveBtn.disabled = count === 0;
                    }

                    document.addEventListener('click', function(e) {
                        var btn = e.target.closest('.sta-flickr-photos-btn');
                        if (!btn) return;
                        currentPostId = btn.dataset.id;
                        var name = btn.dataset.name;
                        modalTitle.textContent = 'Toernooifoto\'s · ' + name;
                        modalBody.innerHTML = '<div style="padding:20px;color:#999;">Flickr doorzoeken...</div>';
                        saveStatus.textContent = '';
                        saveBtn.disabled = true;
                        selCount.textContent = '0';
                        selected = {};
                        modal.style.display = 'flex';

                        var fd = new FormData();
                        fd.append('action', 'sta_flickr_search_athlete');
                        fd.append('nonce', _staNonce);
                        fd.append('post_id', currentPostId);
                        fetch(ajaxurl, {method:'POST', body:fd}).then(function(r) { return r.json(); }).then(function(res) {
                            if (!res.success) { modalBody.innerHTML = '<p style="color:red;">Fout: ' + (res.data || 'onbekend') + '</p>'; return; }
                            var photos = res.data.photos;
                            if (!photos || photos.length === 0) {
                                modalBody.innerHTML = '<p style="color:#999;padding:20px;">Geen foto\'s gevonden op Flickr voor deze atleet.</p>';
                                return;
                            }
                            // Pre-select: current stored flickr_ids
                            var fd2 = new FormData();
                            fd2.append('action', 'sta_get_tournament_photos');
                            fd2.append('nonce', _staNonce);
                            fd2.append('post_id', currentPostId);

                            var grid = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;">';
                            photos.forEach(function(p) {
                                selected[p.flickr_id] = false;
                                grid += '<div class="sta-flickr-thumb" data-id="' + p.flickr_id + '" style="cursor:pointer;border-radius:6px;overflow:hidden;border:3px solid transparent;position:relative;" title="' + p.title + '">'
                                    + '<img src="' + p.thumb + '" style="width:100%;aspect-ratio:1;object-fit:cover;display:block;">'
                                    + '<div class="sta-flickr-check" style="display:none;position:absolute;top:4px;right:4px;background:#2271b1;color:#fff;border-radius:50%;width:22px;height:22px;font-size:14px;text-align:center;line-height:22px;font-weight:700;">✓</div>'
                                    + '</div>';
                            });
                            grid += '</div>';
                            modalBody.innerHTML = grid;

                            modalBody.addEventListener('click', function(e) {
                                var thumb = e.target.closest('.sta-flickr-thumb');
                                if (!thumb) return;
                                var id = thumb.dataset.id;
                                selected[id] = !selected[id];
                                thumb.style.borderColor = selected[id] ? '#2271b1' : 'transparent';
                                thumb.querySelector('.sta-flickr-check').style.display = selected[id] ? 'block' : 'none';
                                updateSaveBtn();
                            });
                        }).catch(function() {
                            modalBody.innerHTML = '<p style="color:red;">Verbindingsfout.</p>';
                        });
                    });

                    saveBtn.addEventListener('click', function() {
                        var ids = Object.keys(selected).filter(function(k) { return selected[k]; });
                        if (!ids.length) return;
                        saveBtn.disabled = true;
                        saveStatus.textContent = 'Opslaan...';
                        var fd = new FormData();
                        fd.append('action', 'sta_save_tournament_photos');
                        fd.append('nonce', _staNonce);
                        fd.append('post_id', currentPostId);
                        fd.append('flickr_ids', ids.join(','));
                        fetch(ajaxurl, {method:'POST', body:fd}).then(function(r) { return r.json(); }).then(function(res) {
                            if (res.success) {
                                saveStatus.textContent = '✓ ' + res.data.saved + ' foto\'s opgeslagen';
                                setTimeout(function() { modal.style.display = 'none'; }, 1200);
                            } else {
                                saveStatus.textContent = 'Fout: ' + (res.data || 'onbekend');
                                saveBtn.disabled = false;
                            }
                        });
                    });
                })();

                // ── Tabelfilters ──────────────────────────────────────────────
                (function() {
                    var filters = { name: '', club: '', foto: '', bio: '', status: '', bioText: '', country: '' };

                    function applyFilters() {
                        var visible = 0;
                        document.querySelectorAll('#sta-athletes-table tbody tr.sta-athlete-data-row').forEach(function(tr) {
                            var ok = true;
                            if (filters.name    && tr.dataset.name.indexOf(filters.name) === -1) ok = false;
                            if (filters.club    && tr.dataset.club.indexOf(filters.club) === -1) ok = false;
                            if (filters.foto    && tr.dataset.hasPhoto !== filters.foto) ok = false;
                            if (filters.bio     && tr.dataset.hasBio   !== filters.bio)  ok = false;
                            if (filters.status  && tr.dataset.status   !== filters.status) ok = false;
                            if (filters.bioText && tr.dataset.bioText.indexOf(filters.bioText) === -1) ok = false;
                            if (filters.country === '0' && tr.dataset.country) ok = false;

                            tr.style.display = ok ? '' : 'none';
                            // bio-edit rij ook verbergen als de hoofdrij verborgen is
                            var bioRow = document.getElementById('sta-bio-row-' + tr.id.replace('sta-row-', ''));
                            if (bioRow) bioRow.style.display = (ok && bioRow.dataset.open === '1') ? '' : 'none';
                            if (ok) visible++;
                        });
                        var el = document.getElementById('sta-visible-count');
                        if (el) el.textContent = visible;
                    }

                    function bind(id, key, transform) {
                        var el = document.getElementById(id);
                        if (!el) return;
                        el.addEventListener('input', function() {
                            filters[key] = transform ? transform(this.value) : this.value;
                            applyFilters();
                        });
                    }

                    bind('sta-filter-name',     'name',    function(v){ return v.toLowerCase(); });
                    bind('sta-filter-club',     'club',    function(v){ return v.toLowerCase(); });
                    bind('sta-filter-foto',     'foto',    null);
                    bind('sta-filter-bio',      'bio',     null);
                    bind('sta-filter-status',   'status',  null);
                    bind('sta-filter-bio-text', 'bioText', function(v){ return v.toLowerCase(); });

                    // select uses 'change', not 'input'
                    var selectMap = { 'sta-filter-club':'club','sta-filter-foto':'foto','sta-filter-bio':'bio','sta-filter-status':'status','sta-filter-country':'country' };
                    Object.keys(selectMap).forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) el.addEventListener('change', function() {
                            filters[selectMap[id]] = this.value;
                            applyFilters();
                        });
                    });

                    document.getElementById('sta-filter-reset') && document.getElementById('sta-filter-reset').addEventListener('click', function() {
                        ['sta-filter-name','sta-filter-bio-text'].forEach(function(id){ var e=document.getElementById(id); if(e) e.value=''; });
                        ['sta-filter-club','sta-filter-foto','sta-filter-bio','sta-filter-status','sta-filter-country'].forEach(function(id){ var e=document.getElementById(id); if(e) e.value=''; });
                        filters = { name:'', club:'', foto:'', bio:'', status:'', bioText:'', country:'' };
                        applyFilters();
                    });
                })();

                // Bio quick-edit toggle
                document.addEventListener('click', function(e) {
                    var toggle = e.target.closest('.sta-bio-toggle');
                    if (!toggle) return;
                    e.preventDefault();
                    var id  = toggle.dataset.id;
                    var row = document.getElementById('sta-bio-row-' + id);
                    if (!row) return;
                    row.style.display = row.style.display === 'none' ? '' : 'none';
                });

                // Bio opslaan
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('.sta-save-bio-btn');
                    if (!btn) return;
                    var id     = btn.dataset.id;
                    var bio    = document.getElementById('sta-bio-text-' + id).value;
                    var notes  = document.getElementById('sta-notes-text-' + id).value;
                    var status = btn.closest('div').querySelector('.sta-bio-status');
                    btn.disabled = true;
                    status.textContent = 'Opslaan…';
                    var fd = new FormData();
                    fd.append('action', 'sta_save_bio'); fd.append('nonce', _staNonce);
                    fd.append('post_id', id); fd.append('bio', bio); fd.append('notes', notes);
                    fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(function(res) {
                        status.textContent = res.success ? '✓ Opgeslagen' : '✗ ' + (res.data || 'Fout');
                    }).catch(function() { status.textContent = '✗ Verzoek mislukt'; })
                    .finally(function() { btn.disabled = false; });
                });

                // Bio opnieuw genereren
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('.sta-regen-bio-btn');
                    if (!btn) return;
                    var id     = btn.dataset.id;
                    var status = btn.closest('div').querySelector('.sta-bio-status');
                    var notes  = document.getElementById('sta-notes-text-' + id).value;
                    btn.disabled = true;
                    status.textContent = 'Bio genereren…';
                    var fd = new FormData();
                    fd.append('action', 'sta_generate_bio'); fd.append('nonce', _staNonce);
                    fd.append('post_id', id); fd.append('notes', notes);
                    fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(function(res) {
                        if (res.success && res.data && res.data.bio) {
                            document.getElementById('sta-bio-text-' + id).value = res.data.bio;
                            status.textContent = '✓ Bio gegenereerd';
                        } else {
                            status.textContent = '✗ ' + (res.data || 'Fout');
                        }
                    }).catch(function() { status.textContent = '✗ Verzoek mislukt'; })
                    .finally(function() { btn.disabled = false; });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_settings_page() {
        wp_enqueue_media();
        $saved = isset( $_GET['settings-updated'] );
        ?>
        <div class="wrap sta-wrap">
            <h1>⚙️ Instellingen</h1>
            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'sta_settings' ); ?>

                <div class="sta-card">
                    <h2>API-sleutels</h2>

                    <table class="form-table">
                        <tr>
                            <th><label for="sta_anthropic_api_key">Anthropic API-sleutel</label></th>
                            <td>
                                <input type="password" id="sta_anthropic_api_key" name="sta_anthropic_api_key"
                                       value="<?php echo esc_attr( get_option( 'sta_anthropic_api_key' ) ); ?>"
                                       class="regular-text" autocomplete="off">
                                <p class="description">Verplicht. Haal je sleutel op via <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></p>
                            </td>
                        </tr>
                        <?php if ( STA_Modules::is_active( 'photos' ) ) : ?>
                        <tr>
                            <th><label for="sta_flickr_api_key">Flickr API-sleutel</label></th>
                            <td>
                                <input type="password" id="sta_flickr_api_key" name="sta_flickr_api_key"
                                       value="<?php echo esc_attr( get_option( 'sta_flickr_api_key' ) ); ?>"
                                       class="regular-text" autocomplete="off">
                                <p class="description">Verplicht voor foto's. Maak een app aan via <a href="https://www.flickr.com/services/apps/create/" target="_blank">flickr.com/services/apps</a> — alleen de API key is nodig, niet de secret.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sta_flickr_username">Flickr gebruikersnaam fotograaf</label></th>
                            <td>
                                <input type="text" id="sta_flickr_username" name="sta_flickr_username"
                                       value="<?php echo esc_attr( get_option( 'sta_flickr_username', 'kjvoverbeeke' ) ); ?>"
                                       class="regular-text">
                                <p class="description">Flickr-gebruikersnaam van de fotograaf. Foto's uit alle toernooien in hun stream worden doorzocht.</p>
                                <p style="margin-top:8px;">
                                    <button type="button" id="sta-flickr-test" class="button">
                                        Verbinding testen
                                    </button>
                                    <span id="sta-flickr-test-result" style="margin-left:12px;font-style:italic;"></span>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><label for="sta_serper_api_key">Serper.dev API-sleutel <em>(optioneel)</em></label></th>
                            <td>
                                <input type="password" id="sta_serper_api_key" name="sta_serper_api_key"
                                       value="<?php echo esc_attr( get_option( 'sta_serper_api_key' ) ); ?>"
                                       class="regular-text" autocomplete="off">
                                <p class="description">Optioneel, verbetert de kwaliteit van bio's met webzoekresultaten. Via <a href="https://serper.dev" target="_blank">serper.dev</a> (gratis tier beschikbaar)</p>
                                <p style="margin-top:8px;">
                                    <button type="button" id="sta-serper-test" class="button">
                                        Verbinding testen
                                    </button>
                                    <span id="sta-serper-test-result" style="margin-left:12px;font-style:italic;"></span>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if ( STA_Modules::is_active( 'heroshot' ) ) :
                    $hs_const = defined( 'STA_HEROSHOT_SECRET' ) && STA_HEROSHOT_SECRET;
                    $hs_set   = $hs_const || get_option( 'sta_heroshot_secret', '' ) !== '';
                    ?>
                <div class="sta-card">
                    <h2>Heroshot</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="sta_heroshot_dir">Map voor Heroshot-bestanden</label></th>
                            <td>
                                <code><?php echo esc_html( untrailingslashit( ABSPATH ) ); ?>/</code>
                                <input type="text" id="sta_heroshot_dir" name="sta_heroshot_dir"
                                       value="<?php echo esc_attr( STA_Heroshot_Paths::relative() ); ?>"
                                       class="regular-text" placeholder="<?php echo esc_attr( STA_Heroshot_Paths::DEFAULT ); ?>">
                                <p class="description">
                                    Pad ten opzichte van de hoofdmap van de site. Alleen letters, cijfers, <code>-</code> en <code>_</code>; submappen met <code>/</code> (bijvoorbeeld <code>media/heroshot</code>).
                                    Daarbinnen gebruikt de plugin <code>videos/</code> (de mp4-bestanden) en <code>data/</code> (koppelbestanden).
                                </p>
                                <?php
                                $hs_videos = STA_Heroshot_Paths::dir( 'videos' );
                                $hs_data   = STA_Heroshot_Paths::dir( 'data' );
                                $hs_n      = is_dir( $hs_videos ) ? count( glob( $hs_videos . '*.mp4' ) ?: [] ) : 0;
                                ?>
                                <p class="description">
                                    Huidige status:
                                    <?php if ( is_dir( $hs_videos ) ) : ?>
                                        <span style="color:#1a7f37;">✓ <code>videos/</code> gevonden (<?php echo (int) $hs_n; ?> mp4-bestanden)</span>
                                    <?php else : ?>
                                        <span style="color:#b32d2e;">✗ <code>videos/</code> bestaat niet</span>
                                    <?php endif; ?>
                                    ·
                                    <?php if ( is_dir( $hs_data ) ) : ?>
                                        <span style="color:#1a7f37;">✓ <code>data/</code> gevonden</span>
                                    <?php else : ?>
                                        <span style="color:#996800;"><code>data/</code> wordt aangemaakt bij het koppelen</span>
                                    <?php endif; ?>
                                </p>
                                <p class="description"><strong>Verplaats of hernoem de map zelf op de server</strong> als je dit wijzigt; de plugin verplaatst geen bestanden. Staat de nieuwe map er niet, dan verdwijnen de video's van de atletenpagina's.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sta_heroshot_secret">Heroshot-sleutel</label></th>
                            <td>
                                <?php if ( $hs_const ) : ?>
                                    <p><strong>Ingesteld via wp-config.php</strong> (<code>STA_HEROSHOT_SECRET</code>). Dit veld is dan niet nodig.</p>
                                <?php else : ?>
                                    <input type="password" id="sta_heroshot_secret" name="sta_heroshot_secret"
                                           value="" class="regular-text" autocomplete="new-password"
                                           placeholder="<?php echo $hs_set ? '•••••••• (ingesteld, leeg laten om te behouden)' : 'Nog niet ingesteld'; ?>">
                                    <p class="description">Geheime tekst waarmee de bestandsnamen van Heroshot-video's worden afgeleid. Kies een lange, willekeurige tekst en bewaar hem veilig. <strong>Wijzig je hem, draai dan opnieuw "Atleten koppelen" en hernoem de bestaande video's</strong>, anders vinden atleten hun video niet meer.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <div class="sta-card">
                    <h2>Bio-prompt</h2>
                    <p>De prompt die naar Claude gestuurd wordt om een bio te genereren. Klik op een variabele om hem in te voegen op de cursorpositie.</p>
                    <p style="margin-top:6px;">
                        <?php
                        $vars = [
                            '{naam}'          => 'Naam van de atleet',
                            '{events}'        => 'Inschrijvingen (afstanden)',
                            '{club}'          => 'Zwemclub',
                            '{speaker_notes}' => 'Publieke speaker notes',
                            '{context}'       => 'Webzoekresultaten (Serper)',
                            '{today}'         => 'Huidige datum',
                        ];
                        foreach ( $vars as $var => $desc ) :
                        ?>
                        <code class="sta-prompt-var" title="<?php echo esc_attr( $desc ); ?>"
                              style="cursor:pointer;margin-right:6px;margin-bottom:4px;display:inline-block;padding:2px 7px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:3px;"
                              data-var="<?php echo esc_attr( $var ); ?>"><?php echo esc_html( $var ); ?></code>
                        <?php endforeach; ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th><label for="sta_bio_prompt">Prompt</label></th>
                            <td>
                                <textarea id="sta_bio_prompt" name="sta_bio_prompt"
                                          rows="14" class="large-text" style="font-family:monospace;font-size:12px;"><?php
                                    echo esc_textarea( get_option( 'sta_bio_prompt', STA_Bio_Generator::DEFAULT_PROMPT ) );
                                ?></textarea>
                                <p class="description">
                                    <a href="#" id="sta-reset-prompt">Standaard-prompt herstellen</a>
                                </p>
                                <script>
                                document.getElementById('sta-reset-prompt').addEventListener('click', function(e) {
                                    e.preventDefault();
                                    if (confirm('Wil je de prompt terugzetten naar de standaard?')) {
                                        document.getElementById('sta_bio_prompt').value = <?php echo wp_json_encode( STA_Bio_Generator::DEFAULT_PROMPT ); ?>;
                                    }
                                });
                                document.querySelectorAll('.sta-prompt-var').forEach(function(chip) {
                                    chip.addEventListener('click', function() {
                                        var ta  = document.getElementById('sta_bio_prompt');
                                        var val = ta.value;
                                        var s   = ta.selectionStart, e2 = ta.selectionEnd;
                                        ta.value = val.slice(0, s) + chip.dataset.var + val.slice(e2);
                                        ta.selectionStart = ta.selectionEnd = s + chip.dataset.var.length;
                                        ta.focus();
                                    });
                                });
                                </script>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sta-card">
                    <h2>Fallback foto's</h2>
                    <p>Wordt gebruikt als er geen Flickr-foto gevonden wordt, en als 6e optie in de fotokiezer.</p>
                    <table class="form-table">
                        <?php foreach ( [ 'm' => 'Mannen', 'f' => 'Vrouwen' ] as $gender => $label ) :
                            $opt_key     = 'sta_fallback_photo_' . $gender;
                            $attachment_id = intval( get_option( $opt_key, 0 ) );
                            $thumb_url   = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
                        ?>
                        <tr>
                            <th><label><?php echo $label; ?></label></th>
                            <td>
                                <input type="hidden" name="<?php echo $opt_key; ?>" id="<?php echo $opt_key; ?>" value="<?php echo $attachment_id; ?>">
                                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                                    <div id="<?php echo $opt_key; ?>_preview" style="width:80px;height:80px;background:#e5e7eb;border-radius:4px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                                        <?php if ( $thumb_url ) : ?>
                                            <img src="<?php echo esc_url( $thumb_url ); ?>" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else : ?>
                                            <span style="color:#999;font-size:11px;">Geen foto</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button type="button" class="button sta-media-upload" data-target="<?php echo $opt_key; ?>">
                                            Afbeelding kiezen
                                        </button>
                                        <?php if ( $attachment_id ) : ?>
                                            <button type="button" class="button sta-media-remove" data-target="<?php echo $opt_key; ?>" style="margin-left:6px;">
                                                Verwijderen
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <script>
                    jQuery(function($) {
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

                        $('#sta-flickr-test').on('click', function() {
                            var $btn    = $(this);
                            var $result = $('#sta-flickr-test-result');
                            var apiKey  = $('#sta_flickr_api_key').val();
                            var username = $('#sta_flickr_username').val();
                            $btn.prop('disabled', true);
                            $result.text('Verbinding testen…').css('color', '');
                            $.post(ajaxurl, {
                                action:   'sta_flickr_test',
                                api_key:  apiKey,
                                username: username,
                                _wpnonce: '<?php echo wp_create_nonce( 'sta_flickr_test' ); ?>',
                            }, function(r) {
                                if (r.success) {
                                    $result.text('✓ ' + r.data).css('color', 'green');
                                } else {
                                    $result.text('✗ ' + r.data).css('color', 'red');
                                }
                            }).fail(function() {
                                $result.text('✗ Verzoek mislukt').css('color', 'red');
                            }).always(function() {
                                $btn.prop('disabled', false);
                            });
                        });

                        $('#sta-serper-test').on('click', function() {
                            var $btn    = $(this);
                            var $result = $('#sta-serper-test-result');
                            var apiKey  = $('#sta_serper_api_key').val();
                            $btn.prop('disabled', true);
                            $result.text('Verbinding testen…').css('color', '');
                            $.post(ajaxurl, {
                                action:   'sta_serper_test',
                                api_key:  apiKey,
                                query:    'Caeleb Dressel swimmer',
                                _wpnonce: '<?php echo wp_create_nonce( 'sta_serper_test' ); ?>',
                            }, function(r) {
                                if (r.success) {
                                    $result.text('✓ ' + r.data).css('color', 'green');
                                } else {
                                    $result.text('✗ ' + r.data).css('color', 'red');
                                }
                            }).fail(function() {
                                $result.text('✗ Verzoek mislukt').css('color', 'red');
                            }).always(function() {
                                $btn.prop('disabled', false);
                            });
                        });
                    });
                    </script>
                </div>

                <div class="sta-card">
                <h2>Ticker stijl</h2>
                <p>Kies de visuele stijl van de <code>[sta_ticker]</code> shortcode.</p>
                <table class="form-table">
                    <tr>
                        <th><label>Preset</label></th>
                        <td>
                            <?php
                            $current_style = get_option( 'sta_ticker_style', 'swimming_trials' );
                            $presets = [
                                'swimming_trials' => 'Swimming Trials',
                                'rotterdam'       => 'Rotterdam',
                            ];
                            foreach ( $presets as $value => $label ) : ?>
                                <label style="display:inline-flex;align-items:center;gap:8px;margin-right:24px;cursor:pointer;">
                                    <input type="radio"
                                           name="sta_ticker_style"
                                           value="<?php echo esc_attr( $value ); ?>"
                                           <?php checked( $current_style, $value ); ?>>
                                    <strong><?php echo esc_html( $label ); ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>

                <?php submit_button( 'Instellingen opslaan' ); ?>
            </form>

            <div class="sta-card">
                <h2>Shortcodes</h2>
                <p>Voeg deze shortcodes in op elke pagina of widget.</p>

                <table class="widefat" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:36%">Shortcode</th>
                            <th>Beschrijving</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[sta_ticker]</code></td>
                            <td>Scrollende ticker met uitgelichte atleten. Optioneel: <code>link="/atleten/"</code></td>
                        </tr>
                        <tr>
                            <td><code>[sta_featured]</code></td>
                            <td>Fotogrid van uitgelichte atleten (ronde foto's, vlag + naam). Optioneel: <code>columns="5"</code>, <code>link="/atleten/"</code></td>
                        </tr>
                        <tr>
                            <td><code>[sta_featured country="NL"]</code></td>
                            <td>Zelfde als hierboven, maar gefilterd op landcode (bijv. <code>NL</code>, <code>BE</code>, <code>DE</code>). Combineerbaar met <code>columns</code>.</td>
                        </tr>
                        <tr>
                            <td><code>[sta_program]</code></td>
                            <td>Volledig wedstrijdprogramma per dag (tabbladen) met sessions, events en starttijden. Klikbaar naar afstandspagina's. Starttijden beheer je via <strong>Programma</strong> in het menu.</td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top:16px;color:#666;font-size:13px;">
                    Atleten verschijnen in de widgets als "Uitgelicht" aangevinkt is op hun profielpagina.
                </p>
            </div>
        </div>
        <?php
    }
}
