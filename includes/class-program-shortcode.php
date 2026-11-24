<?php

class STA_Program_Shortcode {

    public static function register(): void {
        add_shortcode( 'sta_program', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ): string {
        $days = STA_Program::get_by_day();

        // Term-links cache: term_name → URL
        $term_links = [];
        foreach ( get_terms( [ 'taxonomy' => 'afstand', 'hide_empty' => false ] ) as $term ) {
            $term_links[ $term->name ] = get_term_link( $term );
        }

        $session_colors = [
            'heats'     => '#1d4e89',
            'bc_finals' => '#13315C',
            'a_finals'  => '#0c2340',
        ];
        $session_icons = [];

        ob_start();
        $uid = 'sta-prog-' . wp_rand( 1000, 9999 );
        ?>
        <style>
        .sta-program-wrap { font-family: inherit; }

        .sta-program-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .sta-program-tab {
            background: #e8edf5;
            color: #13315C;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s, color .15s;
        }
        .sta-program-tab.active,
        .sta-program-tab:hover {
            background: #13315C;
            color: #F6F6F6;
        }

        .sta-program-day { display: none; }
        .sta-program-day.active { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        @media (max-width: 700px) { .sta-program-day.active { grid-template-columns: 1fr; } }

        .sta-program-session {
            background: #fff;
            border: 1px solid #e0e7f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .sta-program-session-head {
            padding: 12px 16px;
            color: #F6F6F6;
            font-weight: 700;
            font-size: 14px;
        }
        .sta-program-event {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 14px;
            border-top: 1px solid #f0f4f8;
            font-size: 13px;
            color: #13315C;
            gap: 8px;
        }
        .sta-program-event:first-child { border-top: none; }
        .sta-program-event a {
            color: inherit;
            text-decoration: none;
            font-weight: 600;
        }
        .sta-program-event a:hover { text-decoration: underline; }
        .sta-program-event-label { flex: 1; }
        .sta-program-badge {
            font-size: 10px;
            font-weight: 700;
            background: #e8edf5;
            color: #13315C;
            padding: 2px 7px;
            border-radius: 50px;
            white-space: nowrap;
        }
        .sta-program-badge.para { background: #fde8e8; color: #c0392b; }
        .sta-program-badge.time { background: #13315C; color: #F6F6F6; }
        .sta-program-relay { }
        .sta-program-event.sta-program-njk { background: #f8f9fb; }
        .sta-program-badge.njk { background: #e0e7f0; color: #13315C; }
        .sta-program-note {
            font-size: 12px;
            color: #999;
            padding: 10px 14px;
            border-top: 1px solid #f0f4f8;
        }
        </style>

        <div class="sta-program-wrap" id="<?php echo $uid; ?>">

            <?php
            // Active tab: today if in program, else last past day, else first day
            $today_ymd   = date( 'Y-m-d' );
            $active_date = array_key_first( $days );
            foreach ( array_keys( $days ) as $d ) {
                if ( $d <= $today_ymd ) $active_date = $d;
            }
            ?>
            <div class="sta-program-tabs">
                <?php foreach ( $days as $date => $day_data ) : ?>
                    <button class="sta-program-tab <?php echo $date === $active_date ? 'active' : ''; ?>"
                            data-target="<?php echo esc_attr( $uid . '-' . $date ); ?>"
                            onclick="staProgramTab(this, '<?php echo esc_attr( $uid ); ?>')">
                        <?php echo esc_html( $day_data['day'] ); ?>
                        <span style="font-weight:400;opacity:.7;font-size:12px;margin-left:4px;">
                            <?php echo date( 'j/m', strtotime( $date ) ); ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ( $days as $date => $day_data ) : ?>
                <div class="sta-program-day <?php echo $date === $active_date ? 'active' : ''; ?>"
                     id="<?php echo esc_attr( $uid . '-' . $date ); ?>">

                    <?php foreach ( $day_data['sessions'] as $session_key => $session ) :
                        $bg = $session_colors[ $session_key ] ?? '#13315C';
                        $icon = $session_icons[ $session_key ] ?? '';
                    ?>
                        <div class="sta-program-session">
                            <div class="sta-program-session-head" style="background:<?php echo $bg; ?>;">
                                <?php echo esc_html( $session['label'] ); ?>
                                <span style="font-weight:400;opacity:.8;font-size:12px;margin-left:6px;">
                                    <?php echo esc_html( $session['approx_time'] ); ?>
                                </span>
                            </div>

                            <?php
                        $sorted_events = $session['events'];
                        usort( $sorted_events, function( $a, $b ) {
                            $ta = $a['start_time'] ?: ( $a['approx_time'] ?: '99:99' );
                            $tb = $b['start_time'] ?: ( $b['approx_time'] ?: '99:99' );
                            $cmp = strcmp( $ta, $tb );
                            if ( $cmp !== 0 ) return $cmp;
                            // Same time: events without individual start_time sort first
                            return ( $a['start_time'] ? 1 : 0 ) - ( $b['start_time'] ? 1 : 0 );
                        } );
                        foreach ( $sorted_events as $ev ) :
                                $gender_icon = $ev['gender'] === 'f' ? '♀' : '♂';
                                $time_label  = $ev['start_time'] ?: ( $ev['approx_time'] ?: '' );
                                $is_relay    = $ev['relay'];
                                $is_njk      = ! empty( $ev['njk'] );

                                if ( $is_relay ) {
                                    $label = $ev['term_name'] . ' ' . $gender_icon;
                                } elseif ( $is_njk ) {
                                    // "NJK 1500m Freestyle U16" → label zonder prefix, gender icon erachter
                                    $label = $ev['distance'] . 'm ' . $ev['stroke'] . ' ' . $gender_icon;
                                } else {
                                    $label = $ev['distance'] . 'm ' . $ev['stroke'] . ' ' . $gender_icon;
                                }

                                // Probeer term-link
                                $term_url = isset( $term_links[ $ev['term_name'] ] )
                                    ? $term_links[ $ev['term_name'] ]
                                    : null;

                                // Bepaal leeftijdscategorie voor NJK badge (uit term_name: "NJK 800m Freestyle U16")
                                $njk_cat = '';
                                if ( $is_njk && preg_match( '/\b(U\d+)\b/', $ev['term_name'], $m ) ) {
                                    $njk_cat = 'NJK ' . $m[1];
                                }
                            ?>
                                <div class="sta-program-event <?php echo $is_relay ? 'sta-program-relay' : ''; ?> <?php echo $is_njk ? 'sta-program-njk' : ''; ?>"<?php echo $time_label ? ' data-time="' . esc_attr( $time_label ) . '" data-date="' . esc_attr( $date ) . '"' : ''; ?>>
                                    <?php if ( $time_label ) : ?>
                                        <span class="sta-prog-live-dot" style="display:none;width:8px;height:8px;min-width:8px;border-radius:50%;background:#e74c3c;animation:staProgPulse 1.4s infinite;margin-right:2px;"></span>
                                    <?php endif; ?>
                                    <span class="sta-program-event-label">
                                        <?php if ( $term_url ) : ?>
                                            <a href="<?php echo esc_url( $term_url ); ?>">
                                                <?php echo esc_html( $label ); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo esc_html( $label ); ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ( $njk_cat ) : ?>
                                        <span class="sta-program-badge njk"><?php echo esc_html( $njk_cat ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $ev['fastest_heat'] && $ev['session'] === 'a_finals' ) : ?>
                                        <span class="sta-program-badge">fastest heat</span>
                                    <?php endif; ?>
                                    <?php if ( $ev['paralympic'] ) : ?>
                                        <span class="sta-program-badge para">P</span>
                                    <?php endif; ?>
                                    <?php if ( $time_label ) : ?>
                                        <span class="sta-program-badge time"><?php echo esc_html( $time_label ); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endforeach; ?>

        </div>

        <style>
        @keyframes staProgPulse { 0%,100%{opacity:1} 50%{opacity:.3} }
        </style>
        <script>
        function staProgramTab(btn, uid) {
            document.querySelectorAll('#' + uid + ' .sta-program-tab').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('#' + uid + ' .sta-program-day').forEach(function(d) { d.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById(btn.dataset.target).classList.add('active');
        }

        (function() {
            function staProgLiveDot() {
                var now = new Date();
                var today = now.toISOString().slice(0, 10);
                var nowMins = now.getHours() * 60 + now.getMinutes();

                // Hide all dots
                document.querySelectorAll('.sta-prog-live-dot').forEach(function(d) { d.style.display = 'none'; });

                // Find event with latest start time that has already started today
                var best = null, bestMins = -1;
                document.querySelectorAll('.sta-program-event[data-time][data-date="' + today + '"]').forEach(function(el) {
                    var parts = el.dataset.time.split(':');
                    var t = parseInt(parts[0]) * 60 + parseInt(parts[1]);
                    if (nowMins >= t && t > bestMins) { bestMins = t; best = el; }
                });

                // Hide dot if more than 90 minutes have passed since the last event started
                // (session is considered done — next session will become the new "best" when it starts)
                if (best && nowMins > bestMins + 90) best = null;

                if (best) {
                    var dot = best.querySelector('.sta-prog-live-dot');
                    if (dot) dot.style.display = 'inline-block';
                }
            }
            staProgLiveDot();
            setInterval(staProgLiveDot, 60000);
        })();
        </script>
        <?php

        return ob_get_clean();
    }
}
