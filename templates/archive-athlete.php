<?php
get_header();

$s   = get_option('sta_layout', []);
$def = [
    'primary_color'   => '#13315c',
    'header_image_id' => 0,
    'header_height'   => 220,
    'flag_shape'      => 'circle',
    'card_radius'     => 12,
    'card_bg'         => '#ffffff',
    'label_radius'    => 50,
    'photo_ratio'     => '3/4',
];
$s = array_merge($def, $s);

$color       = esc_attr($s['primary_color']);
$card_bg     = esc_attr($s['card_bg']);
$card_radius = (int)$s['card_radius'];
$lbl_radius  = (int)$s['label_radius'];
$ratio       = esc_attr($s['photo_ratio']);
$flag_radius = $s['flag_shape'] === 'circle' ? '50%' : ($s['flag_shape'] === 'rounded' ? '4px' : '0');

$header_url = $s['header_image_id'] ? wp_get_attachment_image_url($s['header_image_id'], 'full') : '';

$all_athletes = get_posts([
    'post_type'      => 'athlete',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

// Afstand-termen gesorteerd op programma-volgorde
$afstand_terms = get_terms(['taxonomy' => 'afstand', 'hide_empty' => true, 'orderby' => 'name']);
// Sort by earliest program entry date+session+number
if (!is_wp_error($afstand_terms) && class_exists('STA_Program')) {
    usort($afstand_terms, function($a, $b) {
        foreach (['m','f'] as $g) {
            $ea = STA_Program::get_entries_for_term($a->name, $g);
            $eb = STA_Program::get_entries_for_term($b->name, $g);
            if (!empty($ea) || !empty($eb)) {
                $da = !empty($ea) ? ($ea[0]['date'] . sprintf('%04d', $ea[0]['order'])) : '9999';
                $db = !empty($eb) ? ($eb[0]['date'] . sprintf('%04d', $eb[0]['order'])) : '9999';
                return strcmp($da, $db);
            }
        }
        return strcmp($a->name, $b->name);
    });
}

$groups = ['Women' => [], 'Men' => []];
foreach ($all_athletes as $athlete) {
    $gender = get_post_meta($athlete->ID, 'sta_gender', true);
    if ($gender === 'f')     $groups['Women'][] = $athlete;
    elseif ($gender === 'm') $groups['Men'][]   = $athlete;
}

// Build per-athlete term slug list for JS filtering
$athlete_terms = [];
foreach ($all_athletes as $athlete) {
    $terms = wp_get_object_terms($athlete->ID, 'afstand', ['fields' => 'slugs']);
    $athlete_terms[$athlete->ID] = is_array($terms) ? $terms : [];
}

foreach ($groups as &$group) {
    usort($group, function($a, $b) {
        $fa = (bool) get_post_meta($a->ID, 'sta_featured', true);
        $fb = (bool) get_post_meta($b->ID, 'sta_featured', true);
        if ($fa !== $fb) return $fb <=> $fa;
        return strcmp($a->post_title, $b->post_title);
    });
}
unset($group);
?>

<?php if ( STA_Templates::show_header() ) : ?>
<div class="athlete-header-image" style="
    width:100%;
    height:<?php echo (int)$s['header_height']; ?>px;
    <?php if ($header_url) : ?>
    background-image:url('<?php echo esc_url($header_url); ?>');
    background-size:cover;
    background-position:center;
    <?php else : ?>
    background-color:<?php echo $color; ?>;
    <?php endif; ?>
"></div>
<?php endif; ?>

<main id="primary" class="site-main">
    <div class="container" style="max-width:1000px;margin:0 auto;padding:50px 20px 80px;">

        <h1 style="font-size:36px;font-weight:700;color:<?php echo $color; ?>;text-align:center;margin-bottom:8px;">Athletes</h1>
        <p style="text-align:center;color:#666;margin-bottom:24px;">
            <span id="sta-athlete-count"><?php echo count($all_athletes); ?></span> athlete<?php echo count($all_athletes) !== 1 ? 's' : ''; ?> on the start list
        </p>

        <?php if (!is_wp_error($afstand_terms) && !empty($afstand_terms)) : ?>
        <!-- Desktop: buttons -->
        <div class="sta-filter-desktop" style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-bottom:36px;" id="sta-distance-filters">
            <button class="sta-dist-btn active" data-slug=""
                style="background:<?php echo $color; ?>;color:#fff;border:none;padding:6px 18px;border-radius:<?php echo $lbl_radius; ?>px;font-size:13px;font-weight:600;cursor:pointer;">
                All
            </button>
            <?php foreach ($afstand_terms as $term) : ?>
            <button class="sta-dist-btn" data-slug="<?php echo esc_attr($term->slug); ?>"
                style="background:#e5e7eb;color:#333;border:none;padding:6px 18px;border-radius:<?php echo $lbl_radius; ?>px;font-size:13px;font-weight:600;cursor:pointer;">
                <?php echo esc_html($term->name); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <!-- Mobile: dropdown -->
        <div class="sta-filter-mobile" style="display:none;margin-bottom:28px;">
            <select id="sta-distance-select" style="width:100%;padding:10px 14px;border:2px solid <?php echo $color; ?>;border-radius:8px;font-size:14px;font-weight:600;color:<?php echo $color; ?>;background:#fff;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%228%22 viewBox=%220 0 12 8%22><path d=%22M1 1l5 5 5-5%22 stroke=%22%23333%22 stroke-width=%221.5%22 fill=%22none%22 stroke-linecap=%22round%22/></svg>');background-repeat:no-repeat;background-position:right 14px center;cursor:pointer;">
                <option value="">All distances</option>
                <?php foreach ($afstand_terms as $term) : ?>
                <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <style>
        @media (max-width:640px) {
            .sta-filter-desktop { display:none !important; }
            .sta-filter-mobile  { display:block !important; }
        }
        </style>
        <?php endif; ?>

        <style>
        .sta-gender-columns { display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start; }
        .sta-athlete-grid   { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px; }
        @media (max-width:900px) {
            .sta-gender-columns { gap:8px; }
            .sta-athlete-grid   { grid-template-columns:1fr !important;gap:8px; }
            .sta-athlete-card-img { aspect-ratio:1 !important; }
            .sta-athlete-card-name { font-size:12px !important; }
            .sta-athlete-card-club { display:none; }
        }
        </style>

        <div class="sta-gender-columns">
        <?php foreach ($groups as $label => $athletes) :
            if (empty($athletes)) continue; ?>

            <div>
                <h2 style="
                    font-size:22px;font-weight:700;color:#fff;
                    background:<?php echo $color; ?>;
                    display:inline-block;padding:6px 20px;
                    border-radius:<?php echo $lbl_radius; ?>px;
                    margin:0 0 20px;
                "><?php echo esc_html($label); ?> <span style="font-size:16px;font-weight:400;opacity:.75;">(<?php echo count($athletes); ?>)</span></h2>

                <div class="sta-athlete-grid">
                <?php foreach ($athletes as $athlete) :
                    $country_code = get_post_meta($athlete->ID, 'sta_country', true);
                    $club         = get_post_meta($athlete->ID, 'sta_club', true);
                    $featured     = (bool) get_post_meta($athlete->ID, 'sta_featured', true);
                ?>
                    <a href="<?php echo get_permalink($athlete->ID); ?>"
                       class="sta-athlete-card"
                       data-terms="<?php echo esc_attr(implode(' ', $athlete_terms[$athlete->ID] ?? [])); ?>"
                       style="
                        text-decoration:none;color:inherit;display:block;
                        background:<?php echo $card_bg; ?>;
                        border:1px solid #e5e7eb;
                        border-radius:<?php echo $card_radius; ?>px;
                        overflow:hidden;transition:box-shadow .15s;
                        <?php echo $featured ? 'box-shadow:0 0 0 2px ' . $color . ';' : ''; ?>
                    " onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
                       onmouseout="this.style.boxShadow='<?php echo $featured ? '0 0 0 2px ' . $color : 'none'; ?>'"
                    >

                        <?php if ( STA_Modules::is_active( 'photos' ) && has_post_thumbnail( $athlete->ID ) ) : ?>
                            <div class="sta-athlete-card-img" style="aspect-ratio:<?php echo $ratio; ?>;overflow:hidden;">
                                <?php echo get_the_post_thumbnail($athlete->ID, 'medium', [
                                    'style' => 'width:100%;height:100%;object-fit:cover;display:block;' . sta_focus_style($athlete->ID),
                                ]); ?>
                            </div>
                        <?php elseif ( STA_Modules::is_active( 'photos' ) ) : ?>
                            <div class="sta-athlete-card-img" style="aspect-ratio:<?php echo $ratio; ?>;background:#e5e7eb;display:flex;align-items:center;justify-content:center;">
                                <span style="font-size:48px;opacity:.3;">🏊</span>
                            </div>
                        <?php endif; ?>

                        <div style="padding:10px 12px;">
                            <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                                <?php if ($country_code) : ?>
                                    <img src="https://hatscripts.github.io/circle-flags/flags/<?php echo esc_attr(strtolower($country_code)); ?>.svg"
                                         style="width:16px;height:16px;border-radius:<?php echo $flag_radius; ?>;flex-shrink:0;" alt="">
                                <?php endif; ?>
                                <strong class="sta-athlete-card-name" style="font-size:13px;color:<?php echo $color; ?>;line-height:1.2;">
                                    <?php echo esc_html($athlete->post_title); ?>
                                </strong>
                            </div>
                            <?php if ($club) : ?>
                                <div class="sta-athlete-card-club" style="font-size:12px;color:#666;"><?php echo esc_html($club); ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>

        <?php endforeach; ?>
        </div>

    </div>
</main>

<script>
(function() {
    var activeSlug  = '';
    var activeColor = '<?php echo esc_js($color); ?>';
    var btnActive   = 'background:' + activeColor + ';color:#fff;';
    var btnInactive = 'background:#e5e7eb;color:#333;';

    function applyFilter(slug) {
        activeSlug = slug;
        // Sync buttons
        document.querySelectorAll('.sta-dist-btn').forEach(function(b) {
            var isActive = b.dataset.slug === slug;
            b.style.cssText = (isActive ? btnActive : btnInactive) + 'border:none;padding:6px 18px;border-radius:<?php echo $lbl_radius; ?>px;font-size:13px;font-weight:600;cursor:pointer;';
        });
        // Sync dropdown
        var sel = document.getElementById('sta-distance-select');
        if (sel) sel.value = slug;
        // Filter cards
        var visible = 0;
        document.querySelectorAll('.sta-athlete-card').forEach(function(card) {
            var terms = card.dataset.terms || '';
            var show  = !slug || terms.split(' ').indexOf(slug) !== -1;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var countEl = document.getElementById('sta-athlete-count');
        if (countEl) countEl.textContent = visible;
    }

    // Auto-activate filter from URL param ?afstand=slug
    var urlSlug = new URLSearchParams(window.location.search).get('afstand');
    if (urlSlug) setTimeout(function() { applyFilter(urlSlug); }, 0);

    document.querySelectorAll('.sta-dist-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { applyFilter(this.dataset.slug); });
    });

    var selectEl = document.getElementById('sta-distance-select');
    if (selectEl) {
        selectEl.addEventListener('change', function() { applyFilter(this.value); });
    }
})();
</script>

<?php get_footer(); ?>
