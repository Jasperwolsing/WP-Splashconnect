<?php
get_header();

$term = get_queried_object();

$_s           = get_option( 'sta_layout', [] );
$_header_id   = $_s['header_image_id'] ?? 0;
$_header_url  = $_header_id ? wp_get_attachment_image_url( $_header_id, 'full' ) : '';
$_header_h    = (int)( $_s['header_height'] ?? 220 );
$_color       = esc_attr( $_s['primary_color'] ?? '#13315c' );
?>

<?php if ( STA_Templates::show_header() ) : ?>
<div class="athlete-header-image" style="
    width:100%;
    height:<?php echo $_header_h; ?>px;
    <?php if ( $_header_url ) : ?>
    background-image:url('<?php echo esc_url( $_header_url ); ?>');
    background-size:cover;
    background-position:center;
    <?php else : ?>
    background-color:<?php echo $_color; ?>;
    <?php endif; ?>
"></div>
<?php endif; ?>

<main id="primary" class="site-main">

    <div class="container" style="
        max-width:1000px;
        margin:0 auto;
        padding:50px 20px 80px;
    ">

        <div style="margin-bottom:32px;">
            <?php
            global $wpdb;
            $prog_id  = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_content LIKE '%[sta_program%' LIMIT 1" );
            $program_url = $prog_id ? get_permalink( (int)$prog_id ) . '#schedule' : home_url( '/' );
            ?>
            <a href="<?php echo esc_url( $program_url ); ?>" style="
                display:inline-flex;
                align-items:center;
                gap:6px;
                color:#13315C;
                font-size:14px;
                font-weight:600;
                text-decoration:none;
                opacity:.8;
            " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.8'">
                ← Full schedule
            </a>
        </div>

        <h1 style="
            font-size:36px;
            font-weight:700;
            color:#0d2d63;
            text-align:center;
            margin-bottom:8px;
        "><?php echo esc_html( $term->name ); ?></h1>

        <p style="text-align:center;color:#666;margin-bottom:24px;">
            <?php echo esc_html( $term->count ); ?> athlete<?php echo $term->count !== 1 ? 's' : ''; ?> found for this distance
        </p>

        <?php if ( class_exists( 'STA_Program' ) ) :
            $entries_f = STA_Program::get_entries_for_term( $term->name, 'f' );
            $entries_m = STA_Program::get_entries_for_term( $term->name, 'm' );

            if ( ! empty( $entries_f ) || ! empty( $entries_m ) ) :
                $session_bg = [
                    'heats'     => '#1a5276',
                    'bc_finals' => '#1a6a8a',
                    'a_finals'  => '#13315C',
                ];

                $render_entries = function( $entries ) use ( $session_bg ) {
                    foreach ( $entries as $pe ) :
                        $bg               = $session_bg[ $pe['session_slug'] ] ?? '#13315C';
                        $day_str          = date( 'l j F', strtotime( $pe['date'] ) );
                        $sess_str         = $pe['session_label'];
                        $has_event_time = ! empty( $pe['start_time'] );
                        if ( $has_event_time ) {
                            $time_str = $pe['start_time'];
                            $pos_str  = 'Scheduled event time';
                        } elseif ( ! empty( $pe['approx_time'] ) ) {
                            $time_str = $pe['approx_time'];
                            $pos_str  = 'Session start · ' . STA_Program::ordinal( $pe['order'] ) . ' event';
                        } else {
                            $time_str = '–';
                            $pos_str  = STA_Program::ordinal( $pe['order'] ) . ' event in session';
                        }
                ?>
                        <div style="
                            background:<?php echo $bg; ?>;
                            color:#F6F6F6;
                            border-radius:12px;
                            padding:10px 18px;
                            font-size:13px;
                            line-height:1.6;
                            text-align:center;
                        ">
                            <div style="font-weight:700;font-size:15px;"><?php echo esc_html( $day_str ); ?></div>
                            <div><?php echo esc_html( $sess_str . ' · ' . $time_str ); ?></div>
                            <div style="opacity:.75;font-size:12px;"><?php echo esc_html( $pos_str ); ?></div>
                        </div>
                <?php endforeach;
                };
        ?>
            <div style="display:flex;gap:24px;justify-content:center;margin-bottom:40px;flex-wrap:wrap;">

                <?php if ( ! empty( $entries_f ) ) : ?>
                <div style="text-align:center;">
                    <div style="font-size:13px;font-weight:700;color:#13315C;margin-bottom:8px;">♀ Women</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php $render_entries( $entries_f ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $entries_m ) ) : ?>
                <div style="text-align:center;">
                    <div style="font-size:13px;font-weight:700;color:#13315C;margin-bottom:8px;">♂ Men</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php $render_entries( $entries_m ); ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        <?php endif; endif; ?>

        <?php
        // ── Results section ────────────────────────────────────────────────────
        // Collect finals event_ids for live polling (all finals slugs, any gender)
        $live_event_ids = [];
        $sta_meet_id    = get_option( 'sta_swimrankings_meet_id', '' );
        if ( class_exists( 'STA_Program' ) && $sta_meet_id ) {
            $eid_term_map_live = get_option( 'sta_eid_term_map', [] );
            foreach ( STA_Program::get_program() as $pe ) {
                $eids_live = ! empty( $pe['event_ids'] ) ? $pe['event_ids'] : ( $pe['event_id'] ? [ $pe['event_id'] ] : [] );
                $matched = false;
                foreach ( $eids_live as $eid_l ) {
                    if ( ( $eid_term_map_live[ $eid_l ] ?? '' ) === $term->slug ) { $matched = true; break; }
                }
                if ( ! $matched ) {
                    $pe_term = $pe['term_name'] ?? '';
                    if ( $pe_term !== $term->name && strpos( $pe_term, $term->name ) === false ) continue;
                }
                foreach ( $eids_live as $eid_l ) {
                    $live_event_ids[ $eid_l ] = true;
                }
            }
        }
        $live_event_ids = array_keys( $live_event_ids );

        // Build name → permalink map from all athlete posts in this term
        $athlete_urls = [];
        $term_athlete_posts = get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [ [ 'taxonomy' => 'afstand', 'field' => 'term_id', 'terms' => $term->term_id ] ],
        ] );
        foreach ( $term_athlete_posts as $apid ) {
            $athlete_urls[ mb_strtolower( get_the_title( $apid ), 'UTF-8' ) ] = get_permalink( $apid );
        }

        // ── Startlijst: toekomstige series ─────────────────────────────────────
        ?>
        <script>
        if (typeof staToggleBlock === 'undefined') {
            function staToggleBlock(el) {
                var block = el.closest('.sta-result-block');
                var body  = block.querySelector('.sta-rb-body');
                var btn   = block.querySelector('.sta-toggle-btn');
                var open  = body.style.display === 'none';
                document.querySelectorAll('.sta-result-block').forEach(function(other) {
                    if (other === block) return;
                    var ob = other.querySelector('.sta-rb-body');
                    var ob2 = other.querySelector('.sta-toggle-btn');
                    if (ob) ob.style.display = 'none';
                    if (ob2) ob2.textContent = '▼';
                });
                body.style.display = open ? '' : 'none';
                if (btn) btn.textContent = open ? '▲' : '▼';
            }
        }
        </script>
        <?php
        $meet_id_sl2   = $sta_meet_id;
        $today_sl      = date( 'Y-m-d' );
        $event_results = get_option( 'sta_event_results', [] );

        if ( $meet_id_sl2 && class_exists( 'STA_Program' ) ) :
            $heats_program = [];
            foreach ( [ 'f', 'm', 'x' ] as $_g ) {
                foreach ( STA_Program::get_entries_for_term( $term->name, $_g ) as $pe ) {
                    $sl = $pe['session_slug'] ?? '';
                    if ( ! in_array( $sl, [ 'heats', 'finals', 'bc_finals', 'a_finals' ], true ) ) continue;
                    if ( ( $pe['date'] ?? '' ) < $today_sl ) continue;
                    $heats_program[] = $pe;
                }
            }
            usort( $heats_program, fn( $a, $b ) => strcmp( $a['date'] . $a['session'], $b['date'] . $b['session'] ) );

            $gi_sl          = [ 'f' => '♀', 'm' => '♂' ];
            $rendered_eid_keys = []; // deduplicate identical blocks

            foreach ( $heats_program as $pe_sl ) :
                $eids_sl = ! empty( $pe_sl['event_ids'] ) ? $pe_sl['event_ids'] : ( $pe_sl['event_id'] ? [ $pe_sl['event_id'] ] : [] );
                // Include session_slug so bc_finals and a_finals (sharing event_ids) each get their own block
                $eid_key = implode( ',', $eids_sl ) . '|' . ( $pe_sl['gender'] ?? '' ) . '|' . ( $pe_sl['session_slug'] ?? '' );
                if ( isset( $rendered_eid_keys[ $eid_key ] ) ) continue;
                $rendered_eid_keys[ $eid_key ] = true;

                $all_heats_sl = [];
                foreach ( $eids_sl as $e_id ) {
                    $ck = 'sta_heats_startlijst_' . $e_id;
                    $hd = get_transient( $ck );
                    // Stale empty cache: delete and re-fetch
                    if ( $hd !== false && empty( $hd['heats'] ) ) {
                        delete_transient( $ck );
                        $hd = false;
                    }
                    if ( $hd === false ) {
                        $url_sl = 'https://live.swimrankings.net/meets/' . $meet_id_sl2 . '/heats/' . $e_id . '.json';
                        $resp   = wp_remote_get( $url_sl, [ 'timeout' => 10, 'sslverify' => false ] );
                        if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                            $hd = json_decode( wp_remote_retrieve_body( $resp ), true );
                            // Only cache when data is present; empty responses are retried on next load
                            if ( ! empty( $hd['heats'] ) ) {
                                set_transient( $ck, $hd, HOUR_IN_SECONDS );
                            }
                        }
                    }
                    foreach ( $hd['heats'] ?? [] as $h ) $all_heats_sl[] = $h;
                }

                if ( empty( $all_heats_sl ) ) continue;

                $by_heat = [];
                foreach ( $all_heats_sl as $heat_sl ) {
                    $hn_sl = $heat_sl['heatinfo']['number'] ?? $heat_sl['id'] ?? 0;
                    foreach ( $heat_sl['entries'] ?? [] as $entry_sl ) {
                        $lane_sl = (int) ( $entry_sl['lane'] ?? -1 );
                        if ( $lane_sl < 0 || empty( $entry_sl['nametext'] ) ) continue;
                        $by_heat[ $hn_sl ][ $lane_sl ] = $entry_sl;
                    }
                }
                ksort( $by_heat );
                foreach ( $by_heat as &$_lanes_sl ) ksort( $_lanes_sl );
                unset( $_lanes_sl );

                // Split heats between bc_finals and a_finals blocks:
                // last heat = A final, earlier heats = B/C finals
                $sl_slug_pre = $pe_sl['session_slug'] ?? 'heats';
                if ( $sl_slug_pre === 'bc_finals' || $sl_slug_pre === 'a_finals' ) {
                    $heat_keys = array_keys( $by_heat );
                    $last_key  = end( $heat_keys );
                    if ( $sl_slug_pre === 'a_finals' ) {
                        $by_heat = ( $last_key !== null && isset( $by_heat[ $last_key ] ) )
                            ? [ $last_key => $by_heat[ $last_key ] ] : [];
                    } elseif ( count( $heat_keys ) > 1 ) {
                        unset( $by_heat[ $last_key ] ); // remove A final heat from bc_finals block
                    }
                    // if only 1 heat total and bc_finals: keep it (might be only BC, no A heat yet)
                }

                $total_sl = array_sum( array_map( 'count', $by_heat ) );
                if ( $total_sl === 0 ) continue;

                // Hide startlist if results already exist for any of these event IDs.
                // Exception: a_finals shares event_id with bc_finals, so bc_finals results
                // would incorrectly hide the a_finals startlist. For a_finals, only treat
                // results as "done" once the a_finals start time has actually passed.
                $sl_slug_check   = $pe_sl['session_slug'] ?? '';
                $event_ts        = 0;
                if ( $sl_slug_check === 'a_finals' && ! empty( $pe_sl['start_time'] ) ) {
                    $event_ts = strtotime( $pe_sl['date'] . ' ' . $pe_sl['start_time'] );
                }
                $has_results_sl = false;
                foreach ( $eids_sl as $e_id ) {
                    foreach ( $event_results[ $e_id ] ?? [] as $ag ) {
                        foreach ( $ag['entries'] ?? [] as $_entry ) {
                            if ( empty( $_entry['swimtime'] ) ) continue; // DNS/DNF: no real result
                            // a_finals: ignore results if the event hasn't started yet
                            if ( $sl_slug_check === 'a_finals' && $event_ts > 0 && time() < $event_ts ) continue;
                            $has_results_sl = true; break 3;
                        }
                    }
                }
                if ( $has_results_sl ) continue;

                $start_time_sl = $pe_sl['start_time'] ?: ( $pe_sl['approx_time'] ?? '' );
                $date_str_sl   = date( 'l j F', strtotime( $pe_sl['date'] ) );
                $gi_lbl        = ( $gi_sl[ $pe_sl['gender'] ] ?? '' );
        ?>
                <?php
                $sl_slug  = $pe_sl['session_slug'] ?? 'heats';
                $is_time_trial = ( $sl_slug === 'heats' && $total_sl === 1 );
                $sl_label = $is_time_trial ? 'Time Trial'
                          : ( $pe_sl['session_label'] ?? ( $sl_slug === 'heats' ? 'Heats' : 'Finals' ) );
                $sl_color = $sl_slug === 'heats' ? '#1a5276' : '#0c2340';
                ?>
                <div class="sta-result-block" data-block-key="sl_<?php echo esc_attr( $pe_sl['date'] . '_' . $sl_slug . '_' . $pe_sl['gender'] ); ?>" data-collapsed="1" data-slug="startlist" data-start-time="<?php echo esc_attr( $start_time_sl ); ?>" data-date="<?php echo esc_attr( $pe_sl['date'] ); ?>" style="margin-bottom:12px;">
                    <div onclick="staToggleBlock(this)" style="background:<?php echo $sl_color; ?>;color:#fff;border-radius:10px;padding:10px 16px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;">
                        <span><?php echo esc_html( $gi_lbl ); ?></span>
                        <span style="opacity:.8;">·</span>
                        <span><?php echo esc_html( $sl_label ); ?></span>
                        <span style="font-weight:400;opacity:.7;font-size:12px;"><?php echo esc_html( $date_str_sl ); ?></span>
                        <?php if ( $start_time_sl ) : ?>
                        <span style="font-weight:400;opacity:.7;font-size:12px;">· <?php echo esc_html( $start_time_sl ); ?></span>
                        <?php endif; ?>
                        <span style="font-weight:400;opacity:.75;font-size:12px;margin-left:2px;">(<?php echo $total_sl; ?>)</span>
                        <button class="sta-toggle-btn" onclick="event.stopPropagation();staToggleBlock(this.closest('.sta-result-block').querySelector('[onclick]'))" style="margin-left:auto;background:none;border:none;color:#fff;font-size:14px;cursor:pointer;padding:0;line-height:1;">▼</button>
                    </div>
                    <div class="sta-rb-body" style="display:none;border:1px solid #e0e7f0;border-top:none;border-radius:0 0 8px 8px;">
                    <?php
                    $final_letters = [ 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D' ];
                    $is_finals_sl  = in_array( $sl_slug, [ 'finals', 'bc_finals', 'a_finals' ], true );
                    $total_heats_sl = count( $by_heat );
                    $heat_seq = 0; foreach ( $by_heat as $hn_out => $lanes_out ) : $heat_seq++;
                    if ( $is_finals_sl ) {
                        $rev_seq  = $total_heats_sl - $heat_seq + 1; // A=last heat, B=second-to-last
                        $heat_lbl = ( $final_letters[ $rev_seq ] ?? $rev_seq ) . ' Final';
                    } else {
                        $heat_lbl = 'Heat ' . $heat_seq;
                    }
                    ?>
                        <div style="padding:0 0 4px;">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#1a5276;padding:10px 12px 4px;">
                                <?php echo esc_html( $heat_lbl ); ?>
                            </div>
                            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                            <?php foreach ( $lanes_out as $lane_out => $entry_out ) :
                                $sw_name = $entry_out['nametext'];
                                $sw_url  = $athlete_urls[ mb_strtolower( $sw_name, 'UTF-8' ) ] ?? '';
                                $nation  = strtolower( $entry_out['nation'] ?? '' );
                            ?>
                            <tr style="border-top:1px solid #dde4ed;">
                                <td style="padding:2px 10px;width:36px;text-align:center;font-weight:700;color:#1a5276;font-size:14px;line-height:1;">
                                    <?php echo esc_html( $lane_out ); ?>
                                    <div style="font-size:9px;font-weight:400;color:#aaa;line-height:1.2;margin-top:1px;">lane</div>
                                </td>
                                <td style="padding:2px 10px;font-size:13px;">
                                    <?php if ( $sw_url ) : ?>
                                    <a href="<?php echo esc_url( $sw_url ); ?>" style="font-weight:600;color:#0d2d63;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                        <?php echo esc_html( $sw_name ); ?>
                                    </a>
                                    <?php else : ?>
                                    <span style="font-weight:600;color:#0d2d63;"><?php echo esc_html( $sw_name ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ( $nation ) : ?>
                                <td style="padding:2px 6px;width:28px;text-align:center;">
                                    <img src="https://flagcdn.com/20x15/<?php echo esc_attr( $nation ); ?>.png" width="20" height="15" alt="" onerror="this.style.display='none'">
                                </td>
                                <?php endif; ?>
                                <td style="padding:2px 10px;text-align:right;font-family:monospace;font-weight:600;color:#555;white-space:nowrap;font-size:13px;">
                                    <?php echo esc_html( $entry_out['entrytime'] ?? '–' ); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
        <?php
            endforeach;
        endif;

        $event_results = get_option( 'sta_event_results', [] );
        $club_logos    = get_option( 'sta_club_logos', [] );
        if ( class_exists( 'STA_Program' ) ) :
            // Build result blocks per session × gender, in chronological order
            $slug_order = [ 'heats' => 0, 'semis' => 1, 'bc_finals' => 2, 'finals' => 2, 'a_finals' => 3 ];
            $result_blocks = []; // key => [session_label, slug, date, gender, agegroups[]]

            $today        = date( 'Y-m-d' );
            $eid_term_map = get_option( 'sta_eid_term_map', [] );

            // Pre-collect event_ids belonging to a_finals entries so bc_finals blocks can be skipped
            $a_finals_eids_set = [];
            foreach ( STA_Program::get_program() as $_pe ) {
                if ( ( $_pe['session_slug'] ?? '' ) !== 'a_finals' ) continue;
                $_eids = ! empty( $_pe['event_ids'] ) ? $_pe['event_ids'] : ( $_pe['event_id'] ? [ $_pe['event_id'] ] : [] );
                foreach ( $_eids as $_eid ) $a_finals_eids_set[ $_eid ] = true;
            }

            foreach ( STA_Program::get_program() as $pe ) {
                if ( ( $pe['date'] ?? '' ) > $today ) continue;       // don't show future events
                $term_is_relay = preg_match( '/\d+\s*x/i', $term->name );
                if ( $pe['relay'] && ! $term_is_relay ) continue;      // relay results only on relay pages
                if ( ! $pe['relay'] && $term_is_relay ) continue;      // individual results only on individual pages

                // Collect all event_ids for this program entry (senior + U18 share same bucket)
                $eids = ! empty( $pe['event_ids'] ) ? $pe['event_ids'] : ( $pe['event_id'] ? [ $pe['event_id'] ] : [] );

                // bc_finals shares event_ids with a_finals → skip bc_finals block entirely;
                // all results (B/C + A) are merged into the a_finals block
                if ( ( $pe['session_slug'] ?? '' ) === 'bc_finals' ) {
                    $shared = false;
                    foreach ( $eids as $eid ) { if ( isset( $a_finals_eids_set[ $eid ] ) ) { $shared = true; break; } }
                    if ( $shared ) continue;
                }

                // Match via direct event_id → term_slug map (built in Step 2), fallback to term_name
                $matched = false;
                foreach ( $eids as $eid ) {
                    if ( ( $eid_term_map[ $eid ] ?? '' ) === $term->slug ) { $matched = true; break; }
                }
                if ( ! $matched ) {
                    $pe_term = $pe['term_name'] ?? '';
                    if ( $pe_term !== $term->name && strpos( $pe_term, $term->name ) === false ) continue;
                }
                $gender_label = $pe['gender'] === 'f' ? 'Women' : ( $pe['gender'] === 'x' ? 'Mixed' : 'Men' );
                $gender_icon  = $pe['gender'] === 'f' ? '♀' : ( $pe['gender'] === 'x' ? '' : '♂' );
                // Unique block per date × session × gender (not per eid, to avoid duplicates from bc/a split)
                $block_key = $pe['date'] . '|' . $pe['session'] . '|' . $pe['gender'];

                if ( ! isset( $result_blocks[ $block_key ] ) ) {
                    // a_finals: if event hasn't started yet, label as "B/C Finals" (only BC results are valid)
                    $af_started = true;
                    $display_slug = $pe['session_slug'];
                    if ( $pe['session_slug'] === 'a_finals' && ! empty( $pe['start_time'] ) ) {
                        $af_ts = strtotime( $pe['date'] . ' ' . $pe['start_time'] );
                        if ( $af_ts && time() < $af_ts ) { $af_started = false; $display_slug = 'bc_finals'; }
                    }
                    $result_blocks[ $block_key ] = [
                        'date'          => $pe['date'],
                        'session_label' => ( $af_started || ! empty( $pe['session_label_custom'] ) ) ? $pe['session_label'] : 'B/C Finals',
                        'custom_label'  => ! empty( $pe['session_label_custom'] ),
                        'slug'          => $display_slug,
                        'gender_label'  => $gender_label,
                        'gender_icon'   => $gender_icon,
                        'sort'          => $pe['date'] . '|' . ( $slug_order[ $pe['session_slug'] ] ?? 9 ) . '|' . $pe['gender'],
                        'start_time'    => $pe['start_time'] ?: $pe['approx_time'],
                        'agegroups'     => [],
                    ];
                }

                foreach ( $eids as $eid ) {
                    if ( empty( $event_results[ $eid ] ) ) continue;
                    foreach ( $event_results[ $eid ] as $agid => $ag_data ) {
                        if ( empty( $ag_data['entries'] ) ) continue;
                        if ( ! isset( $result_blocks[ $block_key ]['agegroups'][ $agid ] ) ) {
                            $entries = $ag_data['entries'];
                            usort( $entries, fn( $a, $b ) => ( $a['place'] > 0 ? $a['place'] : 9999 ) - ( $b['place'] > 0 ? $b['place'] : 9999 ) );
                            $result_blocks[ $block_key ]['agegroups'][ $agid ] = [
                                'name'      => $ag_data['name'],
                                'scoretype' => $ag_data['scoretype'],
                                'entries'   => $entries,
                            ];
                        }
                    }
                }
            }

            // max_rank based only on blocks WITH actual results (empty blocks don't influence collapse logic)
            $max_rank_results = 0;
            foreach ( $result_blocks as $block ) {
                if ( empty( $block['agegroups'] ) ) continue;
                $r = $slug_order[ $block['slug'] ] ?? 0;
                if ( $r > $max_rank_results ) $max_rank_results = $r;
            }
            if ( ! empty( $result_blocks ) ) :
                uasort( $result_blocks, fn( $a, $b ) => strcmp( $b['sort'], $a['sort'] ) );
                $slug_bg = [
                    'heats'    => '#1a5276',
                    'semis'    => '#1a5276',
                    'bc_finals'=> '#1a6a8a',
                    'finals'   => '#13315C',
                    'a_finals' => '#13315C',
                ];
        ?>
        <script>
        var staBlockStates = {};  // key → true=open, false=collapsed
        var staBlockStoreKey = 'sta_blocks_<?php echo esc_js( $term->slug ); ?>';

        function staLoadBlockStates() {
            try { staBlockStates = JSON.parse(localStorage.getItem(staBlockStoreKey) || '{}'); } catch(e) {}
        }
        function staSaveBlockStates() {
            try { localStorage.setItem(staBlockStoreKey, JSON.stringify(staBlockStates)); } catch(e) {}
        }
        function staApplyBlockStates() {
            // Accordion: only one block open at a time.
            // Priority: server-open block > auto-expand > localStorage
            var toOpen = null;
            document.querySelectorAll('.sta-result-block[data-block-key]').forEach(function(el) {
                if (el.dataset.collapsed !== '1' && !toOpen) toOpen = el;
            });
            if (!toOpen) {
                document.querySelectorAll('.sta-result-block[data-block-key]').forEach(function(el) {
                    // Never restore heats/semis/startlist from localStorage
                    if (el.dataset.slug === 'heats' || el.dataset.slug === 'semis' || el.dataset.slug === 'startlist') return;
                    if (staBlockStates[el.dataset.blockKey] === true && !toOpen) toOpen = el;
                });
            }
            document.querySelectorAll('.sta-result-block[data-block-key]').forEach(function(el) {
                var body = el.querySelector('.sta-rb-body');
                var btn  = el.querySelector('.sta-toggle-btn');
                var open = (el === toOpen);
                if (body) body.style.display = open ? '' : 'none';
                if (btn) btn.textContent = open ? '▲' : '▼';
            });
        }
        var slugRank = { heats: 0, semis: 1, bc_finals: 2, finals: 2, a_finals: 3 };

        function staAutoExpand() {
            var now     = new Date();
            var today   = now.toISOString().slice(0, 10);
            var nowMins = now.getHours() * 60 + now.getMinutes();

            // Clear all live dots first
            document.querySelectorAll('.sta-live-dot').forEach(function(d) { d.style.display = 'none'; });

            // Find most-advanced block that has started (or starts within 5 min) today, across ALL blocks.
            var best = null, bestMins = -1, bestRank = -1;
            document.querySelectorAll('.sta-result-block[data-start-time]').forEach(function(el) {
                if (el.dataset.date !== today) return;
                if (el.dataset.slug === 'startlist') return;
                var t = el.dataset.startTime;
                if (!t) return;
                var parts = t.split(':');
                var startMins = parseInt(parts[0]) * 60 + parseInt(parts[1]);
                var rank = slugRank[el.dataset.slug] || 0;
                var hasResults = el.querySelector('.sta-rb-body table tr:not(.sta-dns)') !== null;
                // A Finals: open 60 min before start even without results
                // Other blocks: only consider if results are present and already started
                var earlyOpen = (el.dataset.slug === 'a_finals') && (nowMins >= startMins - 60);
                if (!earlyOpen && (!hasResults || nowMins < startMins)) return;
                if (rank > bestRank || (rank === bestRank && startMins > bestMins)) {
                    bestRank = rank; bestMins = startMins; best = el;
                }
            });

            if (best) {
                // Show live dot on this block
                var dot = best.querySelector('.sta-live-dot');
                if (dot) dot.style.display = 'inline-block';

                // If this block is collapsed, open it
                if (best.dataset.collapsed === '1') {
                    best.dataset.collapsed = '0';
                    var body = best.querySelector('.sta-rb-body');
                    var btn  = best.querySelector('.sta-toggle-btn');
                    if (body) body.style.display = '';
                    if (btn)  btn.textContent = '▲';
                }

                // If higher-ranked results exist (e.g. finals), collapse all heats-only blocks
                if (bestRank > 0) {
                    document.querySelectorAll('.sta-result-block[data-slug="heats"]').forEach(function(el) {
                        if (el === best) return;
                        var body = el.querySelector('.sta-rb-body');
                        var btn  = el.querySelector('.sta-toggle-btn');
                        if (body && body.style.display !== 'none') {
                            body.style.display = 'none';
                            if (btn) btn.textContent = '▼';
                        }
                    });
                }
            } else {
                // No results block active today — auto-open startlist if event starts within slMins
                var slMins = <?php echo (int)get_option('sta_startlist_autoopen_mins', 30); ?>;
                var bestSl = null, bestSlMins = Infinity;
                document.querySelectorAll('.sta-result-block[data-slug="startlist"][data-start-time]').forEach(function(el) {
                    if (el.dataset.date !== today) return;
                    var parts = el.dataset.startTime.split(':');
                    var startMins = parseInt(parts[0]) * 60 + parseInt(parts[1]);
                    var diff = startMins - nowMins;
                    if (diff >= 0 && diff <= slMins && startMins < bestSlMins) {
                        bestSlMins = startMins; bestSl = el;
                    }
                });
                if (slMins > 0 && bestSl && bestSl.dataset.collapsed === '1') {
                    bestSl.dataset.collapsed = '0';
                    var slBody = bestSl.querySelector('.sta-rb-body');
                    var slBtn  = bestSl.querySelector('.sta-toggle-btn');
                    if (slBody) slBody.style.display = '';
                    if (slBtn)  slBtn.textContent = '▲';
                }
            }
        }
        staAutoExpand();
        setInterval(staAutoExpand, 60000);

        function staToggleBlock(el) {
            var block = el.closest('.sta-result-block');
            var body  = block.querySelector('.sta-rb-body');
            var btn   = block.querySelector('.sta-toggle-btn');
            var open  = body.style.display === 'none';

            // Close all other blocks first (accordion)
            document.querySelectorAll('.sta-result-block').forEach(function(other) {
                if (other === block) return;
                var ob = other.querySelector('.sta-rb-body');
                var ob2 = other.querySelector('.sta-toggle-btn');
                if (ob) ob.style.display = 'none';
                if (ob2) ob2.textContent = '▼';
            });

            body.style.display = open ? '' : 'none';
            if (btn) btn.textContent = open ? '▲' : '▼';
            if (block.dataset.collapsed === '1' && block.dataset.slug !== 'heats' && block.dataset.slug !== 'semis' && block.dataset.slug !== 'startlist') {
                staBlockStates[block.dataset.blockKey] = open;
                staSaveBlockStates();
            }
        }
        staLoadBlockStates();
        document.addEventListener('DOMContentLoaded', staApplyBlockStates);
        </script>
        <div id="sta-results-section" style="margin-bottom:48px;">
            <h2 style="font-size:24px;font-weight:700;color:#0d2d63;margin-bottom:4px;text-align:center;">Uitslagen</h2>
            <p id="sta-live-status" style="text-align:center;font-size:12px;color:#999;margin-bottom:20px;min-height:16px;"></p>

            <?php foreach ( $result_blocks as $block ) :
                if ( empty( $block['agegroups'] ) ) continue; // no real entries — skip entirely
                $bg        = $slug_bg[ $block['slug'] ] ?? '#13315C';
                $day_str   = date( 'l j F', strtotime( $block['date'] ) );
                $rank      = $slug_order[ $block['slug'] ] ?? 0;
                $collapsed = $rank < $max_rank_results;
                $block_key = esc_attr( $block['date'] . '|' . $block['slug'] . '|' . $block['gender_label'] );
            ?>
            <div class="sta-result-block" data-block-key="<?php echo $block_key; ?>" data-collapsed="<?php echo $collapsed ? '1' : '0'; ?>" data-start-time="<?php echo esc_attr( $block['start_time'] ?? '' ); ?>" data-date="<?php echo esc_attr( $block['date'] ); ?>" data-slug="<?php echo esc_attr( $block['slug'] ); ?>" style="margin-bottom:12px;">
                <div onclick="staToggleBlock(this)" style="background:<?php echo $bg; ?>;color:#fff;border-radius:<?php echo $collapsed ? '10px' : '10px 10px 0 0'; ?>;padding:10px 16px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;">
                    <span class="sta-live-dot" style="display:none;width:8px;height:8px;border-radius:50%;background:#e74c3c;flex-shrink:0;animation:staPulse 1.4s infinite;"></span>
                    <span><?php echo esc_html( trim( $block['gender_icon'] . ' ' . $block['gender_label'] ) ); ?></span>
                    <span style="opacity:.8;">·</span>
                    <span><?php echo esc_html( ( $block['slug'] === 'a_finals' && empty( $block['custom_label'] ) ) ? 'Finals' : $block['session_label'] ); ?></span>
                    <span style="font-weight:400;opacity:.7;font-size:12px;"><?php echo esc_html( $day_str ); ?></span>
                    <button class="sta-toggle-btn" onclick="event.stopPropagation();staToggleBlock(this.closest('.sta-result-block').querySelector('[onclick]'))" style="margin-left:auto;background:none;border:none;color:#fff;font-size:14px;cursor:pointer;padding:0;line-height:1;"><?php echo $collapsed ? '▼' : '▲'; ?></button>
                </div>

                <div class="sta-rb-body" style="<?php echo $collapsed ? 'display:none;' : ''; ?>">
                <?php if ( empty( $block['agegroups'] ) ) : ?>
                <div style="padding:16px;text-align:center;font-size:13px;color:#999;border:1px solid #e0e7f0;border-top:none;border-radius:0 0 8px 8px;">
                    Nog geen uitslagen beschikbaar
                </div>
                <?php endif; ?>
                <?php $ag_count = count( $block['agegroups'] ); foreach ( $block['agegroups'] as $agid => $ag ) :
                    $is_para = stripos( $ag['name'], 'para' ) !== false;
                ?>
                <?php if ( $is_para ) : ?>
                <div style="font-size:12px;font-weight:700;color:#c0392b;padding:6px 12px 2px;background:#fff;border-left:1px solid #e0e7f0;border-right:1px solid #e0e7f0;">
                    PARA — <?php echo esc_html( $ag['name'] ); ?>
                </div>
                <?php elseif ( $ag_count > 1 ) : ?>
                <div style="font-size:12px;font-weight:700;color:#13315C;padding:6px 12px 2px;background:#f8f9fb;border-left:1px solid #e0e7f0;border-right:1px solid #e0e7f0;">
                    <?php echo esc_html( $ag['name'] ); ?>
                </div>
                <?php endif; ?>
                <div style="overflow-x:auto;border:1px solid #e0e7f0;border-top:none;<?php echo $is_para ? '' : 'border-radius:0 0 8px 8px;'; ?>">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tbody>
                    <?php foreach ( $ag['entries'] as $row ) :
                        $show_medal = ( $block['slug'] !== 'bc_finals' );
                        $medal_bg = '';
                        if ( $show_medal && $row['medal'] === 1 )     $medal_bg = 'background:#FFF9E6;';
                        elseif ( $show_medal && $row['medal'] === 2 ) $medal_bg = 'background:#F5F5F5;';
                        elseif ( $show_medal && $row['medal'] === 3 ) $medal_bg = 'background:#FFF3E6;';
                        $qual     = $row['qualcode'];
                        $flag_iso = $row['nation'];
                        $athlete_url = $athlete_urls[ mb_strtolower( $row['nametext'], 'UTF-8' ) ] ?? null;
                    ?>
                    <tr class="<?php echo empty( $row['swimtime'] ) ? 'sta-dns' : ''; ?>" style="border-top:1px solid #dde4ed;<?php echo $medal_bg; ?>opacity:<?php echo empty( $row['swimtime'] ) ? '.5' : '1'; ?>">
                        <!-- Positie -->
                        <td style="padding:3px 8px;text-align:center;font-weight:700;color:#13315C;width:36px;white-space:nowrap;">
                            <?php
                            if ( $show_medal && $row['medal'] === 1 )     echo '🥇';
                            elseif ( $show_medal && $row['medal'] === 2 ) echo '🥈';
                            elseif ( $show_medal && $row['medal'] === 3 ) echo '🥉';
                            elseif ( $row['place'] > 0 )   echo esc_html( $row['place'] );
                            elseif ( empty( $row['swimtime'] ) ) echo '<span style="font-size:10px;color:#999;">DNS/DNF</span>';
                            else                           echo '<span style="color:#ccc;">–</span>';
                            ?>
                        </td>
                        <!-- Naam -->
                        <td style="padding:3px 8px;font-size:13px;font-weight:600;color:#0d2d63;">
                            <?php if ( $athlete_url ) : ?>
                            <a href="<?php echo esc_url( $athlete_url ); ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo esc_html( $row['nametext'] ); ?></a>
                            <?php else : ?>
                            <?php echo esc_html( $row['nametext'] ); ?>
                            <?php endif; ?>
                            <?php if ( $is_para && $row['handicap'] && $row['handicap'] !== '0' ) : ?>
                            <span style="font-size:10px;background:#fde8e8;color:#c0392b;padding:1px 6px;border-radius:50px;margin-left:4px;"><?php echo esc_html( $row['handicap'] ); ?></span>
                            <?php endif; ?>
                        </td>
                        <!-- Qualcode -->
                        <td style="padding:3px 4px;width:28px;text-align:center;white-space:nowrap;">
                            <?php if ( $qual && $qual !== '0' ) : ?>
                            <span style="font-size:10px;background:#27ae60;color:#fff;padding:1px 6px;border-radius:50px;font-weight:700;font-family:Georgia,serif;"><?php echo esc_html( $qual ); ?></span>
                            <?php endif; ?>
                        </td>
                        <!-- Club -->
                        <td style="padding:3px 8px;color:#777;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px;"><?php echo esc_html( $row['clubname'] ); ?></td>
                        <!-- Tijd -->
                        <td style="padding:3px 8px;text-align:right;font-weight:700;font-family:monospace;color:#13315C;white-space:nowrap;font-size:13px;width:72px;">
                            <?php echo esc_html( $row['swimtime'] ?: '–' ); ?>
                            <?php if ( $is_para && $row['handicappoints'] ) : ?>
                            <span style="display:block;font-size:11px;font-weight:700;color:#13315C;"><?php echo esc_html( $row['handicappoints'] ); ?> pts</span>
                            <?php endif; ?>
                        </td>
                        <!-- Diff -->
                        <td style="padding:3px 8px;text-align:right;font-size:11px;color:#888;white-space:nowrap;width:52px;font-family:monospace;">
                            <?php echo $row['diff'] ? esc_html( $row['diff'] ) : ''; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endforeach; ?>
                </div><!-- .sta-rb-body -->
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; // end results section ?>

        <?php if ( $sta_meet_id && ! empty( $live_event_ids ) ) : ?>
        <script>
        (function() {
            var meetId    = <?php echo json_encode( $sta_meet_id ); ?>;
            var eventIds  = <?php echo json_encode( $live_event_ids ); ?>;
            var termSlug  = <?php echo json_encode( $term->slug ); ?>;
            var ajaxUrl   = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            var buildnr    = null;
            var meetIsLive = false;
            var $status    = document.getElementById('sta-live-status');

            function pad(n) { return n < 10 ? '0' + n : n; }
            function timeNow() { var d = new Date(); return pad(d.getHours()) + ':' + pad(d.getMinutes()); }

            function setLive() {
                if (!$status) return;
                $status.innerHTML =
                    '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#e74c3c;margin-right:5px;vertical-align:middle;animation:staPulse 1.4s infinite;"></span>' +
                    'Live <span style="font-weight:400;opacity:.7;margin-left:6px;">· bijgewerkt om ' + timeNow() + '</span>';
            }

            function applyHtml(html) {
                if (!html) return;
                // Snapshot current open/closed states before replacing DOM
                document.querySelectorAll('.sta-result-block[data-block-key]').forEach(function(el) {
                    var body = el.querySelector('.sta-rb-body');
                    if (body) staBlockStates[el.dataset.blockKey] = body.style.display !== 'none';
                });
                var parser = new DOMParser();
                var doc    = parser.parseFromString(html, 'text/html');
                var newSec = doc.getElementById('sta-results-section');
                var curSec = document.getElementById('sta-results-section');
                if (newSec && curSec) {
                    var newStatus = newSec.querySelector('#sta-live-status');
                    if (newStatus && $status) newStatus.replaceWith($status);
                    curSec.replaceWith(newSec);
                    $status = document.getElementById('sta-live-status');
                    staAutoExpand();
                    staApplyBlockStates();
                }
                if (meetIsLive) setLive();
            }

            function refreshSection() {
                var body = new URLSearchParams({ action: 'sta_live_refresh', meet_id: meetId, term_slug: termSlug });
                eventIds.forEach(function(id) { body.append('event_ids[]', id); });

                fetch(ajaxUrl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) return;
                        if (data.data && data.data.html) applyHtml(data.data.html);
                    })
                    .catch(function() {});
            }

            function poll() {
                fetch('https://live.swimrankings.net/meets/' + meetId + '/main.json')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var isLive = data.status === 3 || data.status === '3';
                        var newBuildnr = data.buildnr;
                        var buildnrChanged = (buildnr !== null && newBuildnr !== buildnr);
                        buildnr = newBuildnr;

                        if (isLive) {
                            meetIsLive = true;
                            setLive();
                            refreshSection(); // always refresh while live
                        } else {
                            meetIsLive = false;
                            if (buildnrChanged) refreshSection(); // catch late publishes
                            // else: do nothing — no unnecessary load outside competition
                        }
                    })
                    .catch(function() {});
            }
            // First load: always fetch once to show latest results
            refreshSection();

            // CSS pulse animation
            var style = document.createElement('style');
            style.textContent = '@keyframes staPulse{0%,100%{opacity:1}50%{opacity:.3}}';
            document.head.appendChild(style);

            poll();
            setInterval(poll, 20000);
        })();
        </script>
        <?php endif; ?>

        <?php
        $all_athletes = get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [ [
                'taxonomy' => 'afstand',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ] ],
        ] );

        $is_relay = preg_match( '/\d+\s*x/i', $term->name );
        ?>

        <style>
        .sta-gender-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        .sta-athlete-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }
        .sta-team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }
        .sta-team-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .sta-team-card-head {
            background: #13315C;
            color: #fff;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sta-team-card-athletes {
            padding: 8px 0;
        }
        .sta-team-card-athlete {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            font-size: 13px;
            color: #13315C;
            text-decoration: none;
            transition: background .1s;
        }
        .sta-team-card-athlete:hover { background: #f0f4f8; }
        @media (max-width: 900px) {
            .sta-gender-columns { gap: 8px; }
            .sta-athlete-grid   { grid-template-columns: 1fr !important; gap: 8px; }
            .sta-team-grid      { grid-template-columns: 1fr; }
            .sta-athlete-card-img  { aspect-ratio: 1 !important; }
            .sta-athlete-card-name { font-size: 12px !important; }
            .sta-athlete-card-club { display: none; }
        }
        </style>

        <?php if ( $is_relay ) :
            // ── Relay: fetch actual entries from API per program event ─────────
            $meet_id = get_option( 'sta_swimrankings_meet_id', '' );

            // Collect all program entries for this relay term
            $relay_entries = [];
            if ( class_exists( 'STA_Program' ) ) {
                foreach ( STA_Program::get_program() as $pe ) {
                    if ( $pe['relay'] && $pe['term_name'] === $term->name && ! empty( $pe['event_id'] ) ) {
                        $relay_entries[] = $pe;
                    }
                }
            }

            $gender_icon = [ 'f' => '♀', 'm' => '♂', 'x' => '♀♂' ];

            foreach ( $relay_entries as $pe ) :
                $event_id  = $pe['event_id'];
                $cache_key = 'sta_relay_heats_' . $event_id;
                $heats_data = get_transient( $cache_key );

                if ( $heats_data === false && $meet_id ) {
                    $url      = 'https://live.swimrankings.net/meets/' . $meet_id . '/heats/' . $event_id . '.json';
                    $response = wp_remote_get( $url, [ 'timeout' => 10, 'sslverify' => false ] );
                    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                        $heats_data = json_decode( wp_remote_retrieve_body( $response ), true );
                        set_transient( $cache_key, $heats_data, HOUR_IN_SECONDS );
                    }
                }

                if ( empty( $heats_data['heats'] ) ) continue;

                // Collect all entries across heats, sorted by heat then lane
                $all_entries = [];
                foreach ( $heats_data['heats'] as $heat ) {
                    $heat_num = $heat['heatinfo']['number'] ?? $heat['id'] ?? 0;
                    foreach ( ( $heat['entries'] ?? [] ) as $entry ) {
                        if ( empty( $entry['nametext'] ) ) continue;
                        $relay_swimmers = [];
                        foreach ( $entry['relayathletes'] ?? [] as $ra ) {
                            $swimmer_name = trim( ( $ra['lastname'] ?? '' ) . ' ' . ( $ra['firstname'] ?? '' ) );
                            if ( $swimmer_name ) $relay_swimmers[] = $swimmer_name;
                        }
                        $all_entries[] = [
                            'heat'       => $heat_num,
                            'lane'       => $entry['lane']       ?? 0,
                            'name'       => $entry['nametext'],
                            'nation'     => strtolower( $entry['nation'] ?? '' ),
                            'entrytime'  => $entry['entrytime']  ?? '',
                            'teamnumber' => $entry['teamnumber'] ?? 1,
                            'swimmers'   => $relay_swimmers,
                        ];
                    }
                }
                if ( empty( $all_entries ) ) continue;

                usort( $all_entries, fn( $a, $b ) =>
                    $a['heat'] !== $b['heat'] ? $a['heat'] - $b['heat'] : $a['lane'] - $b['lane']
                );

                $gender_lbl   = $gender_icon[ $pe['gender'] ] ?? '';
                $session_lbl  = $pe['session_label'];
                $total_teams  = count( $all_entries );
                $start_time   = $pe['start_time'] ?: ( $pe['approx_time'] ?? '' );
            ?>

            <div style="margin-bottom:36px;">
                <h2 style="
                    font-size:20px;font-weight:700;color:#fff;
                    background:#13315C;display:inline-block;
                    padding:6px 20px;border-radius:50px;margin:0 0 16px;
                ">
                    <?php echo esc_html( $gender_lbl . ' ' . $session_lbl ); ?>
                    <?php if ( $start_time ) : ?>
                    <span style="font-size:14px;font-weight:400;opacity:.7;margin-left:4px;">· <?php echo esc_html( $start_time ); ?></span>
                    <?php endif; ?>
                    <span style="font-size:15px;font-weight:400;opacity:.75;margin-left:4px;">
                        (<?php echo $total_teams; ?> team<?php echo $total_teams !== 1 ? 's' : ''; ?>)
                    </span>
                </h2>

                <div class="sta-team-grid">
                <?php foreach ( $all_entries as $idx => $entry ) :
                    $display_name  = $entry['name'];
                    $club_key      = trim( preg_replace( '/\s+\d+$/', '', $display_name ) );
                    $team_logo_url = $club_logos[ $club_key ] ?? $club_logos[ $display_name ] ?? '';
                ?>
                    <div class="sta-team-card">
                        <div class="sta-team-card-head" style="gap:10px;">
                            <?php if ( $team_logo_url ) : ?>
                            <img src="<?php echo esc_url( $team_logo_url ); ?>" style="max-height:32px;width:auto;flex-shrink:0;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <?php echo esc_html( $display_name ); ?>
                        </div>
                        <div style="padding:8px 16px 10px;border-top:1px solid #e8eef4;">
                            <?php if ( ! empty( $entry['swimmers'] ) ) : ?>
                            <ol style="margin:0 0 6px;padding-left:18px;font-size:12px;color:#444;line-height:1.7;">
                                <?php foreach ( $entry['swimmers'] as $sw ) : ?>
                                <li><?php echo esc_html( $sw ); ?></li>
                                <?php endforeach; ?>
                            </ol>
                            <?php endif; ?>
                            <?php if ( $entry['entrytime'] ) : ?>
                            <div style="font-size:12px;color:#888;">Inschrijftijd: <strong style="color:#13315C;"><?php echo esc_html( $entry['entrytime'] ); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <?php endforeach;

        elseif ( empty( $all_athletes ) ) : ?>
            <p style="text-align:center;color:#999;">No athletes found for this event.</p>

        <?php else :
            // ── Individual event: grouped by gender ────────────────────────────
            $entry_time_key = 'sta_entry_time_' . $term->slug;

            // Helper: convert swim time string "m:ss.hh" or "ss.hh" to centiseconds for sorting
            $parse_time = function( $t ) {
                if ( ! $t || $t === 'NT' ) return PHP_INT_MAX;
                $parts = explode( ':', $t );
                if ( count( $parts ) === 2 ) {
                    return (int)$parts[0] * 6000 + (int)round( (float)$parts[1] * 100 );
                }
                return (int)round( (float)$t * 100 );
            };

            $groups = [ 'Women' => [], 'Men' => [] ];
            foreach ( $all_athletes as $athlete ) {
                $gender = get_post_meta( $athlete->ID, 'sta_gender', true );
                if ( $gender === 'f' )     $groups['Women'][] = $athlete;
                elseif ( $gender === 'm' ) $groups['Men'][]   = $athlete;
            }
            foreach ( $groups as &$group ) {
                usort( $group, function( $a, $b ) use ( $entry_time_key, $parse_time ) {
                    $ta = get_post_meta( $a->ID, $entry_time_key, true );
                    $tb = get_post_meta( $b->ID, $entry_time_key, true );
                    $ca = $parse_time( $ta );
                    $cb = $parse_time( $tb );
                    if ( $ca !== $cb ) return $ca - $cb;
                    return strcmp( $a->post_title, $b->post_title );
                } );
            }
            unset( $group );
        ?>

            <div class="sta-gender-columns">
            <?php foreach ( $groups as $label => $athletes ) :
                if ( empty( $athletes ) ) continue; ?>

                <div>
                <h2 style="
                    font-size:22px;font-weight:700;color:#fff;
                    background:#0d2d63;display:inline-block;
                    padding:6px 20px;border-radius:50px;margin:0 0 20px;
                "><?php echo esc_html( $label ); ?> <span style="font-size:16px;font-weight:400;opacity:.75;">(<?php echo count( $athletes ); ?>)</span></h2>

                <div class="sta-athlete-grid">
                    <?php foreach ( $athletes as $athlete ) :
                        $country_code = get_post_meta( $athlete->ID, 'sta_country', true );
                        $club         = get_post_meta( $athlete->ID, 'sta_club', true );
                        $featured     = (bool) get_post_meta( $athlete->ID, 'sta_featured', true );
                        $entry_time   = get_post_meta( $athlete->ID, $entry_time_key, true );
                    ?>
                        <a href="<?php echo get_permalink( $athlete->ID ); ?>" style="
                            text-decoration:none;color:inherit;display:block;
                            background:#fff;border:1px solid #e5e7eb;border-radius:12px;
                            overflow:hidden;transition:box-shadow .15s;
                            <?php echo $featured ? 'box-shadow:0 0 0 2px #13315C;' : ''; ?>
                        " onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
                           onmouseout="this.style.boxShadow='<?php echo $featured ? '0 0 0 2px #13315C' : 'none'; ?>'">

                            <?php if ( STA_Modules::is_active( 'photos' ) && has_post_thumbnail( $athlete->ID ) ) : ?>
                                <div class="sta-athlete-card-img" style="aspect-ratio:3/4;overflow:hidden;">
                                    <?php echo get_the_post_thumbnail( $athlete->ID, 'medium', [
                                        'style' => 'width:100%;height:100%;object-fit:cover;display:block;' . sta_focus_style( $athlete->ID ),
                                    ] ); ?>
                                </div>
                            <?php elseif ( STA_Modules::is_active( 'photos' ) ) : ?>
                                <div class="sta-athlete-card-img" style="aspect-ratio:3/4;background:#e5e7eb;display:flex;align-items:center;justify-content:center;">
                                    <span style="font-size:48px;opacity:.3;">🏊</span>
                                </div>
                            <?php endif; ?>

                            <div style="padding:10px 12px;">
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                                    <strong class="sta-athlete-card-name" style="font-size:13px;color:#0d2d63;line-height:1.2;">
                                        <?php echo esc_html( $athlete->post_title ); ?>
                                    </strong>
                                </div>
                                <?php if ( $club ) : ?>
                                    <div class="sta-athlete-card-club" style="font-size:12px;color:#666;"><?php echo esc_html( $club ); ?></div>
                                <?php endif; ?>
                                <?php if ( $entry_time && $entry_time !== 'NT' ) : ?>
                                    <div style="font-size:12px;color:#888;margin-top:2px;font-family:monospace;"><?php echo esc_html( $entry_time ); ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                </div>

            <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

</main>

<?php get_footer(); ?>
