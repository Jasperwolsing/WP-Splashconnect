<?php
/**
 * Template Name: Club overzicht
 */
get_header();

$s = array_merge( [
    'primary_color'   => '#13315c',
    'header_image_id' => 0,
    'header_height'   => 220,
    'card_radius'     => 12,
    'card_bg'         => '#ffffff',
    'label_radius'    => 50,
], get_option( 'sta_layout', [] ) );

$color       = esc_attr( $s['primary_color'] );
$card_bg     = esc_attr( $s['card_bg'] );
$card_radius = (int) $s['card_radius'];
$lbl_radius  = (int) $s['label_radius'];
$header_url  = $s['header_image_id'] ? wp_get_attachment_image_url( $s['header_image_id'], 'full' ) : '';

$club_logos = get_option( 'sta_club_logos', [] );

// Alle club-termen
$club_terms = get_terms( [ 'taxonomy' => 'sta_club', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ] );
if ( is_wp_error( $club_terms ) ) $club_terms = [];
$club_terms = array_values( array_filter( $club_terms, fn( $t ) => $t->count > 0 ) );

// Medailles per club uit uitslagen
$event_results = get_option( 'sta_event_results', [] );
$club_medals   = []; // club_name => [1=>n, 2=>n, 3=>n]

foreach ( $event_results as $eid => $agegroups ) {
    foreach ( $agegroups as $ag_data ) {
        foreach ( $ag_data['entries'] ?? [] as $row ) {
            if ( ! isset( $row['medal'] ) || ! $row['medal'] ) continue;
            $cn = trim( $row['clubname'] ?? '' );
            if ( ! $cn ) continue;
            $club_medals[ $cn ][ $row['medal'] ] = ( $club_medals[ $cn ][ $row['medal'] ] ?? 0 ) + 1;
        }
    }
}

// Sorteerfunctie: goud desc, dan zilver, dan brons, dan naam
usort( $club_terms, function( $a, $b ) use ( $club_medals ) {
    $ma = $club_medals[ $a->name ] ?? [];
    $mb = $club_medals[ $b->name ] ?? [];
    foreach ( [ 1, 2, 3 ] as $p ) {
        $diff = ( $mb[$p] ?? 0 ) - ( $ma[$p] ?? 0 );
        if ( $diff !== 0 ) return $diff;
    }
    return strcmp( $a->name, $b->name );
} );
?>

<?php if ( STA_Templates::show_header() ) : ?>
<div style="
    width:100%;height:<?php echo (int) $s['header_height']; ?>px;
    <?php if ( $header_url ) : ?>background-image:url('<?php echo esc_url( $header_url ); ?>');background-size:cover;background-position:center;
    <?php else : ?>background-color:<?php echo $color; ?>;<?php endif; ?>
"></div>
<?php endif; ?>

<main id="primary" class="site-main">
<div class="container" style="max-width:900px;margin:0 auto;padding:50px 20px 80px;">

    <h1 style="font-size:32px;font-weight:800;color:<?php echo $color; ?>;text-align:center;margin:0 0 8px;">Clubs</h1>
    <p style="text-align:center;color:#888;margin:0 0 16px;font-size:14px;"><?php echo count( $club_terms ); ?> clubs on the start list</p>
    <p style="text-align:center;color:#888;margin:0 0 40px;font-size:13px;">The medal table reflects results from this competition and does not represent official Dutch Championship medals.</p>

    <?php if ( empty( $club_terms ) ) : ?>
    <p style="text-align:center;color:#999;">No clubs found yet. Please import athletes first.</p>
    <?php else : ?>

    <div style="overflow-x:auto;border:1px solid #e0e7f0;border-radius:<?php echo $card_radius; ?>px;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
            <tr style="background:#f8f9fb;border-bottom:2px solid #e0e7f0;">
                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#13315C;width:32px;">#</th>
                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#13315C;">Club</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#13315C;width:56px;">🥇</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#13315C;width:56px;">🥈</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#13315C;width:56px;">🥉</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#13315C;width:60px;">Total</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#13315C;width:72px;">Athletes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $club_terms as $i => $club ) :
            $medals    = $club_medals[ $club->name ] ?? [];
            $gold      = $medals[1] ?? 0;
            $silver    = $medals[2] ?? 0;
            $bronze    = $medals[3] ?? 0;
            $total     = $gold + $silver + $bronze;
            $logo      = $club_logos[ $club->name ] ?? '';
            $club_url  = get_term_link( $club );
            $rank      = $i + 1;
            $row_bg    = $rank === 1 && $gold > 0 ? 'background:#FFFDF0;' : '';
        ?>
        <tr style="border-top:1px solid #f0f4f8;<?php echo $row_bg; ?>transition:background .1s;"
            onmouseover="this.style.background='#f4f7fb'" onmouseout="this.style.background='<?php echo $rank === 1 && $gold > 0 ? '#FFFDF0' : 'transparent'; ?>'">
            <td style="padding:10px 14px;font-weight:700;color:#aaa;font-size:12px;"><?php echo $rank; ?></td>
            <td style="padding:10px 14px;">
                <a href="<?php echo esc_url( $club_url ); ?>" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;">
                    <div style="background:<?php echo $color; ?>;border-radius:6px;padding:6px 8px;display:flex;align-items:center;justify-content:center;width:48px;height:36px;flex-shrink:0;box-sizing:border-box;">
                        <?php if ( $logo ) : ?>
                        <img src="<?php echo esc_url( $logo ); ?>" style="max-height:24px;max-width:36px;width:auto;object-fit:contain;" onerror="this.style.display='none'">
                        <?php endif; ?>
                    </div>
                    <span style="font-weight:700;color:<?php echo $color; ?>;"><?php echo esc_html( $club->name ); ?></span>
                </a>
            </td>
            <td style="padding:10px 14px;text-align:center;font-weight:700;font-size:15px;color:#333;"><?php echo $gold   ?: '<span style="color:#ddd;">–</span>'; ?></td>
            <td style="padding:10px 14px;text-align:center;font-weight:700;font-size:15px;color:#333;"><?php echo $silver ?: '<span style="color:#ddd;">–</span>'; ?></td>
            <td style="padding:10px 14px;text-align:center;font-weight:700;font-size:15px;color:#333;"><?php echo $bronze ?: '<span style="color:#ddd;">–</span>'; ?></td>
            <td style="padding:10px 14px;text-align:center;font-weight:600;color:#555;"><?php echo $total ?: '<span style="color:#ddd;">–</span>'; ?></td>
            <td style="padding:10px 14px;text-align:center;color:#888;font-size:13px;"><?php echo $club->count; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php endif; ?>
</div>
</main>

<?php get_footer(); ?>
