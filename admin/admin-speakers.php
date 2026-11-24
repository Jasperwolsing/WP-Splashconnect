<?php

class STA_Admin_Speakers {

    public static function register(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'wp_ajax_sta_save_speaker_note',  [ __CLASS__, 'ajax_save_note' ] );
        add_action( 'wp_ajax_sta_update_bio_speaker', [ __CLASS__, 'ajax_update_bio' ] );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'swimming-trials-ai',
            'Speakers',
            'Speakers',
            'edit_posts',
            'swimming-trials-speakers',
            [ __CLASS__, 'render' ]
        );
    }

    public static function ajax_save_note(): void {
        check_ajax_referer( 'sta_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Geen toegang.' );

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $field   = sanitize_key( $_POST['field'] ?? '' );
        $value   = sanitize_textarea_field( $_POST['value'] ?? '' );

        if ( ! $post_id || ! in_array( $field, [ 'sta_notes_public', 'sta_notes_internal' ], true ) ) {
            wp_send_json_error( 'Ongeldig verzoek.' );
        }

        update_post_meta( $post_id, $field, $value );
        wp_send_json_success();
    }

    public static function ajax_update_bio(): void {
        check_ajax_referer( 'sta_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Geen toegang.' );

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( 'Geen atleet opgegeven.' );

        $name = get_the_title( $post_id );
        if ( ! $name ) wp_send_json_error( 'Atleet niet gevonden.' );

        $generator = new STA_Bio_Generator();
        $bio = $generator->generate( $name, $post_id );

        if ( is_wp_error( $bio ) ) {
            wp_send_json_error( $bio->get_error_message() );
        }

        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => wp_kses_post( $bio ),
        ] );

        wp_send_json_success( [ 'bio' => $bio ] );
    }

    public static function render(): void {
        $athletes = get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <div class="wrap sta-wrap">
            <h1>🎙️ Speakers</h1>
            <p style="color:#666;margin-bottom:16px;">
                Notities worden automatisch opgeslagen zodra je het veld verlaat.
                <strong>Publieke notitie</strong> wordt meegestuurd naar de AI bij het genereren van de bio.
                <strong>Interne notitie</strong> is alleen zichtbaar in de backend.
            </p>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <input type="text" id="sta-speakers-filter" placeholder="Zoek op naam of club…"
                       style="width:280px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;font-size:14px;">
                <span id="sta-speakers-count" style="color:#999;font-size:13px;"></span>
                <button id="sta-audio-filter" class="button" style="border-radius:20px;display:flex;align-items:center;gap:6px;">
                    <span id="sta-audio-dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ccc;flex-shrink:0;"></span>
                    Name recorded
                </button>
            </div>

            <table class="wp-list-table widefat fixed striped" id="sta-speakers-table">
                <thead>
                    <tr>
                        <th style="width:180px;">Atleet</th>
                        <th style="width:130px;">Club</th>
                        <th>Publieke notitie <span style="font-weight:400;color:#999;">(gaat mee naar AI)</span></th>
                        <th>Interne notitie</th>
                        <th style="width:110px;"></th>
                        <th style="width:160px;">Naam opname</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $athletes as $athlete ) :
                    $club     = get_post_meta( $athlete->ID, 'sta_club', true );
                    $pub      = get_post_meta( $athlete->ID, 'sta_notes_public', true );
                    $internal = get_post_meta( $athlete->ID, 'sta_notes_internal', true );
                    $audio_rel = get_post_meta( $athlete->ID, 'sta_name_audio', true );
                    $audio_url = $audio_rel ? home_url( '/' . ltrim( $audio_rel, '/' ) ) : '';
                ?>
                    <tr data-id="<?php echo $athlete->ID; ?>" data-audio="<?php echo $audio_rel ? '1' : '0'; ?>">
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $athlete->ID ) ); ?>" style="font-weight:700;text-decoration:none;color:#13315C;">
                                <?php echo esc_html( $athlete->post_title ); ?>
                            </a>
                            <div style="font-size:11px;color:<?php echo $athlete->post_status === 'publish' ? '#1a8a1a' : '#999'; ?>">
                                <?php echo $athlete->post_status === 'publish' ? 'Gepubliceerd' : 'Concept'; ?>
                            </div>
                        </td>
                        <td style="color:#666;font-size:13px;"><?php echo esc_html( $club ); ?></td>
                        <td>
                            <textarea
                                class="sta-note-field"
                                data-id="<?php echo $athlete->ID; ?>"
                                data-field="sta_notes_public"
                                rows="3"
                                style="width:100%;font-size:13px;resize:vertical;border:1px solid #ddd;border-radius:4px;padding:6px;"
                                placeholder="Bijv. meervoudig NL-kampioen, heeft een bijzonder verhaal…"
                            ><?php echo esc_textarea( $pub ); ?></textarea>
                            <div class="sta-save-status" style="font-size:11px;color:#999;height:14px;"></div>
                        </td>
                        <td>
                            <textarea
                                class="sta-note-field"
                                data-id="<?php echo $athlete->ID; ?>"
                                data-field="sta_notes_internal"
                                rows="3"
                                style="width:100%;font-size:13px;resize:vertical;border:1px solid #ddd;border-radius:4px;padding:6px;"
                                placeholder="Alleen intern zichtbaar…"
                            ><?php echo esc_textarea( $internal ); ?></textarea>
                            <div class="sta-save-status" style="font-size:11px;color:#999;height:14px;"></div>
                        </td>
                        <td style="vertical-align:top;padding-top:10px;">
                            <button class="button sta-update-bio-btn"
                                    data-id="<?php echo $athlete->ID; ?>"
                                    title="Bio opnieuw genereren met de bijgewerkte publieke notitie">
                                Update bio
                            </button>
                            <div class="sta-bio-status" style="font-size:11px;color:#999;margin-top:4px;"></div>
                        </td>
                        <td style="vertical-align:middle;padding-top:8px;">
<?php if ( $audio_url ) : ?>
                            <audio src="<?php echo esc_url( $audio_url ); ?>" controls
                                   style="max-width:150px;height:32px;"></audio>
<?php else : ?>
                            <span style="color:#ccc;">—</span>
<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(function($) {

            // Filter
            var $rows  = $('#sta-speakers-table tbody tr');
            var $count = $('#sta-speakers-count');
            function updateCount(n) {
                $count.text(n + ' van <?php echo count( $athletes ); ?> atleten');
            }
            updateCount($rows.length);

            var audioFilterOn = false;

            function applyFilters() {
                var q = $('#sta-speakers-filter').val().toLowerCase();
                var n = 0;
                $rows.each(function() {
                    var text = $(this).text().toLowerCase();
                    var matchText = !q || text.indexOf(q) !== -1;
                    var matchAudio = !audioFilterOn || $(this).data('audio') == '1';
                    var show = matchText && matchAudio;
                    $(this).toggle(show);
                    if (show) n++;
                });
                updateCount(n);
            }

            $('#sta-speakers-filter').on('input', applyFilters);

            $('#sta-audio-filter').on('click', function() {
                audioFilterOn = !audioFilterOn;
                $('#sta-audio-dot').css('background', audioFilterOn ? '#46b450' : '#ccc');
                $(this).css('border-color', audioFilterOn ? '#46b450' : '');
                $(this).css('color', audioFilterOn ? '#46b450' : '');
                applyFilters();
            });

            // Auto-save bij blur
            $(document).on('blur', '.sta-note-field', function() {
                var $field  = $(this);
                var $status = $field.next('.sta-save-status');
                var id      = $field.data('id');
                var field   = $field.data('field');
                var value   = $field.val();

                $status.text('Opslaan…').css('color', '#999');

                $.post(STA.ajax_url, {
                    action:  'sta_save_speaker_note',
                    nonce:   STA.nonce,
                    post_id: id,
                    field:   field,
                    value:   value,
                }, function(res) {
                    if (res.success) {
                        $status.text('✓ Opgeslagen').css('color', '#1a8a1a');
                        setTimeout(function() { $status.text(''); }, 2000);
                    } else {
                        $status.text('✗ Fout bij opslaan').css('color', '#c00');
                    }
                });
            });

            // Update bio
            $(document).on('click', '.sta-update-bio-btn', function() {
                var $btn    = $(this);
                var $status = $btn.next('.sta-bio-status');
                var id      = $btn.data('id');

                $btn.prop('disabled', true).text('Bezig…');
                $status.text('Bio wordt gegenereerd…').css('color', '#999');

                $.post(STA.ajax_url, {
                    action:  'sta_update_bio_speaker',
                    nonce:   STA.nonce,
                    post_id: id,
                }, function(res) {
                    $btn.prop('disabled', false).text('Update bio');
                    if (res.success) {
                        $status.text('✓ Bio bijgewerkt').css('color', '#1a8a1a');
                        setTimeout(function() { $status.text(''); }, 3000);
                    } else {
                        $status.text('✗ ' + (res.data || 'Fout')).css('color', '#c00');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Update bio');
                    $status.text('✗ Verbindingsfout').css('color', '#c00');
                });
            });

        });
        </script>
        <?php
    }
}
