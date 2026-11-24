<?php
get_header();

$term = get_queried_object();

$s = array_merge( [
    'primary_color'   => '#13315c',
    'header_image_id' => 0,
    'header_height'   => 220,
    'flag_shape'      => 'circle',
    'card_radius'     => 12,
    'card_bg'         => '#ffffff',
    'label_radius'    => 50,
    'photo_ratio'     => '3/4',
    'club_badge_bg'   => '#13315C',
    'club_badge_radius' => 16,
], get_option( 'sta_layout', [] ) );

$color       = esc_attr( $s['primary_color'] );
$card_bg     = esc_attr( $s['card_bg'] );
$card_radius = (int) $s['card_radius'];
$lbl_radius  = (int) $s['label_radius'];
$ratio       = esc_attr( $s['photo_ratio'] );
$flag_radius = $s['flag_shape'] === 'circle' ? '50%' : ( $s['flag_shape'] === 'rounded' ? '4px' : '0' );
$header_url  = $s['header_image_id'] ? wp_get_attachment_image_url( $s['header_image_id'], 'full' ) : '';

$club_logos  = get_option( 'sta_club_logos', [] );
$club_name   = $term->name;
$club_logo   = $club_logos[ $club_name ] ?? '';

// Atleten van deze club
$athletes = get_posts( [
    'post_type'      => 'athlete',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'tax_query'      => [ [ 'taxonomy' => 'sta_club', 'field' => 'term_id', 'terms' => $term->term_id ] ],
] );

$groups = [ 'Women' => [], 'Men' => [] ];
foreach ( $athletes as $a ) {
    $g = get_post_meta( $a->ID, 'sta_gender', true );
    if ( $g === 'f' )     $groups['Women'][] = $a;
    elseif ( $g === 'm' ) $groups['Men'][]   = $a;
}

// Medaillespiegel voor deze club
$event_results = get_option( 'sta_event_results', [] );
$medals = [ 1 => 0, 2 => 0, 3 => 0 ];

foreach ( $event_results as $eid => $agegroups ) {
    foreach ( $agegroups as $ag_data ) {
        foreach ( $ag_data['entries'] ?? [] as $row ) {
            if ( strcasecmp( trim( $row['clubname'] ?? '' ), $club_name ) !== 0 ) continue;
            if ( isset( $medals[ $row['medal'] ] ) ) $medals[ $row['medal'] ]++;
        }
    }
}
$total_medals = $medals[1] + $medals[2] + $medals[3];
?>

<div style="
    width:100%;height:<?php echo (int) $s['header_height']; ?>px;
    <?php if ( $header_url ) : ?>background-image:url('<?php echo esc_url( $header_url ); ?>');background-size:cover;background-position:center;
    <?php else : ?>background-color:<?php echo $color; ?>;<?php endif; ?>
"></div>

<main id="primary" class="site-main">
<div class="container" style="max-width:1000px;margin:0 auto;padding:50px 20px 80px;">

    <!-- Terug knop -->
    <div style="margin-bottom:24px;">
        <a href="/clubs/" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:<?php echo $color; ?>;text-decoration:none;opacity:.8;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.8'">
            ← All clubs
        </a>
    </div>

    <!-- Club header -->
    <div style="display:flex;align-items:center;gap:24px;margin-bottom:48px;flex-wrap:wrap;">
        <?php if ( $club_logo ) : ?>
        <img src="<?php echo esc_url( $club_logo ); ?>" style="max-height:80px;width:auto;" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
            <h1 style="font-size:32px;font-weight:800;color:<?php echo $color; ?>;margin:0 0 4px;"><?php echo esc_html( $club_name ); ?></h1>
            <p style="margin:0;color:#666;font-size:15px;"><?php echo count( $athletes ); ?> athlete<?php echo count( $athletes ) !== 1 ? 's' : ''; ?> on the start list</p>
        </div>
    </div>

    <style>
    .sta-gender-columns{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}
    .sta-athlete-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}
    @media(max-width:700px){
        .sta-gender-columns{grid-template-columns:1fr;gap:24px}
        .sta-athlete-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
        .sta-athlete-card-club{display:none}
    }
    </style>

    <!-- Medaillespiegel -->
    <?php if ( $total_medals > 0 ) : ?>
    <div style="display:flex;gap:16px;margin-bottom:40px;flex-wrap:wrap;">
        <?php foreach ( [ 1 => [ '🥇', 'Gold', '#FFD700' ], 2 => [ '🥈', 'Silver', '#C0C0C0' ], 3 => [ '🥉', 'Bronze', '#CD7F32' ] ] as $place => [ $emoji, $label, $clr ] ) : ?>
        <div style="flex:1;min-width:90px;background:#fff;border:1px solid #e0e7f0;border-radius:<?php echo $card_radius; ?>px;padding:16px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.04);">
            <div style="font-size:32px;line-height:1;"><?php echo $emoji; ?></div>
            <div style="font-size:28px;font-weight:800;color:<?php echo $color; ?>;margin:4px 0 2px;"><?php echo $medals[ $place ]; ?></div>
            <div style="font-size:12px;color:#888;"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Atleten -->
    <?php if ( ! empty( $athletes ) ) : ?>
    <h2 style="font-size:20px;font-weight:700;color:<?php echo $color; ?>;margin:0 0 20px;">Athletes</h2>
    <div class="sta-gender-columns" style="margin-bottom:56px;">
    <?php foreach ( $groups as $label => $group ) :
        if ( empty( $group ) ) continue; ?>
        <div>
            <h3 style="font-size:16px;font-weight:700;color:#fff;background:<?php echo $color; ?>;display:inline-block;padding:4px 16px;border-radius:<?php echo $lbl_radius; ?>px;margin:0 0 16px;">
                <?php echo esc_html( $label ); ?> <span style="font-weight:400;opacity:.75;">(<?php echo count( $group ); ?>)</span>
            </h3>
            <div class="sta-athlete-grid">
            <?php foreach ( $group as $athlete ) :
                $country_code = get_post_meta( $athlete->ID, 'sta_country', true );
            ?>
                <a href="<?php echo get_permalink( $athlete->ID ); ?>" style="text-decoration:none;color:inherit;display:block;background:<?php echo $card_bg; ?>;border:1px solid #e5e7eb;border-radius:<?php echo $card_radius; ?>px;overflow:hidden;transition:box-shadow .15s;"
                   onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='none'">
                    <?php if ( has_post_thumbnail( $athlete->ID ) ) : ?>
                    <div style="aspect-ratio:<?php echo $ratio; ?>;overflow:hidden;">
                        <?php echo get_the_post_thumbnail( $athlete->ID, 'medium', [
                            'style' => 'width:100%;height:100%;object-fit:cover;display:block;' . sta_focus_style( $athlete->ID ),
                        ] ); ?>
                    </div>
                    <?php else : ?>
                    <div style="aspect-ratio:<?php echo $ratio; ?>;background:#e5e7eb;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:40px;opacity:.3;">🏊</span>
                    </div>
                    <?php endif; ?>
                    <div style="padding:8px 10px;">
                        <div style="display:flex;align-items:center;gap:5px;margin-bottom:2px;">
                            <?php if ( $country_code ) : ?>
                            <img src="https://hatscripts.github.io/circle-flags/flags/<?php echo esc_attr( strtolower( $country_code ) ); ?>.svg" style="width:14px;height:14px;border-radius:<?php echo $flag_radius; ?>;flex-shrink:0;" alt="">
                            <?php endif; ?>
                            <strong style="font-size:12px;color:<?php echo $color; ?>;line-height:1.2;"><?php echo esc_html( $athlete->post_title ); ?></strong>
                        </div>
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
