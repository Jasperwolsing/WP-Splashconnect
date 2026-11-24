<?php

class STA_Admin_Program {

    public static function register(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_sta_save_program_times', [ __CLASS__, 'save_times' ] );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'swimming-trials-ai',
            'Programma',
            'Programma',
            'manage_options',
            'swimming-trials-program',
            [ __CLASS__, 'render' ]
        );
    }

    public static function save_times(): void {
        check_admin_referer( 'sta_program_times' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang.' );

        $times = [];
        foreach ( $_POST['sta_time'] ?? [] as $key => $val ) {
            $key = sanitize_text_field( $key );
            $val = sanitize_text_field( $val );
            if ( $val !== '' ) $times[ $key ] = $val;
        }
        update_option( 'sta_program_times', $times );

        $session_times = [];
        foreach ( $_POST['sta_session_time'] ?? [] as $key => $val ) {
            $key = sanitize_text_field( $key );
            $val = sanitize_text_field( $val );
            if ( $val !== '' ) $session_times[ $key ] = $val;
        }
        update_option( 'sta_program_session_times', $session_times );

        // Eigen sessienamen: leeg veld = automatische naam gebruiken.
        $session_names = [];
        foreach ( $_POST['sta_session_name'] ?? [] as $key => $val ) {
            $key = sanitize_text_field( wp_unslash( $key ) );
            $val = trim( sanitize_text_field( wp_unslash( $val ) ) );
            if ( $val !== '' ) $session_names[ $key ] = mb_substr( $val, 0, 60 );
        }
        update_option( 'sta_program_session_names', $session_names );

        // Sla para-vinkjes op: alle keys die aangevinkt zijn
        $para_checked = array_keys( $_POST['sta_para'] ?? [] );
        $para_checked = array_map( 'sanitize_text_field', $para_checked );
        update_option( 'sta_program_para', $para_checked );

        wp_redirect( admin_url( 'admin.php?page=swimming-trials-program&saved=1' ) );
        exit;
    }

    public static function render(): void {
        $days          = STA_Program::get_by_day();
        $saved         = isset( $_GET['saved'] );
        $para_checked  = get_option( 'sta_program_para', null );
        $session_times = get_option( 'sta_program_session_times', [] );
        // Load raw manual times directly (NOT via get_program()) so auto-generated times don't bleed in
        $manual_times  = get_option( 'sta_program_times', [] );
        $auto_times    = get_option( 'sta_program_times_auto', [] );
        // null = nog nooit opgeslagen → gebruik defaults uit de data
        // array = admin heeft handmatig ingesteld

        $session_colors = [
            'heats'     => '#D0021B',
            'bc_finals' => '#a80116',
            'a_finals'  => '#800011',
        ];
        ?>
        <div class="wrap sta-wrap">
            <h1>📅 Programma – NK Zwemmen 2026</h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>
            <?php endif; ?>

            <p style="color:#666;margin-bottom:24px;">
                Vul starttijden in zodra die bekend zijn (bijv. <code>9:00</code> of <code>20:14</code>).
                Vink <strong>P</strong> aan voor events met een Paralympische finale.
                Geef een sessie een eigen <strong>naam</strong> (bijv. "Ochtendsessie"); laat het veld leeg voor de automatische naam.
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="sta_save_program_times">
                <?php wp_nonce_field( 'sta_program_times' ); ?>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">

                <?php foreach ( $days as $date => $day_data ) : ?>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;">

                        <div style="background:#13315C;color:#F6F6F6;padding:12px 16px;">
                            <strong style="font-size:16px;"><?php echo esc_html( $day_data['day'] ); ?></strong>
                            <span style="opacity:.7;font-size:13px;margin-left:8px;">
                                <?php echo date( 'd-m-Y', strtotime( $date ) ); ?>
                            </span>
                        </div>

                        <?php foreach ( $day_data['sessions'] as $session_key => $session ) :
                            // Session key for session-level time = date|session_id
                            $first_ev    = $session['events'][0] ?? null;
                            $sess_tkey   = $first_ev ? STA_Program::session_key( $first_ev ) : '';
                            $sess_time_v = $sess_tkey ? ( $session_times[ $sess_tkey ] ?? '' ) : '';
                        ?>
                            <div style="padding:12px 16px;border-top:1px solid #eee;">

                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
                                    <div style="
                                        background:<?php echo $session_colors[ $session_key ] ?? '#666'; ?>;
                                        color:#fff;
                                        font-size:12px;
                                        font-weight:700;
                                        padding:3px 10px;
                                        border-radius:50px;
                                    "><?php echo esc_html( $session['label'] ); ?></div>
                                    <label style="font-size:12px;color:#555;display:flex;align-items:center;gap:6px;">
                                        Naam:
                                        <input type="text"
                                               name="sta_session_name[<?php echo esc_attr( $sess_tkey ); ?>]"
                                               value="<?php echo esc_attr( ! empty( $session['label_custom'] ) ? $session['label'] : '' ); ?>"
                                               placeholder="<?php echo esc_attr( $session['label_default'] ?? $session['label'] ); ?>"
                                               maxlength="60"
                                               title="Laat leeg voor de automatische naam"
                                               style="width:110px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;font-size:12px;">
                                    </label>
                                    <label style="font-size:12px;color:#555;display:flex;align-items:center;gap:6px;">
                                        Sessie starttijd:
                                        <input type="text"
                                               name="sta_session_time[<?php echo esc_attr( $sess_tkey ); ?>]"
                                               value="<?php echo esc_attr( $sess_time_v ); ?>"
                                               placeholder="bijv. 14:00"
                                               style="width:70px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;font-size:12px;">
                                    </label>
                                    <?php if ( $session['approx_time'] ) : ?>
                                    <span style="font-size:11px;color:#999;">API: <?php echo esc_html( $session['approx_time'] ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                    <tr style="color:#999;font-size:11px;text-transform:uppercase;">
                                        <th style="text-align:left;padding:2px 4px;font-weight:600;">Event</th>
                                        <th style="padding:2px 4px;font-weight:600;text-align:center;">P</th>
                                        <th style="padding:2px 4px;font-weight:600;text-align:left;">Starttijd</th>
                                    </tr>
                                    <?php foreach ( $session['events'] as $ev ) :
                                        $key        = STA_Program::event_key( $ev );
                                        $g          = $ev['gender'] === 'f' ? '♀' : '♂';
                                        $label      = $ev['relay']
                                            ? $ev['term_name'] . ' ' . $g
                                            : $ev['distance'] . 'm ' . $ev['stroke'] . ' ' . $g;
                                        // Read manually set time directly (not via get_program so auto-times don't show)
                                        $manual_val = $manual_times[ $key ] ?? '';
                                        $auto_hint  = $auto_times[ $key ] ?? '';

                                        // Para: gebruik admin-override als die bestaat, anders default uit data
                                        if ( $para_checked === null ) {
                                            $is_para = $ev['paralympic'];
                                        } else {
                                            $is_para = in_array( $key, $para_checked, true );
                                        }
                                    ?>
                                    <tr style="border-bottom:1px solid #f0f0f0;">
                                        <td style="padding:5px 4px;color:#333;">
                                            <?php echo esc_html( $label ); ?>
                                        </td>
                                        <td style="padding:5px 4px;text-align:center;">
                                            <input type="checkbox"
                                                   name="sta_para[<?php echo esc_attr( $key ); ?>]"
                                                   value="1"
                                                   <?php checked( $is_para ); ?>
                                                   style="margin:0;">
                                        </td>
                                        <td style="padding:5px 4px;">
                                            <input type="text"
                                                   name="sta_time[<?php echo esc_attr( $key ); ?>]"
                                                   value="<?php echo esc_attr( $manual_val ); ?>"
                                                   placeholder="<?php echo esc_attr( $auto_hint ?: '–' ); ?>"
                                                   style="width:65px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;font-size:12px;<?php echo $auto_hint && ! $manual_val ? 'color:#999;' : ''; ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>

                            </div>
                        <?php endforeach; ?>

                    </div>
                <?php endforeach; ?>

                </div>

                <p style="margin-top:24px;">
                    <?php submit_button( 'Opslaan', 'primary', 'submit', false ); ?>
                </p>

            </form>
        </div>
        <?php
    }
}
