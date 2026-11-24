<?php
get_header();

$term = get_queried_object();

$_s           = get_option( 'sta_layout', [] );
$_header_id   = $_s['header_image_id'] ?? 0;
$_header_url  = $_header_id ? wp_get_attachment_image_url( $_header_id, 'full' ) : '';
$_header_h    = (int)( $_s['header_height'] ?? 220 );
$_color       = esc_attr( $_s['primary_color'] ?? '#13315c' );
?>

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
                        $bg       = $session_bg[ $pe['session_slug'] ] ?? '#13315C';
                        $day_str  = date( 'l j F', strtotime( $pe['date'] ) );
                        $sess_str = $pe['session_label'];
                        $time_str = $pe['start_time'] ? $pe['start_time'] : $pe['approx_time'];
                        $pos_str  = STA_Program::ordinal( $pe['order'] ) . ' event in session';
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
            $athlete_urls[ strtolower( get_the_title( $apid ) ) ] = get_permalink( $apid );
        }

        $event_results = get_option( 'sta_event_results', [] );
        $club_logos    = get_option( 'sta_club_logos', [] );
        if ( class_exists( 'STA_Program' ) ) :
            // Build result blocks per session × gender, in chronological order
            $slug_order = [ 'heats' => 0, 'semis' => 1, 'bc_finals' => 2, 'finals' => 2, 'a_finals' => 3 ];
            $result_blocks = []; // key => [session_label, slug, date, gender, agegroups[]]

            $today        = date( 'Y-m-d' );
            $eid_term_map = get_option( 'sta_eid_term_map', [] );
            foreach ( STA_Program::get_program() as $pe ) {
                if ( ( $pe['date'] ?? '' ) > $today ) continue;       // don't show future events
                $term_is_relay = preg_match( '/\d+\s*x/i', $term->name );
                if ( $pe['relay'] && ! $term_is_relay ) continue;      // relay results only on relay pages
                if ( ! $pe['relay'] && $term_is_relay ) continue;      // individual results only on individual pages

                // Collect all event_ids for this program entry (senior + U18 share same bucket)
                $eids = ! empty( $pe['event_ids'] ) ? $pe['event_ids'] : ( $pe['event_id'] ? [ $pe['event_id'] ] : [] );

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
                    $result_blocks[ $block_key ] = [
                        'date'          => $pe['date'],
                        'session_label' => $pe['session_label'],
                        'slug'          => $pe['session_slug'],
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
        function staAutoExpand() {
            var now  = new Date();
            var today = now.toISOString().slice(0, 10);
            var nowMins = now.getHours() * 60 + now.getMinutes();

            // Find the single most-advanced block that has started (or starts within 5 min).
            // "Most advanced" = latest start time, so finales beats halve finales beats series.
            var best = null, bestMins = -1;
            document.querySelectorAll('.sta-result-block[data-collapsed="1"][data-start-time]').forEach(function(el) {
                if (el.dataset.date !== today) return;
                var t = el.dataset.startTime;
                if (!t) return;
                var parts = t.split(':');
                var startMins = parseInt(parts[0]) * 60 + parseInt(parts[1]);
                if (nowMins >= startMins - 5 && startMins > bestMins) {
                    bestMins = startMins;
                    best = el;
                }
            });

            if (best) {
                best.dataset.collapsed = '0';
                var body = best.querySelector('.sta-rb-body');
                var btn  = best.querySelector('.sta-toggle-btn');
                if (body) body.style.display = '';
                if (btn)  btn.textContent = '▲';
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
            if (block.dataset.collapsed === '1') {
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
                $bg        = $slug_bg[ $block['slug'] ] ?? '#13315C';
                $day_str   = date( 'l j F', strtotime( $block['date'] ) );
                $rank      = $slug_order[ $block['slug'] ] ?? 0;
                $collapsed = empty( $block['agegroups'] ) || $rank < $max_rank_results;
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

                            <?php if ( has_post_thumbnail( $athlete->ID ) ) : ?>
                                <div class="sta-athlete-card-img" style="aspect-ratio:3/4;overflow:hidden;">
                                    <?php echo get_the_post_thumbnail( $athlete->ID, 'medium', [
                                        'style' => 'width:100%;height:100%;object-fit:cover;display:block;' . sta_focus_style( $athlete->ID ),
                                    ] ); ?>
                                </div>
                            <?php else : ?>
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
