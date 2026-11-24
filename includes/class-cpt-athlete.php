<?php

class STA_CPT_Athlete {

    const POST_TYPE = 'athlete';

    // ISO-3166-1 alpha-2 → [vlag emoji, naam in het Nederlands]
    const COUNTRIES = [
        'AF' => ['🇦🇫', 'Afghanistan'],
        'AL' => ['🇦🇱', 'Albanië'],
        'DZ' => ['🇩🇿', 'Algerije'],
        'AD' => ['🇦🇩', 'Andorra'],
        'AO' => ['🇦🇴', 'Angola'],
        'AG' => ['🇦🇬', 'Antigua en Barbuda'],
        'AR' => ['🇦🇷', 'Argentinië'],
        'AM' => ['🇦🇲', 'Armenië'],
        'AU' => ['🇦🇺', 'Australië'],
        'AT' => ['🇦🇹', 'Oostenrijk'],
        'AW' => ['🇦🇼', 'Aruba'],
        'AZ' => ['🇦🇿', 'Azerbeidzjan'],
        'BS' => ['🇧🇸', 'Bahama\'s'],
        'BH' => ['🇧🇭', 'Bahrein'],
        'BD' => ['🇧🇩', 'Bangladesh'],
        'BB' => ['🇧🇧', 'Barbados'],
        'BY' => ['🇧🇾', 'Wit-Rusland'],
        'BE' => ['🇧🇪', 'België'],
        'BZ' => ['🇧🇿', 'Belize'],
        'BJ' => ['🇧🇯', 'Benin'],
        'BT' => ['🇧🇹', 'Bhutan'],
        'BO' => ['🇧🇴', 'Bolivia'],
        'BA' => ['🇧🇦', 'Bosnië-Herzegovina'],
        'BW' => ['🇧🇼', 'Botswana'],
        'BR' => ['🇧🇷', 'Brazilië'],
        'BN' => ['🇧🇳', 'Brunei'],
        'BG' => ['🇧🇬', 'Bulgarije'],
        'BF' => ['🇧🇫', 'Burkina Faso'],
        'BI' => ['🇧🇮', 'Burundi'],
        'CV' => ['🇨🇻', 'Kaapverdië'],
        'KH' => ['🇰🇭', 'Cambodja'],
        'CM' => ['🇨🇲', 'Kameroen'],
        'CA' => ['🇨🇦', 'Canada'],
        'CF' => ['🇨🇫', 'Centraal-Afrikaanse Republiek'],
        'TD' => ['🇹🇩', 'Tsjaad'],
        'CL' => ['🇨🇱', 'Chili'],
        'CN' => ['🇨🇳', 'China'],
        'CO' => ['🇨🇴', 'Colombia'],
        'KM' => ['🇰🇲', 'Comoren'],
        'CG' => ['🇨🇬', 'Congo'],
        'CD' => ['🇨🇩', 'Congo (DR)'],
        'CR' => ['🇨🇷', 'Costa Rica'],
        'HR' => ['🇭🇷', 'Kroatië'],
        'CU' => ['🇨🇺', 'Cuba'],
        'CY' => ['🇨🇾', 'Cyprus'],
        'CZ' => ['🇨🇿', 'Tsjechië'],
        'DK' => ['🇩🇰', 'Denemarken'],
        'DJ' => ['🇩🇯', 'Djibouti'],
        'DM' => ['🇩🇲', 'Dominica'],
        'DO' => ['🇩🇴', 'Dominicaanse Republiek'],
        'EC' => ['🇪🇨', 'Ecuador'],
        'EG' => ['🇪🇬', 'Egypte'],
        'SV' => ['🇸🇻', 'El Salvador'],
        'GQ' => ['🇬🇶', 'Equatoriaal-Guinea'],
        'ER' => ['🇪🇷', 'Eritrea'],
        'EE' => ['🇪🇪', 'Estland'],
        'SZ' => ['🇸🇿', 'Eswatini'],
        'ET' => ['🇪🇹', 'Ethiopië'],
        'FJ' => ['🇫🇯', 'Fiji'],
        'FI' => ['🇫🇮', 'Finland'],
        'FR' => ['🇫🇷', 'Frankrijk'],
        'GA' => ['🇬🇦', 'Gabon'],
        'GM' => ['🇬🇲', 'Gambia'],
        'GE' => ['🇬🇪', 'Georgië'],
        'DE' => ['🇩🇪', 'Duitsland'],
        'GH' => ['🇬🇭', 'Ghana'],
        'GR' => ['🇬🇷', 'Griekenland'],
        'GD' => ['🇬🇩', 'Grenada'],
        'GT' => ['🇬🇹', 'Guatemala'],
        'GN' => ['🇬🇳', 'Guinee'],
        'GW' => ['🇬🇼', 'Guinee-Bissau'],
        'GY' => ['🇬🇾', 'Guyana'],
        'HT' => ['🇭🇹', 'Haïti'],
        'HN' => ['🇭🇳', 'Honduras'],
        'HU' => ['🇭🇺', 'Hongarije'],
        'IS' => ['🇮🇸', 'IJsland'],
        'IN' => ['🇮🇳', 'India'],
        'ID' => ['🇮🇩', 'Indonesië'],
        'IR' => ['🇮🇷', 'Iran'],
        'IQ' => ['🇮🇶', 'Irak'],
        'IE' => ['🇮🇪', 'Ierland'],
        'IL' => ['🇮🇱', 'Israël'],
        'IT' => ['🇮🇹', 'Italië'],
        'JM' => ['🇯🇲', 'Jamaica'],
        'JP' => ['🇯🇵', 'Japan'],
        'JO' => ['🇯🇴', 'Jordanië'],
        'KZ' => ['🇰🇿', 'Kazachstan'],
        'KE' => ['🇰🇪', 'Kenia'],
        'KI' => ['🇰🇮', 'Kiribati'],
        'KP' => ['🇰🇵', 'Noord-Korea'],
        'KR' => ['🇰🇷', 'Zuid-Korea'],
        'KW' => ['🇰🇼', 'Koeweit'],
        'KG' => ['🇰🇬', 'Kirgizstan'],
        'LA' => ['🇱🇦', 'Laos'],
        'LV' => ['🇱🇻', 'Letland'],
        'LB' => ['🇱🇧', 'Libanon'],
        'LS' => ['🇱🇸', 'Lesotho'],
        'LR' => ['🇱🇷', 'Liberia'],
        'LY' => ['🇱🇾', 'Libië'],
        'LI' => ['🇱🇮', 'Liechtenstein'],
        'LT' => ['🇱🇹', 'Litouwen'],
        'LU' => ['🇱🇺', 'Luxemburg'],
        'MG' => ['🇲🇬', 'Madagaskar'],
        'MW' => ['🇲🇼', 'Malawi'],
        'MY' => ['🇲🇾', 'Maleisië'],
        'MV' => ['🇲🇻', 'Maldiven'],
        'ML' => ['🇲🇱', 'Mali'],
        'MT' => ['🇲🇹', 'Malta'],
        'MH' => ['🇲🇭', 'Marshalleilanden'],
        'MR' => ['🇲🇷', 'Mauritanië'],
        'MU' => ['🇲🇺', 'Mauritius'],
        'MX' => ['🇲🇽', 'Mexico'],
        'FM' => ['🇫🇲', 'Micronesië'],
        'MD' => ['🇲🇩', 'Moldavië'],
        'MC' => ['🇲🇨', 'Monaco'],
        'MN' => ['🇲🇳', 'Mongolië'],
        'ME' => ['🇲🇪', 'Montenegro'],
        'MA' => ['🇲🇦', 'Marokko'],
        'MZ' => ['🇲🇿', 'Mozambique'],
        'MM' => ['🇲🇲', 'Myanmar'],
        'NA' => ['🇳🇦', 'Namibië'],
        'NR' => ['🇳🇷', 'Nauru'],
        'NP' => ['🇳🇵', 'Nepal'],
        'NL' => ['🇳🇱', 'Nederland'],
        'NZ' => ['🇳🇿', 'Nieuw-Zeeland'],
        'NI' => ['🇳🇮', 'Nicaragua'],
        'NE' => ['🇳🇪', 'Niger'],
        'NG' => ['🇳🇬', 'Nigeria'],
        'MK' => ['🇲🇰', 'Noord-Macedonië'],
        'NO' => ['🇳🇴', 'Noorwegen'],
        'OM' => ['🇴🇲', 'Oman'],
        'PK' => ['🇵🇰', 'Pakistan'],
        'PW' => ['🇵🇼', 'Palau'],
        'PA' => ['🇵🇦', 'Panama'],
        'PG' => ['🇵🇬', 'Papoea-Nieuw-Guinea'],
        'PY' => ['🇵🇾', 'Paraguay'],
        'PE' => ['🇵🇪', 'Peru'],
        'PH' => ['🇵🇭', 'Filipijnen'],
        'PL' => ['🇵🇱', 'Polen'],
        'PT' => ['🇵🇹', 'Portugal'],
        'QA' => ['🇶🇦', 'Qatar'],
        'RO' => ['🇷🇴', 'Roemenië'],
        'RU' => ['🇷🇺', 'Rusland'],
        'RW' => ['🇷🇼', 'Rwanda'],
        'KN' => ['🇰🇳', 'Saint Kitts en Nevis'],
        'LC' => ['🇱🇨', 'Saint Lucia'],
        'VC' => ['🇻🇨', 'Saint Vincent en de Grenadines'],
        'WS' => ['🇼🇸', 'Samoa'],
        'SM' => ['🇸🇲', 'San Marino'],
        'ST' => ['🇸🇹', 'Sao Tomé en Principe'],
        'SA' => ['🇸🇦', 'Saudi-Arabië'],
        'SN' => ['🇸🇳', 'Senegal'],
        'RS' => ['🇷🇸', 'Servië'],
        'SC' => ['🇸🇨', 'Seychellen'],
        'SL' => ['🇸🇱', 'Sierra Leone'],
        'SG' => ['🇸🇬', 'Singapore'],
        'SK' => ['🇸🇰', 'Slowakije'],
        'SI' => ['🇸🇮', 'Slovenië'],
        'SB' => ['🇸🇧', 'Salomonseilanden'],
        'SO' => ['🇸🇴', 'Somalië'],
        'ZA' => ['🇿🇦', 'Zuid-Afrika'],
        'SS' => ['🇸🇸', 'Zuid-Soedan'],
        'ES' => ['🇪🇸', 'Spanje'],
        'LK' => ['🇱🇰', 'Sri Lanka'],
        'SD' => ['🇸🇩', 'Soedan'],
        'SR' => ['🇸🇷', 'Suriname'],
        'SE' => ['🇸🇪', 'Zweden'],
        'CH' => ['🇨🇭', 'Zwitserland'],
        'SY' => ['🇸🇾', 'Syrië'],
        'TW' => ['🇹🇼', 'Taiwan'],
        'TJ' => ['🇹🇯', 'Tadzjikistan'],
        'TZ' => ['🇹🇿', 'Tanzania'],
        'TH' => ['🇹🇭', 'Thailand'],
        'TL' => ['🇹🇱', 'Oost-Timor'],
        'TG' => ['🇹🇬', 'Togo'],
        'TO' => ['🇹🇴', 'Tonga'],
        'TT' => ['🇹🇹', 'Trinidad en Tobago'],
        'TN' => ['🇹🇳', 'Tunesië'],
        'TR' => ['🇹🇷', 'Turkije'],
        'TM' => ['🇹🇲', 'Turkmenistan'],
        'TV' => ['🇹🇻', 'Tuvalu'],
        'UG' => ['🇺🇬', 'Oeganda'],
        'UA' => ['🇺🇦', 'Oekraïne'],
        'AE' => ['🇦🇪', 'Verenigde Arabische Emiraten'],
        'GB' => ['🇬🇧', 'Verenigd Koninkrijk'],
        'US' => ['🇺🇸', 'Verenigde Staten'],
        'UY' => ['🇺🇾', 'Uruguay'],
        'UZ' => ['🇺🇿', 'Oezbekistan'],
        'VU' => ['🇻🇺', 'Vanuatu'],
        'VE' => ['🇻🇪', 'Venezuela'],
        'VN' => ['🇻🇳', 'Vietnam'],
        'YE' => ['🇾🇪', 'Jemen'],
        'ZM' => ['🇿🇲', 'Zambia'],
        'ZW' => ['🇿🇼', 'Zimbabwe'],
    ];

    public static function register() {
        if ( ! taxonomy_exists( 'afstand' ) ) {
            register_taxonomy( 'afstand', self::POST_TYPE, [
                'labels'       => [
                    'name'          => 'Afstanden',
                    'singular_name' => 'Afstand',
                    'add_new_item'  => 'Nieuwe afstand toevoegen',
                    'search_items'  => 'Afstand zoeken',
                ],
                'hierarchical' => false,
                'public'       => true,
                'show_in_rest' => true,
                'rewrite'      => [ 'slug' => 'afstand' ],
            ] );
        } else {
            register_taxonomy_for_object_type( 'afstand', self::POST_TYPE );
        }

        if ( ! taxonomy_exists( 'sta_club' ) ) {
            register_taxonomy( 'sta_club', self::POST_TYPE, [
                'labels'       => [
                    'name'          => 'Clubs',
                    'singular_name' => 'Club',
                    'add_new_item'  => 'Nieuwe club toevoegen',
                    'search_items'  => 'Club zoeken',
                ],
                'hierarchical' => false,
                'public'       => true,
                'show_in_rest' => true,
                'rewrite'      => [ 'slug' => 'club' ],
            ] );
        } else {
            register_taxonomy_for_object_type( 'sta_club', self::POST_TYPE );
        }

        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => 'Atleten',
                'singular_name'      => 'Atleet',
                'add_new'            => 'Atleet toevoegen',
                'add_new_item'       => 'Nieuwe atleet toevoegen',
                'edit_item'          => 'Atleet bewerken',
                'view_item'          => 'Atleet bekijken',
                'search_items'       => 'Atleet zoeken',
                'not_found'          => 'Geen atleten gevonden',
                'not_found_in_trash' => 'Geen atleten in prullenbak',
            ],
            'public'        => true,
            'has_archive'   => true,
            'menu_icon'     => 'dashicons-groups',
            'supports'      => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
            'show_in_rest'  => true,
            'rewrite'       => [ 'slug' => 'atleten' ],
        ] );

        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'save_meta' ] );

        // Zorg dat alle meta boxes standaard uitgeklapt zijn op de atleet-editpagina
        add_filter( 'get_user_option_closedpostboxes_athlete', [ __CLASS__, 'default_open_metaboxes' ] );
        add_filter( 'get_user_option_metaboxhidden_athlete',   '__return_empty_array' );
    }

    public static function default_open_metaboxes( $closed ) {
        // Als de gebruiker nog nooit zelf iets ingeklapt heeft, alles open laten
        return empty( $closed ) ? [] : $closed;
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'sta_athlete_details',
            'Atleet gegevens',
            [ __CLASS__, 'render_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sta_athlete_speaker_notes',
            'Sprekers notities',
            [ __CLASS__, 'render_speaker_notes_box' ],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function render_speaker_notes_box( $post ) {
        $pub      = get_post_meta( $post->ID, 'sta_notes_public',   true );
        $internal = get_post_meta( $post->ID, 'sta_notes_internal', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sta_notes_public">Publieke notitie</label></th>
                <td>
                    <textarea id="sta_notes_public" name="sta_notes_public"
                              rows="4" class="large-text"
                              placeholder="Bijv. meervoudig NL-kampioen, heeft een bijzonder verhaal…"><?php echo esc_textarea( $pub ); ?></textarea>
                    <p class="description">Wordt meegestuurd naar de AI bij het genereren van de bio.</p>
                </td>
            </tr>
            <tr>
                <th><label for="sta_notes_internal">Interne notitie</label></th>
                <td>
                    <textarea id="sta_notes_internal" name="sta_notes_internal"
                              rows="3" class="large-text"
                              placeholder="Alleen intern zichtbaar…"><?php echo esc_textarea( $internal ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th>Bio</th>
                <td>
                    <button type="button" id="sta-regen-bio-btn" class="button button-primary">
                        Genereer bio opnieuw
                    </button>
                    <span id="sta-regen-bio-status" style="margin-left:10px;font-size:13px;color:#666;"></span>
                    <p class="description">Slaat de notities automatisch op en genereert daarna de bio opnieuw.</p>
                </td>
            </tr>
        </table>

        <script>
        jQuery(function($) {
            var nonce   = '<?php echo wp_create_nonce( 'sta_nonce' ); ?>';
            var postId  = <?php echo $post->ID; ?>;

            $('#sta-regen-bio-btn').on('click', function() {
                var $btn    = $(this);
                var $status = $('#sta-regen-bio-status');

                $btn.prop('disabled', true).text('Opslaan…');
                $status.text('Notities opslaan…').css('color', '#666');

                // Sla eerst de twee notitievelden op via AJAX
                var pubVal  = $('#sta_notes_public').val();
                var intVal  = $('#sta_notes_internal').val();

                $.when(
                    $.post(ajaxurl, { action: 'sta_save_speaker_note', nonce: nonce, post_id: postId, field: 'sta_notes_public',   value: pubVal }),
                    $.post(ajaxurl, { action: 'sta_save_speaker_note', nonce: nonce, post_id: postId, field: 'sta_notes_internal', value: intVal })
                ).then(function() {
                    $btn.text('Bio genereren…');
                    $status.text('Bio wordt gegenereerd…');

                    $.post(ajaxurl, {
                        action:  'sta_update_bio_speaker',
                        nonce:   nonce,
                        post_id: postId,
                    }, function(res) {
                        $btn.prop('disabled', false).text('Genereer bio opnieuw');
                        if (res.success) {
                            $status.text('✓ Bio bijgewerkt — herlaad de pagina om hem te zien.').css('color', '#1a8a1a');
                        } else {
                            $status.text('✗ ' + (res.data || 'Fout bij genereren.')).css('color', '#c00');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false).text('Genereer bio opnieuw');
                        $status.text('✗ Verbindingsfout').css('color', '#c00');
                    });
                });
            });
        });
        </script>
        <?php
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'sta_athlete_meta', 'sta_athlete_nonce' );

        $club       = get_post_meta( $post->ID, 'sta_club',     true );
        $country    = get_post_meta( $post->ID, 'sta_country',  true );
        $gender     = get_post_meta( $post->ID, 'sta_gender',   true );
        $bio        = get_post_meta( $post->ID, 'sta_bio',      true );
        $uitgelicht = (bool) get_post_meta( $post->ID, 'sta_featured', true );
        $flickr     = get_post_meta( $post->ID, 'sta_flickr_photo_page', true );
        $focus_x    = get_post_meta( $post->ID, 'sta_focus_x', true ) ?: '50';
        $focus_y    = get_post_meta( $post->ID, 'sta_focus_y', true ) ?: '50';
        $thumb_url  = get_the_post_thumbnail_url( $post->ID, 'medium' );

        // Huidige afstand-termen voor deze atleet
        $current_terms = wp_get_object_terms( $post->ID, 'afstand', [ 'fields' => 'ids' ] );
        $all_terms     = get_terms( [ 'taxonomy' => 'afstand', 'hide_empty' => false, 'orderby' => 'name' ] );
        ?>
        <table class="form-table">

            <tr>
                <th><label for="sta_featured">Uitgelicht</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="sta_featured" name="sta_featured" value="1" <?php checked( $uitgelicht ); ?>>
                        Toon deze atleet in het uitgelicht-filter op de frontend
                    </label>
                </td>
            </tr>

            <tr>
                <th><label for="sta_gender">Geslacht</label></th>
                <td>
                    <label style="margin-right:16px;">
                        <input type="radio" name="sta_gender" value="m" <?php checked( $gender, 'm' ); ?>>
                        ♂ Man
                    </label>
                    <label>
                        <input type="radio" name="sta_gender" value="f" <?php checked( $gender, 'f' ); ?>>
                        ♀ Vrouw
                    </label>
                </td>
            </tr>

            <tr>
                <th><label for="sta_country">Land</label></th>
                <td>
                    <select id="sta_country" name="sta_country" style="min-width:260px;">
                        <option value="">— Selecteer land —</option>
                        <?php foreach ( self::COUNTRIES as $code => [$flag, $name] ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country, $code ); ?>>
                                <?php echo $flag . ' ' . esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="sta_club">Zwemvereniging / Club</label></th>
                <td>
                    <input type="text" id="sta_club" name="sta_club"
                           value="<?php echo esc_attr( $club ); ?>" class="regular-text">
                </td>
            </tr>

            <tr>
                <th>Afstanden</th>
                <td>
                    <?php if ( $all_terms && ! is_wp_error( $all_terms ) ) : ?>
                        <div style="column-count:2; column-gap:24px; max-width:500px;">
                        <?php foreach ( $all_terms as $term ) : ?>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox"
                                       name="sta_afstand_terms[]"
                                       value="<?php echo esc_attr( $term->term_id ); ?>"
                                       <?php checked( in_array( $term->term_id, (array) $current_terms, true ) ); ?>>
                                <?php echo esc_html( $term->name ); ?>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="description">Nog geen afstanden beschikbaar. Deze worden aangemaakt tijdens de PDF-import.</p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label for="sta_bio">Bio</label></th>
                <td>
                    <textarea id="sta_bio" name="sta_bio" rows="6" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea>
                </td>
            </tr>

            <?php if ( $flickr ) : ?>
            <tr>
                <th>Flickr foto</th>
                <td><a href="<?php echo esc_url( $flickr ); ?>" target="_blank">Bekijk op Flickr ↗</a></td>
            </tr>
            <?php endif; ?>

            <?php if ( $thumb_url ) : ?>
            <tr>
                <th>Focuspunt foto</th>
                <td>
                    <p class="description" style="margin-bottom:8px;">Klik op de foto om het focuspunt in te stellen (gebruikt bij ronde/gecropte weergave).</p>
                    <div id="sta-focus-wrap" style="position:relative;display:inline-block;cursor:crosshair;max-width:300px;">
                        <img id="sta-focus-img" src="<?php echo esc_url( $thumb_url ); ?>"
                             style="display:block;width:100%;height:auto;border-radius:6px;">
                        <div id="sta-focus-dot" style="
                            position:absolute;
                            width:20px;height:20px;
                            border-radius:50%;
                            background:rgba(211,2,27,.85);
                            border:2px solid #fff;
                            transform:translate(-50%,-50%);
                            pointer-events:none;
                            left:<?php echo esc_attr( $focus_x ); ?>%;
                            top:<?php echo esc_attr( $focus_y ); ?>%;
                            box-shadow:0 0 0 1px rgba(0,0,0,.4);
                        "></div>
                    </div>
                    <input type="hidden" name="sta_focus_x" id="sta_focus_x" value="<?php echo esc_attr( $focus_x ); ?>">
                    <input type="hidden" name="sta_focus_y" id="sta_focus_y" value="<?php echo esc_attr( $focus_y ); ?>">
                    <p class="description" style="margin-top:6px;">Huidig: <span id="sta-focus-label"><?php echo esc_html( $focus_x ); ?>% / <?php echo esc_html( $focus_y ); ?>%</span></p>
                    <script>
                    (function() {
                        var wrap = document.getElementById('sta-focus-wrap');
                        if (!wrap) return;
                        wrap.addEventListener('click', function(e) {
                            var rect = wrap.getBoundingClientRect();
                            var x = Math.round((e.clientX - rect.left) / rect.width * 100);
                            var y = Math.round((e.clientY - rect.top)  / rect.height * 100);
                            document.getElementById('sta_focus_x').value = x;
                            document.getElementById('sta_focus_y').value = y;
                            document.getElementById('sta-focus-dot').style.left = x + '%';
                            document.getElementById('sta-focus-dot').style.top  = y + '%';
                            document.getElementById('sta-focus-label').textContent = x + '% / ' + y + '%';
                        });
                    })();
                    </script>
                </td>
            </tr>
            <?php endif; ?>

        </table>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( ! isset( $_POST['sta_athlete_nonce'] ) || ! wp_verify_nonce( $_POST['sta_athlete_nonce'], 'sta_athlete_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, 'sta_featured', ! empty( $_POST['sta_featured'] ) ? '1' : '' );

        if ( isset( $_POST['sta_focus_x'], $_POST['sta_focus_y'] ) ) {
            update_post_meta( $post_id, 'sta_focus_x', min( 100, max( 0, intval( $_POST['sta_focus_x'] ) ) ) );
            update_post_meta( $post_id, 'sta_focus_y', min( 100, max( 0, intval( $_POST['sta_focus_y'] ) ) ) );
        }

        if ( isset( $_POST['sta_gender'] ) ) {
            $gender = in_array( $_POST['sta_gender'], [ 'm', 'f' ], true ) ? $_POST['sta_gender'] : '';
            update_post_meta( $post_id, 'sta_gender', $gender );
        }

        if ( isset( $_POST['sta_country'] ) ) {
            $code = strtoupper( sanitize_text_field( $_POST['sta_country'] ) );
            update_post_meta( $post_id, 'sta_country', array_key_exists( $code, self::COUNTRIES ) ? $code : '' );
        }

        if ( isset( $_POST['sta_club'] ) ) {
            update_post_meta( $post_id, 'sta_club', sanitize_text_field( $_POST['sta_club'] ) );
        }

        if ( isset( $_POST['sta_notes_public'] ) ) {
            update_post_meta( $post_id, 'sta_notes_public', sanitize_textarea_field( $_POST['sta_notes_public'] ) );
        }
        if ( isset( $_POST['sta_notes_internal'] ) ) {
            update_post_meta( $post_id, 'sta_notes_internal', sanitize_textarea_field( $_POST['sta_notes_internal'] ) );
        }

        // Afstand-taxonomie via checkboxes
        $term_ids = array_map( 'intval', (array) ( $_POST['sta_afstand_terms'] ?? [] ) );
        // replace = false want we vervangen de volledige selectie
        wp_set_object_terms( $post_id, $term_ids, 'afstand', false );

        if ( isset( $_POST['sta_bio'] ) ) {
            update_post_meta( $post_id, 'sta_bio', wp_kses_post( $_POST['sta_bio'] ) );
        }
    }

    /**
     * Hulpfunctie: geef vlag + naam terug voor een ISO-code.
     */
    public static function get_country_label( string $code ): string {
        $code = strtoupper( $code );
        if ( isset( self::COUNTRIES[ $code ] ) ) {
            [$flag, $name] = self::COUNTRIES[ $code ];
            return $flag . ' ' . $name;
        }
        return $code;
    }
}
