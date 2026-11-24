<?php
get_header();

$_layout = array_merge([
    'primary_color'     => '#13315c',
    'header_image_id'   => 0,
    'header_height'     => 220,
    'name_badge_bg'     => '#0d2d63',
    'name_badge_radius' => 50,
    'club_badge_bg'     => '#13315C',
    'club_badge_radius' => 16,
    'dist_badge_bg'     => '#0d2d63',
    'dist_badge_radius' => 50,
], get_option('sta_layout', []));
$_badge_bg          = esc_attr($_layout['name_badge_bg']);
$_badge_radius      = (int)$_layout['name_badge_radius'];
$_club_badge_bg     = esc_attr($_layout['club_badge_bg']);
$_club_badge_radius = (int)$_layout['club_badge_radius'];
$_dist_badge_bg     = esc_attr($_layout['dist_badge_bg']);
$_dist_badge_radius = (int)$_layout['dist_badge_radius'];
$_header_url   = $_layout['header_image_id'] ? wp_get_attachment_image_url($_layout['header_image_id'], 'full') : '';
$_header_h     = (int)$_layout['header_height'];

$country_code = get_post_meta( get_the_ID(), 'sta_country', true );
$athlete_club = get_post_meta( get_the_ID(), 'sta_club', true );
$club_logos   = get_option( 'sta_club_logos', [] );
$athlete_club_logo = $athlete_club ? ( $club_logos[ $athlete_club ] ?? '' ) : '';

$countries = array(
    'NL' => 'The Netherlands',
    'BE' => 'Belgium',
    'DE' => 'Germany',
    'FR' => 'France',
    'GB' => 'Great Britain',
    'US' => 'United States',
    'AU' => 'Australia',
    'CA' => 'Canada',
    'IT' => 'Italy',
    'ES' => 'Spain',
    'SE' => 'Sweden',
    'NO' => 'Norway',
    'DK' => 'Denmark',
    'CH' => 'Switzerland',
    'PL' => 'Poland',
    'UA' => 'Ukraine',
    'CZ' => 'Czech Republic',
    'HU' => 'Hungary',
    'RO' => 'Romania',
    'FI' => 'Finland',
    'IE' => 'Ireland',
    'PT' => 'Portugal',
    'GR' => 'Greece',
    'TR' => 'Turkey',
    'JP' => 'Japan',
    'CN' => 'China',
    'KR' => 'South Korea',
    'NZ' => 'New Zealand',
    'BR' => 'Brazil',
    'ZA' => 'South Africa',
);
?>

<div class="athlete-header-image" style="
    width:100%;
    height:<?php echo $_header_h; ?>px;
    <?php if ($_header_url): ?>background-image:url('<?php echo esc_url($_header_url); ?>');background-size:cover;background-position:center;<?php else: ?>background-color:<?php echo esc_attr($_layout['primary_color']); ?>;<?php endif; ?>
"></div>

<main id="primary" class="site-main">

    <div class="container athlete-single" style="
        max-width:1000px;
        margin:0 auto;
        padding:50px 20px 80px;
    ">

        <div style="margin-bottom:32px;">
            <a href="/atleten/" style="
                display:inline-flex;
                align-items:center;
                gap:6px;
                color:#13315C;
                font-size:14px;
                font-weight:600;
                text-decoration:none;
                opacity:.8;
            " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.8'">
                ← All athletes
            </a>
        </div>

        <?php while ( have_posts() ) : the_post(); ?>

            <article <?php post_class(); ?>>

                <header class="athlete-entry-header" style="
                    text-align:center;
                    margin-bottom:40px;
                ">

                    <div style="
            margin-top:64px;
                        display:flex;
                        justify-content:center;
                        align-items:center;
                        gap:25px;
                        flex-wrap:wrap;
                        margin-bottom:35px;
                    ">

                        <div style="
            margin-top:64px;
                            background:<?php echo $_badge_bg; ?>;
                            color:#fff;
                            padding:14px 22px 14px 30px;
                            border-radius:<?php echo $_badge_radius; ?>px;
                            font-size:34px;
                            font-weight:700;
                            text-transform:uppercase;
                            line-height:1;
                            display:flex;
                            align-items:center;
                            gap:23px;
                        ">

                            <span><?php the_title(); ?></span>

                            <?php if ( $country_code && isset( $countries[$country_code] ) ) : ?>

                                <img
                                    src="https://hatscripts.github.io/circle-flags/flags/<?php echo esc_attr( strtolower( $country_code ) ); ?>.svg"
                                    alt="<?php echo esc_attr( $countries[$country_code] ); ?>"
                                    style="
                                        width:46px;
                                        height:46px;
                                        border-radius:50%;
                                        display:block;
                                    "
                                >

                            <?php endif; ?>

                        </div>

                        <?php if ( $country_code && isset( $countries[$country_code] ) ) : ?>

                            <div style="
            margin-top:64px;
                                font-size:44px;
                                font-weight:700;
                                line-height:1.1;
                            ">
                                <?php echo esc_html( $countries[$country_code] ); ?>
                            </div>

                        <?php endif; ?>

                    </div>

                    <?php if ( $athlete_club ) :
                        $club_term     = get_terms( [ 'taxonomy' => 'sta_club', 'name' => $athlete_club, 'hide_empty' => false, 'number' => 1 ] );
                        $club_term_url = ( ! is_wp_error( $club_term ) && ! empty( $club_term ) ) ? get_term_link( $club_term[0] ) : '';
                    ?>
                    <div style="margin-bottom:20px;">
                        <?php if ( $athlete_club_logo ) : ?>
                        <?php if ( $club_term_url ) : ?><a href="<?php echo esc_url( $club_term_url ); ?>" style="text-decoration:none;"><?php endif; ?>
                        <div style="display:flex;flex-direction:column;align-items:center;background:<?php echo $_club_badge_bg; ?>;border-radius:<?php echo $_club_badge_radius; ?>px;padding:24px 20px;gap:16px;width:100%;box-sizing:border-box;">
                            <img src="<?php echo esc_url( $athlete_club_logo ); ?>" style="max-height:104px;width:auto;" onerror="this.style.display='none'">
                            <span style="font-size:28px;font-weight:600;color:#fff;"><?php echo esc_html( $athlete_club ); ?></span>
                        </div>
                        <?php if ( $club_term_url ) : ?></a><?php endif; ?>
                        <?php else : ?>
                        <?php if ( $club_term_url ) : ?>
                        <a href="<?php echo esc_url( $club_term_url ); ?>" style="font-size:18px;font-weight:600;color:#13315C;text-decoration:none;"><?php echo esc_html( $athlete_club ); ?></a>
                        <?php else : ?>
                        <span style="font-size:18px;font-weight:600;color:#13315C;"><?php echo esc_html( $athlete_club ); ?></span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ( STA_Modules::is_active( 'photos' ) && has_post_thumbnail() ) : ?>

                        <div class="athlete-portrait" style="
                            margin:0 auto 35px;
                            max-width:380px;
                        ">

                            <?php
                            the_post_thumbnail(
                                'large',
                                array(
                                    'style' => '
                                        width:100%;
                                        height:auto;
                                        border-radius:14px;
                                        display:block;
                                    '
                                )
                            );
                            ?>

                        </div>

                    <?php endif; ?>

                </header>

                <div class="entry-content athlete-content" style="
                    max-width:700px;
                    margin:0 auto;
                    text-align:center;
                    font-size:20px;
                    line-height:1.8;
                ">

                    <?php the_content(); ?>

                </div>

                <?php
                $events = wp_get_post_terms( get_the_ID(), 'afstand', [ 'orderby' => 'name', 'order' => 'ASC' ] );
                $gender = get_post_meta( get_the_ID(), 'sta_gender', true );
                if ( ! is_wp_error( $events ) && ! empty( $events ) ) :
                    // Sorteer op vroegste programma-datum voor dit geslacht
                    if ( class_exists( 'STA_Program' ) ) {
                        usort( $events, function( $a, $b ) use ( $gender ) {
                            $ea = STA_Program::get_entries_for_term( $a->name, $gender );
                            $eb = STA_Program::get_entries_for_term( $b->name, $gender );
                            $da = ! empty( $ea ) ? $ea[0]['date'] : '9999';
                            $db = ! empty( $eb ) ? $eb[0]['date'] : '9999';
                            return strcmp( $da, $db );
                        } );
                    }
                ?>

                    <div style="max-width:700px;margin:48px auto 0;text-align:center;">
                        <h3 style="
                            font-size:14px;
                            font-weight:700;
                            text-transform:uppercase;
                            letter-spacing:.1em;
                            color:#0d2d63;
                            margin-bottom:16px;
                        ">Events</h3>
                        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px;">
                            <?php foreach ( $events as $event ) :
                                // Zoek programma-entries voor deze afstand + geslacht
                                $prog_entries = class_exists( 'STA_Program' )
                                    ? STA_Program::get_entries_for_term( $event->name, $gender )
                                    : [];

                                // Groepeer per dag: pak de A finale (of heat bij 800/1500)
                                $by_date = [];
                                foreach ( $prog_entries as $pe ) {
                                    $by_date[ $pe['date'] ][ $pe['session'] ] = $pe;
                                }
                            ?>
                                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                    <a href="<?php echo esc_url( get_term_link( $event ) ); ?>" style="
                                        background:<?php echo $_dist_badge_bg; ?>;
                                        color:#fff;
                                        padding:6px 18px;
                                        border-radius:<?php echo $_dist_badge_radius; ?>px;
                                        font-size:15px;
                                        font-weight:600;
                                        text-decoration:none;
                                    ">
                                        <?php echo esc_html( $event->name ); ?>
                                    </a>
                                    <?php
                                    // Unieke datums (een afstand kan meerdere sessies hebben op dezelfde dag)
                                    $dates = array_unique( array_keys( $by_date ) );
                                    foreach ( $dates as $date ) :
                                        $day_short = date( 'D j M', strtotime( $date ) );
                                    ?>
                                        <span style="font-size:12px;color:#666;">
                                            <?php echo esc_html( $day_short ); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php
                // ── Startlijst for this athlete ────────────────────────────────
                // Normalize: strip diacritics + lowercase so "Émile Fouzaï" matches "EMILE FOUZAI"
                $sta_norm = function( string $s ): string {
                    $s = mb_strtolower( $s, 'UTF-8' );
                    $s = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s );
                    return trim( $s );
                };
                $athlete_name_lower = $sta_norm( get_the_title() );
                $athlete_club_lower = $sta_norm( get_post_meta( get_the_ID(), 'sta_club', true ) );
                $meet_id_sl         = get_option( 'sta_swimrankings_meet_id', '' );

                if ( $meet_id_sl && class_exists( 'STA_Program' ) && ! is_wp_error( $events ) && ! empty( $events ) ) :
                    $start_rows = [];

                    foreach ( $events as $event ) {
                        $is_relay_sl  = (bool) preg_match( '/\d+\s*x/i', $event->name );
                        $prog_entries = STA_Program::get_entries_for_term( $event->name, $gender );

                        foreach ( $prog_entries as $pe ) {
                            $eid = $pe['event_id'] ?? '';
                            if ( ! $eid ) continue;

                            $cache_key  = 'sta_heats_startlijst_' . $eid;
                            $heats_data = get_transient( $cache_key );
                            if ( $heats_data === false ) {
                                $url      = 'https://live.swimrankings.net/meets/' . $meet_id_sl . '/heats/' . $eid . '.json';
                                $response = wp_remote_get( $url, [ 'timeout' => 8, 'sslverify' => false ] );
                                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                                    $heats_data = json_decode( wp_remote_retrieve_body( $response ), true );
                                    set_transient( $cache_key, $heats_data, HOUR_IN_SECONDS );
                                }
                            }
                            if ( empty( $heats_data['heats'] ) ) continue;

                            foreach ( $heats_data['heats'] as $heat ) {
                                $heat_num = $heat['heatinfo']['number'] ?? $heat['id'] ?? 0;
                                foreach ( $heat['entries'] ?? [] as $entry ) {
                                    if ( empty( $entry['nametext'] ) ) continue;
                                    $lane = (int) ( $entry['lane'] ?? -1 );
                                    if ( $lane < 0 ) continue;
                                    $match = $is_relay_sl
                                        ? ( $athlete_club_lower && $sta_norm( $entry['nametext'] ) === $athlete_club_lower )
                                        : ( $sta_norm( $entry['nametext'] ) === $athlete_name_lower );
                                    if ( ! $match ) continue;

                                    $start_rows[] = [
                                        'term_name'     => $event->name,
                                        'term_url'      => get_term_link( $event ),
                                        'session_label' => $pe['session_label'],
                                        'date'          => $pe['date'],
                                        'start_time'    => $pe['start_time'] ?: ( $pe['approx_time'] ?? '' ),
                                        'heat'          => $heat_num,
                                        'lane'          => $lane,
                                        'entrytime'     => $entry['entrytime'] ?? '',
                                        'sort'          => $pe['date'] . '|' . $pe['session'] . '|' . sprintf( '%04d', $heat_num ),
                                    ];
                                }
                            }
                        }
                    }

                    if ( ! empty( $start_rows ) ) :
                        usort( $start_rows, fn( $a, $b ) => strcmp( $a['sort'], $b['sort'] ) );
                ?>
                <div style="max-width:500px;margin:48px auto 0;text-align:center;">
                    <h3 style="font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#0d2d63;margin-bottom:16px;">Start List</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:14px;text-align:left;">
                    <?php foreach ( $start_rows as $r ) : ?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:10px 8px;width:36px;text-align:center;font-weight:700;color:#13315C;font-size:15px;">
                            <?php echo esc_html( $r['lane'] ); ?>
                            <div style="font-size:10px;font-weight:400;color:#aaa;">lane</div>
                        </td>
                        <td style="padding:10px 8px;">
                            <a href="<?php echo esc_url( $r['term_url'] ); ?>" style="font-weight:600;color:#0d2d63;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                <?php echo esc_html( $r['term_name'] ); ?>
                            </a>
                            <div style="font-size:11px;color:#999;margin-top:1px;">
                                <?php echo esc_html( $r['session_label'] . ' · ' . date( 'j M', strtotime( $r['date'] ) ) ); ?>
                                <?php if ( $r['start_time'] ) : ?>· <?php echo esc_html( $r['start_time'] ); ?><?php endif; ?>
                            </div>
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-family:monospace;font-weight:600;color:#555;white-space:nowrap;">
                            <?php echo esc_html( $r['entrytime'] ?: '–' ); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; endif; ?>

                <?php
                // ── Results for this athlete ───────────────────────────────────
                $event_results      = get_option( 'sta_event_results', [] );

                if ( ! empty( $event_results ) && ! is_wp_error( $events ) && ! empty( $events ) ) :
                    $result_rows = [];

                    foreach ( $events as $event ) {
                        $is_relay     = (bool) preg_match( '/\d+\s*x/i', $event->name );
                        $prog_entries = class_exists( 'STA_Program' )
                            ? STA_Program::get_entries_for_term( $event->name, $gender )
                            : [];

                        foreach ( $prog_entries as $pe ) {
                            if ( ! in_array( $pe['session_slug'], [ 'a_finals', 'bc_finals', 'finals' ], true ) ) continue;
                            $eid = $pe['event_id'] ?? '';
                            if ( ! $eid || empty( $event_results[ $eid ] ) ) continue;

                            foreach ( $event_results[ $eid ] as $ag_data ) {
                                foreach ( $ag_data['entries'] as $entry ) {
                                    if ( $is_relay ) {
                                        // Relay: match on club name
                                        if ( ! $athlete_club_lower ) continue;
                                        if ( $sta_norm( $entry['nametext'] ) !== $athlete_club_lower ) continue;
                                    } else {
                                        // Individual: match on athlete name
                                        if ( $sta_norm( $entry['nametext'] ) !== $athlete_name_lower ) continue;
                                    }
                                    // Skip entries that aren't properly finalized yet:
                                    // - No swimtime (DNS stub) before the event starts
                                    // - Has swimtime but no place (heats time leaking into finals slot, or results not yet ranked)
                                    $ev_ts = strtotime( $pe['date'] . ' ' . ( $pe['start_time'] ?: '23:59' ) );
                                    if ( empty( $entry['swimtime'] ) && time() < $ev_ts ) continue;
                                    if ( ! empty( $entry['swimtime'] ) && (int)$entry['place'] === 0 && time() < $ev_ts ) continue;
                                    $key = $event->name . '|' . $eid;
                                    if ( ! isset( $result_rows[ $key ] ) ) {
                                        $result_rows[ $key ] = [
                                            'term_name'     => $event->name,
                                            'term_url'      => get_term_link( $event ),
                                            'place'         => $entry['place'],
                                            'swimtime'      => $entry['swimtime'],
                                            'qualcode'      => $entry['qualcode'],
                                            'diff'          => $entry['diff'],
                                            'session_label' => $pe['session_label'],
                                            'date'          => $pe['date'],
                                            'medal'         => $entry['medal'],
                                            'relay'         => $is_relay,
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    if ( ! empty( $result_rows ) ) :
                        // Sort by date
                        usort( $result_rows, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
                ?>
                <div style="max-width:500px;margin:48px auto 0;text-align:center;">
                    <h3 style="
                        font-size:14px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.1em;color:#0d2d63;margin-bottom:16px;
                    ">Results</h3>

                    <table style="width:100%;border-collapse:collapse;font-size:14px;text-align:left;">
                    <?php foreach ( $result_rows as $r ) :
                        $place_str = '';
                        if ( $r['medal'] === 1 )     $place_str = '🥇';
                        elseif ( $r['medal'] === 2 ) $place_str = '🥈';
                        elseif ( $r['medal'] === 3 ) $place_str = '🥉';
                        elseif ( $r['place'] > 0 )   $place_str = $r['place'];
                        else                         $place_str = 'DNS/DNF';

                        $row_bg = '';
                        if ( $r['medal'] === 1 )     $row_bg = 'background:#FFF9E6;';
                        elseif ( $r['medal'] === 2 ) $row_bg = 'background:#F5F5F5;';
                        elseif ( $r['medal'] === 3 ) $row_bg = 'background:#FFF3E6;';
                    ?>
                    <tr style="border-top:1px solid #e5e7eb;<?php echo $row_bg; ?>">
                        <td style="padding:10px 12px;font-weight:700;font-size:16px;text-align:center;width:44px;">
                            <?php echo $place_str; ?>
                        </td>
                        <td style="padding:10px 8px;">
                            <a href="<?php echo esc_url( $r['term_url'] ); ?>"
                               style="font-weight:600;color:#0d2d63;text-decoration:none;"
                               onmouseover="this.style.textDecoration='underline'"
                               onmouseout="this.style.textDecoration='none'">
                                <?php echo esc_html( $r['term_name'] ); ?>
                            </a>
                            <div style="font-size:11px;color:#999;margin-top:1px;">
                                <?php echo esc_html( $r['session_label'] . ' · ' . date( 'j M', strtotime( $r['date'] ) ) ); ?>
                            </div>
                        </td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;font-family:monospace;color:#13315C;white-space:nowrap;">
                            <?php echo esc_html( $r['swimtime'] ?: '–' ); ?>
                            <?php if ( $r['diff'] ) : ?>
                            <span style="font-size:11px;font-weight:400;color:#999;font-family:inherit;"> <?php echo esc_html( $r['diff'] ); ?></span>
                            <?php endif; ?>
                            <?php if ( $r['qualcode'] ) : ?>
                            <span style="display:inline-block;font-size:10px;font-family:inherit;background:#dff0d8;color:#3c763d;padding:1px 5px;border-radius:50px;font-weight:700;margin-left:4px;">
                                <?php echo esc_html( $r['qualcode'] ); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; endif; ?>

                <?php endif; ?>

            </article>

        <?php endwhile; ?>

        <?php
        $hs_id   = get_post_meta( get_the_ID(), 'sta_heroshot_id',   true );
        $hs_hash = get_post_meta( get_the_ID(), 'sta_heroshot_hash', true );
        if ( $hs_id && $hs_hash && STA_Modules::is_active( 'heroshot' ) ) :
            $hs_flag = STA_Heroshot_Paths::dir( 'videos' ) . $hs_hash . '.public';
            if ( file_exists( $hs_flag ) ) :
                $hs_url = STA_Heroshot_Paths::url( 'videos' ) . rawurlencode( $hs_hash ) . '.mp4';
        ?>
        <div style="margin-top:56px;text-align:center;">
            <h3 style="font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#0d2d63;margin-bottom:16px;">Heroshot video</h3>
            <div style="width:100%;max-width:320px;margin:0 auto;aspect-ratio:9/16;">
                <video controls playsinline style="width:100%;height:100%;border-radius:14px;display:block;">
                    <source src="<?php echo esc_url( $hs_url ); ?>" type="video/mp4">
                </video>
            </div>
        </div>
        <?php endif; endif; ?>


        <?php
        $sc_settings  = get_option( 'sta_sharecard', [] );
        if ( ! empty( $sc_settings['bg_id'] ) && ! empty( $sc_settings['enabled'] ) ) :
            $sc_post_id   = get_the_ID();
            $sc_card_url  = rest_url( 'sta/v1/share-card/' . $sc_post_id );
            $sc_page_url  = get_permalink( $sc_post_id );
        ?>
        <!-- Share card preview modal -->
        <div id="sc-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);
             align-items:center;justify-content:center;flex-direction:column;padding:20px;box-sizing:border-box;">
            <img id="sc-modal-img" src="" alt="Profile card"
                 style="max-width:min(380px,90vw);max-height:70vh;object-fit:contain;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.6);">
            <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;justify-content:center;">
                <button id="sc-modal-share" style="display:none;background:#13315c;color:#fff;border:none;cursor:pointer;
                        padding:13px 26px;border-radius:50px;font-size:15px;font-weight:700;font-family:inherit;">
                    Share
                </button>
                <a id="sc-modal-download" href="#" download="profiel-est2026.png"
                   onclick="staTrack('download')"
                   style="background:#13315c;color:#fff;text-decoration:none;
                          padding:13px 26px;border-radius:50px;font-size:15px;font-weight:700;font-family:inherit;">
                    Download
                </a>
                <button id="sc-modal-close" onclick="document.getElementById('sc-modal').style.display='none'"
                        style="background:rgba(255,255,255,.15);color:#fff;border:none;cursor:pointer;
                               padding:13px 26px;border-radius:50px;font-size:15px;font-weight:700;font-family:inherit;">
                    Close
                </button>
            </div>
        </div>

        <div style="margin-top:40px;text-align:center;">
            <button id="btn-share-card"
                    onclick="staShareCard()"
                    style="display:inline-flex;align-items:center;gap:9px;
                           background:#13315c;color:#fff;border:none;cursor:pointer;
                           padding:14px 28px;border-radius:50px;font-size:15px;
                           font-weight:700;font-family:inherit;transition:opacity .15s;"
                    onmouseover="this.style.opacity='.85'"
                    onmouseout="this.style.opacity='1'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                Share profile card
            </button>
            <p id="share-card-status" style="font-size:13px;color:#999;margin-top:8px;display:none;"></p>
        </div>
        <script>
        (function() {
            var cardUrl  = '<?php echo esc_js( $sc_card_url ); ?>';
            var pageUrl  = '<?php echo esc_js( $sc_page_url ); ?>';
            var caption  = 'Check out my profile at the European Swimming Trials 2026! ' + pageUrl + ' #EST2026 #SwimmingTrials #Eindhoven';
            var blob     = null;
            var ajaxUrl  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var postId   = <?php echo intval( $sc_post_id ); ?>;

            window.staTrack = function(action) {
                var fd = new FormData();
                fd.append('action', 'sta_sharecard_track');
                fd.append('track_action', action);
                fd.append('post_id', postId);
                fetch(ajaxUrl, { method: 'POST', body: fd }).catch(function(){});
            }

            window.staShareCard = async function() {
                var btn    = document.getElementById('btn-share-card');
                var status = document.getElementById('share-card-status');
                btn.disabled = true;
                btn.style.opacity = '.6';
                status.textContent = 'Generating...';
                status.style.display = 'block';
                try {
                    var resp = await fetch(cardUrl);
                    if (!resp.ok) throw new Error('failed');
                    blob = await resp.blob();

                    // Show preview modal
                    var objUrl = URL.createObjectURL(blob);
                    document.getElementById('sc-modal-img').src = objUrl;
                    document.getElementById('sc-modal-download').href = objUrl;

                    var shareBtn = document.getElementById('sc-modal-share');
                    var file = new File([blob], 'profiel-est2026.png', { type: 'image/png' });
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        shareBtn.style.display = 'inline-block';
                        shareBtn.onclick = async function() {
                            try {
                                await navigator.share({ files: [file], title: 'European Swimming Trials 2026', text: caption });
                                staTrack('share');
                                document.getElementById('sc-modal').style.display = 'none';
                            } catch(e) {
                                if (e.name !== 'AbortError') alert('Could not share. Try downloading instead.');
                            }
                        };
                    } else {
                        shareBtn.style.display = 'none';
                    }

                    document.getElementById('sc-modal').style.display = 'flex';
                    status.style.display = 'none';
                } catch(e) {
                    status.textContent = 'Could not generate card. Try again.';
                } finally {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            };
        })();
        </script>
        <?php endif; ?>

        <?php
        if ( get_option( 'sta_name_recording_enabled', true ) ) :
            $sta_audio_rel = get_post_meta( get_the_ID(), 'sta_name_audio', true );
            if ( $sta_audio_rel ) :
                $sta_audio_url = home_url( '/' . ltrim( $sta_audio_rel, '/' ) );
        ?>
        <div style="margin-top:32px;text-align:center;">
            <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#999;margin-bottom:10px;">Name pronunciation</p>
            <audio src="<?php echo esc_url( $sta_audio_url ); ?>" controls
                   style="max-width:260px;width:100%;"></audio>
        </div>
        <?php endif; endif; ?>

                <?php
        $tournament_tag    = get_option( 'sta_tournament_tag', '' );
        $tournament_photos = ( $tournament_tag && STA_Modules::is_active( 'photos' ) ) ? get_post_meta( get_the_ID(), 'sta_tournament_photos', true ) : [];
        if ( ! empty( $tournament_photos ) ) :
            $photographer = get_option( 'sta_flickr_photographer', '' );
            $athlete_name    = get_the_title();
            $athlete_tag     = mb_strtolower( str_replace( ' ', '', $athlete_name ), 'UTF-8' );
            $athlete_tag     = strtr( $athlete_tag, ['ä'=>'a','á'=>'a','à'=>'a','ö'=>'o','ó'=>'o','ò'=>'o','ü'=>'u','ú'=>'u','ë'=>'e','é'=>'e','è'=>'e','ï'=>'i','í'=>'i','ñ'=>'n','ß'=>'ss','ç'=>'c','ý'=>'y'] );
            $flickr_url      = $photographer
                ? 'https://www.flickr.com/search/?text=' . urlencode( $athlete_name ) . '&sort=date-posted-desc'
                : '';
        ?>
        <div style="margin-top:40px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:14px;text-align:center;">
                Tournament photos
            </h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;">
                <?php foreach ( $tournament_photos as $photo ) : ?>
                <a href="<?php echo esc_url( $photo['page'] ); ?>" target="_blank" rel="noopener"
                   style="display:block;aspect-ratio:1;overflow:hidden;border-radius:8px;background:#e5e7eb;">
                    <img src="<?php echo esc_url( $photo['thumb'] ); ?>"
                         alt="<?php echo esc_attr( $photo['title'] ); ?>"
                         loading="lazy"
                         style="width:100%;height:100%;object-fit:cover;transition:transform .2s;"
                         onmouseover="this.style.transform='scale(1.05)'"
                         onmouseout="this.style.transform=''">
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ( $flickr_url ) : ?>
            <p style="text-align:center;margin-top:14px;">
                <a href="<?php echo esc_url( $flickr_url ); ?>" target="_blank" rel="noopener"
                   style="font-size:13px;color:#0073aa;text-decoration:none;">
                    Bekijk alle foto's op Flickr →
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <div style="
            margin-top:64px;
            padding-top:32px;
            border-top:1px solid #e5e7eb;
            text-align:center;
        ">
            <?php
            $sta_layout       = get_option('sta_layout', []);
            $sta_contact_url  = $sta_layout['contact_url'] ?? '';
            $sta_contact_text = $sta_layout['contact_text'] ?? '';
            $sta_contact_default = ( STA_Modules::is_active( 'photos' ) ? 'The photo and biography' : 'The biography' ) . " on this page were generated automatically. Spotted a mistake or have something to add?\nFeel free to send us a message.";
            $sta_contact_display = $sta_contact_text ?: $sta_contact_default;
            if ( $sta_contact_url || $sta_contact_text ) : ?>
            <p style="
                font-size:13px;
                color:#999;
                line-height:1.6;
                margin-bottom:14px;
            ">
                <?php echo nl2br( esc_html( $sta_contact_display ) ); ?>
            </p>
            <?php endif; ?>
            <?php if ( $sta_contact_url ) : ?>
            <div class="wp-block-button">
                <a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background has-link-color wp-element-button"
                   href="<?php echo esc_url( $sta_contact_url ); ?>"
                   style="border-style:none;border-width:0px;border-top-left-radius:20px;border-top-right-radius:20px;border-bottom-left-radius:20px;border-bottom-right-radius:20px">
                    Contact us
                </a>
            </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<?php
get_footer();
