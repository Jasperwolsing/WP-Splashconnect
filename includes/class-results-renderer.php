<?php

class STA_Results_Renderer {

    public static function render( WP_Term $term ): string {
        if ( ! class_exists( 'STA_Program' ) ) return '';

        $event_results = get_option( 'sta_event_results', [] );
        $slug_order    = [ 'heats' => 0, 'semis' => 1, 'bc_finals' => 2, 'finals' => 2, 'a_finals' => 3 ];
        $result_blocks = [];
        $today         = date( 'Y-m-d' );

        // Athlete name → permalink map
        $athlete_urls = [];
        foreach ( get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [ [ 'taxonomy' => 'afstand', 'field' => 'term_id', 'terms' => $term->term_id ] ],
        ] ) as $apid ) {
            $athlete_urls[ strtolower( get_the_title( $apid ) ) ] = get_permalink( $apid );
        }

        $eid_term_map = get_option( 'sta_eid_term_map', [] );
        $club_logos   = get_option( 'sta_club_logos', [] );
        $meet_id      = get_option( 'sta_swimrankings_meet_id', '' );

        $term_is_relay = preg_match( '/\d+\s*x/i', $term->name );
        foreach ( STA_Program::get_program() as $pe ) {
            if ( ( $pe['date'] ?? '' ) > $today ) continue;
            if ( $pe['relay'] && ! $term_is_relay ) continue;
            if ( ! $pe['relay'] && $term_is_relay ) continue;

            // Match via direct event_id → term_slug map (set by Step 2), fallback to term_name
            $eids = ! empty( $pe['event_ids'] ) ? $pe['event_ids'] : ( $pe['event_id'] ? [ $pe['event_id'] ] : [] );
            $matched = false;
            foreach ( $eids as $eid ) {
                if ( ( $eid_term_map[$eid] ?? '' ) === $term->slug ) { $matched = true; break; }
            }
            if ( ! $matched ) {
                $pe_term = $pe['term_name'] ?? '';
                if ( $pe_term !== $term->name && strpos( $pe_term, $term->name ) === false ) continue;
            }

            $has = false;
            foreach ( $eids as $eid ) { if ( ! empty( $event_results[$eid] ) ) { $has = true; break; } }
            if ( ! $has ) continue;

            $gender_label = $pe['gender'] === 'f' ? 'Women' : ( $pe['gender'] === 'x' ? 'Mixed' : 'Men' );
            $gender_icon  = $pe['gender'] === 'f' ? '♀' : ( $pe['gender'] === 'x' ? '' : '♂' );
            $block_key    = $pe['date'] . '|' . $pe['session'] . '|' . $pe['gender'];

            if ( ! isset( $result_blocks[$block_key] ) ) {
                $result_blocks[$block_key] = [
                    'date'          => $pe['date'],
                    'session_label' => $pe['session_label'],
                    'slug'          => $pe['session_slug'],
                    'gender_label'  => $gender_label,
                    'gender_icon'   => $gender_icon,
                    'sort'          => $pe['date'] . '|' . ( $slug_order[ $pe['session_slug'] ] ?? 9 ) . '|' . $pe['gender'],
                    'start_time'    => $pe['start_time'] ?: ( $pe['approx_time'] ?? '' ),
                    'agegroups'     => [],
                    'event_ids'     => [],
                ];
            }
            $result_blocks[$block_key]['event_ids'] = array_unique(
                array_merge( $result_blocks[$block_key]['event_ids'], $eids )
            );

            foreach ( $eids as $eid ) {
                if ( empty( $event_results[$eid] ) ) continue;
                foreach ( $event_results[$eid] as $agid => $ag_data ) {
                    if ( empty( $ag_data['entries'] ) ) continue;
                    if ( isset( $result_blocks[$block_key]['agegroups'][$agid] ) ) continue;
                    $entries = $ag_data['entries'];
                    usort( $entries, fn( $a, $b ) => ( $a['place'] > 0 ? $a['place'] : 9999 ) - ( $b['place'] > 0 ? $b['place'] : 9999 ) );
                    $result_blocks[$block_key]['agegroups'][$agid] = [
                        'name'      => $ag_data['name'],
                        'scoretype' => $ag_data['scoretype'],
                        'entries'   => $entries,
                    ];
                }
            }
        }

        if ( empty( $result_blocks ) ) return '';

        // Fetch startlijst for semis/finals
        $startlijst_slugs = [ 'heats', 'semis', 'finals', 'a_finals', 'bc_finals' ];
        if ( $meet_id ) {
            foreach ( $result_blocks as $bk => &$block ) {
                if ( ! in_array( $block['slug'], $startlijst_slugs, true ) ) continue;
                $startlijst = [];
                foreach ( $block['event_ids'] as $eid ) {
                    $cache_key  = 'sta_heats_startlijst_' . $eid;
                    $heats_data = get_transient( $cache_key );
                    if ( $heats_data === false ) {
                        $url      = 'https://live.swimrankings.net/meets/' . $meet_id . '/heats/' . $eid . '.json';
                        $response = wp_remote_get( $url, [ 'timeout' => 10, 'sslverify' => false ] );
                        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                            $heats_data = json_decode( wp_remote_retrieve_body( $response ), true );
                            set_transient( $cache_key, $heats_data, HOUR_IN_SECONDS );
                        }
                    }
                    if ( empty( $heats_data['heats'] ) ) continue;
                    // Build agegroupid → name map: try heats JSON first, fall back to block agegroups
                    $ag_name_map = [];
                    foreach ( $heats_data['agegroups'] ?? [] as $ag ) {
                        foreach ( [ 'id', 'agegroupid', 'ageGroupId' ] as $k ) {
                            if ( isset( $ag[$k] ) ) { $ag_name_map[ $ag[$k] ] = $ag['name'] ?? $ag['agegroupname'] ?? null; break; }
                        }
                    }
                    // Also index block agegroups by their numeric id (agid key = the id used in sta_event_results)
                    foreach ( $block['agegroups'] as $agid => $ag_data ) {
                        if ( ! isset( $ag_name_map[ $agid ] ) ) $ag_name_map[ $agid ] = $ag_data['name'];
                    }
                    foreach ( $heats_data['heats'] as $heat ) {
                        $heat_num  = $heat['heatinfo']['number'] ?? $heat['id'] ?? 0;
                        // Try every plausible agegroupid field name
                        $ag_id_ref = $heat['heatinfo']['agegroupid']
                                  ?? $heat['heatinfo']['ageGroupId']
                                  ?? $heat['heatinfo']['agegroupId']
                                  ?? null;
                        $heat_lbl  = ( $ag_id_ref !== null && isset( $ag_name_map[ $ag_id_ref ] ) )
                                   ? $ag_name_map[ $ag_id_ref ]
                                   : null;
                        if ( ! isset( $startlijst[ $heat_num ] ) ) {
                            $startlijst[ $heat_num ] = [ 'label' => $heat_lbl, 'sort' => $heat_num, 'lanes' => [] ];
                        }
                        foreach ( ( $heat['entries'] ?? [] ) as $entry ) {
                            if ( empty( $entry['nametext'] ) ) continue;
                            $lane = (int)( $entry['lane'] ?? 0 );
                            if ( $lane === 0 ) continue; // reservezwemmers overslaan
                            $startlijst[ $heat_num ]['lanes'][] = [
                                'lane'       => $lane,
                                'name'       => $entry['nametext'],
                                'club'       => $entry['clubname'] ?? '',
                                'nation'     => strtolower( $entry['nation'] ?? '' ),
                                'entrytime'  => $entry['entrytime'] ?? '',
                            ];
                        }
                    }
                }
                foreach ( $startlijst as $hn => &$heat_block ) {
                    usort( $heat_block['lanes'], fn( $a, $b ) => $a['lane'] - $b['lane'] );
                }
                unset( $heat_block );
                // Remove empty heats, sort by heat number
                $startlijst = array_filter( $startlijst, fn( $h ) => ! empty( $h['lanes'] ) );
                ksort( $startlijst );
                $block['startlijst'] = $startlijst;
            }
            unset( $block );
        }

        uasort( $result_blocks, fn( $a, $b ) => strcmp( $b['sort'], $a['sort'] ) );

        // Highest rank among blocks WITH actual results (empty blocks don't influence collapse)
        $slug_order = [ 'heats' => 0, 'semis' => 1, 'bc_finals' => 2, 'finals' => 2, 'a_finals' => 3 ];
        $max_rank = 0;
        foreach ( $result_blocks as $block ) {
            if ( empty( $block['agegroups'] ) ) continue;
            $r = $slug_order[ $block['slug'] ] ?? 0;
            if ( $r > $max_rank ) $max_rank = $r;
        }

        $slug_bg = [
            'heats'    => '#1a5276',
            'semis'    => '#1a5276',
            'bc_finals'=> '#1a6a8a',
            'finals'   => '#13315C',
            'a_finals' => '#13315C',
        ];

        ob_start();
        ?>
        <div id="sta-results-section" style="margin-bottom:48px;">
            <h2 style="font-size:24px;font-weight:700;color:#0d2d63;margin-bottom:4px;text-align:center;">Uitslagen</h2>
            <p id="sta-live-status" style="text-align:center;font-size:12px;color:#999;margin-bottom:20px;min-height:16px;"></p>

            <?php foreach ( $result_blocks as $block ) :
                $bg        = $slug_bg[ $block['slug'] ] ?? '#13315C';
                $day_str   = date( 'l j F', strtotime( $block['date'] ) );
                $rank      = $slug_order[ $block['slug'] ] ?? 0;
                $collapsed = empty( $block['agegroups'] ) || $rank < $max_rank;
                $block_key = esc_attr( $block['date'] . '|' . $block['slug'] . '|' . $block['gender_label'] );
            ?>
            <div class="sta-result-block" data-block-key="<?php echo $block_key; ?>" data-collapsed="<?php echo $collapsed ? '1' : '0'; ?>" data-start-time="<?php echo esc_attr( $block['start_time'] ?? '' ); ?>" data-date="<?php echo esc_attr( $block['date'] ); ?>" style="margin-bottom:12px;">
                <div onclick="staToggleBlock(this)" style="background:<?php echo $bg; ?>;color:#fff;border-radius:<?php echo $collapsed ? '10px' : '10px 10px 0 0'; ?>;padding:10px 16px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;">
                    <span><?php echo esc_html( trim( $block['gender_icon'] . ' ' . $block['gender_label'] ) ); ?></span>
                    <span style="opacity:.8;">·</span>
                    <span><?php echo esc_html( $block['session_label'] ); ?></span>
                    <span style="font-weight:400;opacity:.7;font-size:12px;"><?php echo esc_html( $day_str ); ?></span>
                    <button class="sta-toggle-btn" onclick="event.stopPropagation();staToggleBlock(this.closest('.sta-result-block').querySelector('[onclick]'))" style="margin-left:auto;background:none;border:none;color:#fff;font-size:14px;cursor:pointer;padding:0;line-height:1;"><?php echo $collapsed ? '▼' : '▲'; ?></button>
                </div>

                <div class="sta-rb-body" style="<?php echo $collapsed ? 'display:none;' : ''; ?>">
                <?php if ( ! empty( $block['startlijst'] ) ) :
                    $sl_id       = 'sta-sl-' . md5( $block_key );
                    $has_results = ! empty( $block['agegroups'] );
                    $sl_open     = ! $has_results;
                ?>
                <div style="border:1px solid #e0e7f0;border-top:none;background:#f8f9fb;">
                    <button onclick="(function(b){var p=b.nextElementSibling;var open=p.style.display!=='none';p.style.display=open?'none':'block';b.querySelector('.sta-sl-arrow').textContent=open?'▶':'▼';})(this)"
                        style="width:100%;text-align:left;background:none;border:none;border-bottom:1px solid #e0e7f0;padding:8px 14px;font-size:12px;font-weight:700;color:#13315C;cursor:pointer;display:flex;align-items:center;gap:6px;">
                        <span class="sta-sl-arrow"><?php echo $sl_open ? '▼' : '▶'; ?></span>
                        Startlijst
                    </button>
                    <div style="<?php echo $sl_open ? '' : 'display:none;'; ?>padding:10px 14px;">
                    <?php foreach ( $block['startlijst'] as $heat_num => $heat_block ) :
                        $lanes      = $heat_block['lanes'];
                        $heat_lbl   = $heat_block['label'];
                        $heat_count = count( $block['startlijst'] );
                    ?>
                        <?php if ( $heat_count > 1 ) :
                            $serie_nr    = array_search( $heat_num, array_keys( $block['startlijst'] ) ) + 1;
                            $round_prefix = in_array( $block['slug'], [ 'finals', 'a_finals', 'bc_finals' ], true )
                                          ? 'Finale'
                                          : ( $block['slug'] === 'semis' ? 'Halve finale' : 'Serie' );
                            $lbl_text = $heat_lbl ?: ( $round_prefix . ' ' . $serie_nr );
                        ?>
                        <div style="font-size:11px;font-weight:700;color:#13315C;text-transform:uppercase;letter-spacing:.04em;margin:<?php echo $serie_nr > 1 ? '14px' : '0px'; ?> 0 4px;<?php echo $serie_nr > 1 ? 'padding-top:10px;border-top:1px solid #e8eef4;' : ''; ?>"><?php echo esc_html( $lbl_text ); ?></div>
                        <?php endif; ?>
                        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:<?php echo $heat_count > 1 ? '10' : '0'; ?>px;table-layout:fixed;">
                            <colgroup>
                                <col style="width:32px;">
                                <col style="width:45%;">
                                <col>
                                <col style="width:72px;">
                            </colgroup>
                            <tbody>
                            <?php foreach ( $lanes as $row ) : ?>
                            <tr style="border-top:1px solid #edf1f7;">
                                <td style="padding:5px 8px;text-align:center;font-weight:700;color:#13315C;"><?php echo esc_html( $row['lane'] ); ?></td>
                                <td style="padding:5px 8px;font-weight:600;color:#0d2d63;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?php $athlete_url = $athlete_urls[ strtolower( $row['name'] ) ] ?? null; ?>
                                    <?php if ( $athlete_url ) : ?>
                                    <a href="<?php echo esc_url( $athlete_url ); ?>" style="color:#0d2d63;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo esc_html( $row['name'] ); ?></a>
                                    <?php else : ?>
                                    <?php echo esc_html( $row['name'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:5px 8px;color:#888;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $row['club'] ); ?></td>
                                <td style="padding:5px 8px;text-align:right;font-family:monospace;color:#555;white-space:nowrap;"><?php echo esc_html( $row['entrytime'] ?: '–' ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
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
                        $medal_bg = '';
                        if ( $row['medal'] === 1 )     $medal_bg = 'background:#FFF9E6;';
                        elseif ( $row['medal'] === 2 ) $medal_bg = 'background:#F5F5F5;';
                        elseif ( $row['medal'] === 3 ) $medal_bg = 'background:#FFF3E6;';
                        $qual     = $row['qualcode'];
                        $flag_iso = $row['nation'];
                    ?>
                    <tr style="border-top:1px solid #f0f4f8;<?php echo $medal_bg; ?>">
                        <td style="padding:8px 10px;text-align:center;font-weight:700;color:#13315C;width:36px;">
                            <?php
                            if ( $row['medal'] === 1 )     echo '🥇';
                            elseif ( $row['medal'] === 2 ) echo '🥈';
                            elseif ( $row['medal'] === 3 ) echo '🥉';
                            elseif ( $row['place'] > 0 )   echo esc_html( $row['place'] );
                            elseif ( empty( $row['swimtime'] ) ) echo '<span style="font-size:10px;color:#999;">DNS/DNF</span>';
                            else                           echo '<span style="color:#ccc;">–</span>';
                            ?>
                        </td>
                        <td style="padding:8px 10px;">
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <?php $athlete_url = $athlete_urls[ strtolower( $row['nametext'] ) ] ?? null; ?>
                                <?php if ( $athlete_url ) : ?>
                                <a href="<?php echo esc_url( $athlete_url ); ?>" style="font-weight:600;color:#0d2d63;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo esc_html( $row['nametext'] ); ?></a>
                                <?php else : ?>
                                <span style="font-weight:600;color:#0d2d63;"><?php echo esc_html( $row['nametext'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( $qual && $qual !== '0' ) : ?>
                                <span style="font-size:10px;background:#27ae60;color:#fff;padding:1px 7px;border-radius:50px;font-weight:700;font-family:Georgia,serif;"><?php echo esc_html( $qual ); ?></span>
                                <?php endif; ?>
                                <?php if ( $is_para && $row['handicap'] && $row['handicap'] !== '0' ) : ?>
                                <span style="font-size:10px;background:#fde8e8;color:#c0392b;padding:1px 6px;border-radius:50px;"><?php echo esc_html( $row['handicap'] ); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding:8px 10px;color:#777;font-size:12px;"><?php echo esc_html( $row['clubname'] ); ?></td>
                        <td style="padding:8px 10px;text-align:right;font-weight:700;font-family:monospace;color:#13315C;white-space:nowrap;">
                            <?php echo esc_html( $row['swimtime'] ?: '–' ); ?>
                            <?php if ( $row['diff'] ) : ?>
                            <span style="font-size:10px;font-weight:400;color:#888;font-family:inherit;"> <?php echo esc_html( $row['diff'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( $is_para && $row['handicappoints'] ) : ?>
                            <span style="display:block;font-size:11px;font-weight:700;color:#13315C;"><?php echo esc_html( $row['handicappoints'] ); ?> pts</span>
                            <?php endif; ?>
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
        <?php
        return ob_get_clean();
    }
}
