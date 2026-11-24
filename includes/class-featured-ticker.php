<?php

class STA_Featured_Ticker {

    private const STROKE_ABBR = [
        'freestyle'         => 'Free',
        'butterfly'         => 'Fly',
        'breaststroke'      => 'Breast',
        'backstroke'        => 'Back',
        'individual medley' => 'IM',
    ];

    public static function register(): void {
        add_shortcode( 'sta_ticker',    [ __CLASS__, 'render' ] );
        add_shortcode( 'sta_featured',  [ __CLASS__, 'render_grid' ] );
    }

    private static function style_presets(): array {
        return [
            'swimming_trials' => [
                'wrap_bg'          => '#13315C',
                'wrap_radius'      => '999px',
                'outer_clip'       => '',
                'fade_color'       => '#13315C',
                'track_padding'    => '14px 0',
                'item_padding'     => '0 32px',
                'item_border'      => 'border-right:1px solid rgba(246,246,246,.15)',
                'separator'        => '',
                'name_color'       => '#F6F6F6',
                'name_size'        => '16px',
                'badge_bg'         => 'rgba(246,246,246,.15)',
                'badge_color'      => '#F6F6F6',
                'badge_radius'     => '999px',
                'btn_bg'           => '#D0021B',
                'btn_hover'        => '#b5001a',
                'btn_radius'       => '0 999px 999px 0',
                'btn_clip'         => '',
                'btn_padding'      => '0 26px',
                'left_accent_bg'   => '',
                'mobile_btn_bg'    => '#D0021B',
            ],
            'rotterdam' => [
                'wrap_bg'          => '#072b60',
                'wrap_radius'      => '0',
                'outer_clip'       => 'polygon(14px 0%, 100% 0%, calc(100% - 14px) 100%, 0% 100%)',
                'fade_color'       => '#072b60',
                'track_padding'    => '0 0',
                'item_padding'     => '12px 24px',
                'item_border'      => '',
                'separator'        => '<span aria-hidden="true" style="display:block;width:28px;flex-shrink:0;background:rgba(255,255,255,.22);clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);"></span>',
                'name_color'       => '#FFFFFF',
                'name_size'        => '15px',
                'badge_bg'         => 'rgba(255,255,255,.18)',
                'badge_color'      => '#FFFFFF',
                'badge_radius'     => '4px',
                'btn_bg'           => '#079cdd',
                'btn_hover'        => '#0589c2',
                'btn_radius'       => '0',
                'btn_clip'         => 'polygon(20px 0%, 100% 0%, 100% 100%, 0% 100%)',
                'btn_padding'      => '0 32px 0 44px',
                'left_accent_bg'   => '#fbd205',
                'mobile_btn_bg'    => '#079cdd',
            ],
        ];
    }

    public static function render( array $atts ): string {
        $atts = shortcode_atts( [
            'link' => '/atleten/',
        ], $atts );

        $athletes = get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [
                'key'   => 'sta_featured',
                'value' => '1',
            ] ],
        ] );

        if ( empty( $athletes ) ) {
            return '';
        }

        // Style preset
        $preset_key = get_option( 'sta_ticker_style', 'swimming_trials' );
        $presets    = self::style_presets();
        $s          = $presets[ $preset_key ] ?? $presets['swimming_trials'];

        // Bouw items op
        $items = [];
        foreach ( $athletes as $athlete ) {
            $terms = wp_get_post_terms( $athlete->ID, 'afstand', [ 'orderby' => 'name' ] );
            $abbrs = [];
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    preg_match( '/^(\d+)m\s+(.+)$/i', $term->name, $m );
                    $distance = $m[1] ?? '';
                    $stroke   = strtolower( $m[2] ?? $term->name );
                    $abbr     = self::STROKE_ABBR[ $stroke ] ?? ucfirst( $stroke );
                    $abbrs[]  = $distance ? $distance . ' ' . $abbr : $abbr;
                }
            }

            $items[] = [
                'name'   => $athlete->post_title,
                'events' => $abbrs,
                'url'    => get_permalink( $athlete->ID ),
            ];
        }

        ob_start();
        $uid = 'sta-ticker-' . wp_unique_id();

        // Render helper voor één set items
        $separator    = $s['separator'] ?? '';
        $badge_radius = $s['badge_radius'] ?? '999px';

        $render_items = function( $set_id, $aria_hidden ) use ( $items, $s, $uid, $separator, $badge_radius ) {
            echo '<div style="display:flex;align-items:stretch;" id="' . esc_attr( $uid . '-' . $set_id ) . '"' . ( $aria_hidden ? ' aria-hidden="true"' : '' ) . '>';
            $first = true;
            foreach ( $items as $item ) {
                if ( ! $first && $separator ) {
                    echo $separator;
                }
                $first = false;
                $border_style = $s['item_border'] ? esc_attr( $s['item_border'] ) . ';' : '';
                echo '<a href="' . esc_url( $item['url'] ) . '" style="'
                    . 'display:inline-flex;align-items:center;align-self:stretch;gap:10px;text-decoration:none;'
                    . 'padding:' . esc_attr( $s['item_padding'] ) . ';'
                    . $border_style
                    . '">';
                echo '<span style="color:' . esc_attr( $s['name_color'] ) . ';font-size:' . esc_attr( $s['name_size'] ) . ';font-weight:700;letter-spacing:.02em;">'
                    . esc_html( $item['name'] ) . '</span>';
                if ( $item['events'] ) {
                    echo '<span style="display:inline-flex;gap:5px;flex-wrap:nowrap;">';
                    foreach ( $item['events'] as $abbr ) {
                        echo '<span style="background:' . esc_attr( $s['badge_bg'] ) . ';color:' . esc_attr( $s['badge_color'] ) . ';'
                            . 'font-size:12px;font-weight:600;padding:6px 10px;border-radius:' . esc_attr( $badge_radius ) . ';letter-spacing:.04em;line-height:1;">'
                            . esc_html( $abbr ) . '</span>';
                    }
                    echo '</span>';
                }
                echo '</a>';
            }
            echo '</div>';
        };
        ?>
        <?php if ( $s['outer_clip'] ) : ?>
        <div style="clip-path:<?php echo esc_attr( $s['outer_clip'] ); ?>;line-height:0;display:block;">
        <?php endif; ?>
        <div id="<?php echo $uid; ?>" class="sta-ticker-wrap" style="
            background:<?php echo esc_attr( $s['wrap_bg'] ); ?>;
            border-radius:<?php echo esc_attr( $s['wrap_radius'] ); ?>;
            display:flex;
            align-items:stretch;
            overflow:hidden;
            font-family:inherit;
        ">
            <?php if ( $s['left_accent_bg'] ) : ?>
            <div style="
                width:28px;
                flex-shrink:0;
                background:<?php echo esc_attr( $s['left_accent_bg'] ); ?>;
                clip-path:polygon(0 0,100% 0,calc(100% - 14px) 100%,0 100%);
            " aria-hidden="true"></div>
            <?php endif; ?>

            <!-- Scrollende baan -->
            <div class="sta-ticker-track-wrap" style="flex:1;overflow:hidden;position:relative;min-width:0;">
                <div style="position:absolute;left:0;top:0;bottom:0;width:48px;background:linear-gradient(to right,<?php echo esc_attr( $s['fade_color'] ); ?>,transparent);z-index:2;pointer-events:none;"></div>
                <div style="position:absolute;right:0;top:0;bottom:0;width:48px;background:linear-gradient(to left,<?php echo esc_attr( $s['fade_color'] ); ?>,transparent);z-index:2;pointer-events:none;"></div>

                <div style="display:flex;align-items:stretch;white-space:nowrap;padding:<?php echo esc_attr( $s['track_padding'] ); ?>;will-change:transform;" id="<?php echo $uid; ?>-track">
                    <?php $render_items( 'set-a', false ); ?>
                    <?php $render_items( 'set-b', true ); ?>
                </div>
            </div>

            <!-- Knop alle zwemmers -->
            <a href="<?php echo esc_url( $atts['link'] ); ?>" class="sta-ticker-btn" style="
                display:flex;
                align-items:center;
                background:<?php echo esc_attr( $s['btn_bg'] ); ?>;
                color:#FFFFFF;
                font-size:13px;
                font-weight:800;
                text-decoration:none;
                padding:<?php echo esc_attr( $s['btn_padding'] ); ?>;
                white-space:nowrap;
                letter-spacing:.04em;
                text-transform:uppercase;
                flex-shrink:0;
                border-radius:<?php echo esc_attr( $s['btn_radius'] ); ?>;
                <?php if ( $s['btn_clip'] ) : ?>clip-path:<?php echo esc_attr( $s['btn_clip'] ); ?>;<?php endif; ?>
                transition:background .15s;
            " onmouseover="this.style.background='<?php echo esc_attr( $s['btn_hover'] ); ?>'"
               onmouseout="this.style.background='<?php echo esc_attr( $s['btn_bg'] ); ?>'">
                All athletes →
            </a>
        </div>
        <?php if ( $s['outer_clip'] ) : ?>
        </div>
        <?php endif; ?>

        <style>
        @media (max-width: 640px) {
            #<?php echo $uid; ?> .sta-ticker-btn { display: none !important; }
            #<?php echo $uid; ?>-mobile-btn      { display: flex !important; }
        }
        </style>
        <script>
        (function() {
            var track = document.getElementById('<?php echo $uid; ?>-track');
            var setA  = document.getElementById('<?php echo $uid; ?>-set-a');
            if (!track || !setA) return;
            var pos = 0, setWidth = 0, prevTs = null;
            function tick(ts) {
                if (prevTs !== null) {
                    var speed = window.innerWidth <= 640 ? 150 : 80;
                    pos += speed * (ts - prevTs) / 1000;
                    if (setWidth > 0 && pos >= setWidth) pos -= setWidth;
                    track.style.transform = 'translateX(-' + pos.toFixed(2) + 'px)';
                }
                prevTs = ts;
                requestAnimationFrame(tick);
            }
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    setWidth = setA.offsetWidth;
                    requestAnimationFrame(tick);
                });
            });
        })();
        </script>

        <a id="<?php echo $uid; ?>-mobile-btn" href="<?php echo esc_url( $atts['link'] ); ?>" style="
            display:none;
            justify-content:center;
            margin-top:10px;
            background:<?php echo esc_attr( $s['mobile_btn_bg'] ); ?>;
            color:#FFFFFF;
            font-size:13px;
            font-weight:800;
            text-decoration:none;
            padding:12px 28px;
            border-radius:999px;
            letter-spacing:.04em;
            text-transform:uppercase;
        ">All athletes →</a>

        <?php
        return ob_get_clean();
    }

    public static function render_grid( array $atts ): string {
        $atts = shortcode_atts( [
            'link'    => '/atleten/',
            'columns' => '5',
            'country' => '',
        ], $atts );

        $meta_query = [ [
            'key'   => 'sta_featured',
            'value' => '1',
        ] ];

        if ( ! empty( $atts['country'] ) ) {
            $meta_query[] = [
                'key'   => 'sta_country',
                'value' => strtoupper( $atts['country'] ),
            ];
        }

        $athletes = get_posts( [
            'post_type'      => 'athlete',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => $meta_query,
        ] );

        if ( empty( $athletes ) ) return '';

        $cols = max( 2, intval( $atts['columns'] ) );

        ob_start(); ?>
        <style>
        .sta-featured-grid {
            display: grid;
            grid-template-columns: repeat(<?php echo $cols; ?>, 1fr);
            gap: 24px;
        }
        @media (max-width: 768px) {
            .sta-featured-grid { grid-template-columns: repeat(3, 1fr); gap: 16px; }
        }
        @media (max-width: 480px) {
            .sta-featured-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        }
        .sta-featured-card {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-align: center;
        }
        .sta-featured-photo {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 50%;
            overflow: hidden;
            transition: box-shadow .2s, transform .2s;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sta-featured-card:hover .sta-featured-photo {
            box-shadow: 0 6px 24px rgba(19,49,92,.25);
            transform: translateY(-3px);
        }
        .sta-featured-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .sta-featured-meta {
            display: block;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            color: #13315C;
            line-height: 1.4;
            word-break: break-word;
        }
        .sta-featured-flag {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 4px;
            display: inline-block;
        }
        </style>

        <div class="sta-featured-grid">
            <?php foreach ( $athletes as $athlete ) :
                $country_code = get_post_meta( $athlete->ID, 'sta_country', true );
            ?>
                <a href="<?php echo get_permalink( $athlete->ID ); ?>" class="sta-featured-card">

                    <?php if ( STA_Modules::is_active( 'photos' ) ) : ?>
                    <div class="sta-featured-photo">
                        <?php if ( has_post_thumbnail( $athlete->ID ) ) : ?>
                            <?php echo get_the_post_thumbnail( $athlete->ID, 'medium', [
                                'style' => 'width:100%;height:100%;object-fit:cover;display:block;' . sta_focus_style( $athlete->ID ),
                            ] ); ?>
                        <?php else : ?>
                            <span style="font-size:36px;opacity:.3;">🏊</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="sta-featured-meta">
                        <?php
                        $full_name  = $athlete->post_title;
                        $name_parts = explode( ' ', $full_name, 2 );
                        $first_name = $name_parts[0];
                        $rest_name  = $name_parts[1] ?? '';
                        ?>
                        <span style="white-space:nowrap;">
                            <?php if ( $country_code ) : ?>
                                <img class="sta-featured-flag"
                                     src="https://hatscripts.github.io/circle-flags/flags/<?php echo esc_attr( strtolower( $country_code ) ); ?>.svg"
                                     alt="">
                            <?php endif; ?>
                            <?php echo esc_html( $first_name ); ?>
                        </span><?php if ( $rest_name ) : ?> <?php echo esc_html( $rest_name ); ?><?php endif; ?>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>

        <?php return ob_get_clean();
    }
}
