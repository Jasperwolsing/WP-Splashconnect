<?php
defined('ABSPATH') || exit;

class STA_Admin_SwimRankings {

    const API_BASE  = 'https://live.swimrankings.net/meets/';
    const MEETS_URL = 'https://live.swimrankings.net/index.php?Cmd=Meets';

    const NATION_MAP = [
        'NED'=>'NL','BEL'=>'BE','GER'=>'DE','FRA'=>'FR','GBR'=>'GB','ESP'=>'ES',
        'ITA'=>'IT','SUI'=>'CH','AUT'=>'AT','DEN'=>'DK','SWE'=>'SE','NOR'=>'NO',
        'FIN'=>'FI','POL'=>'PL','HUN'=>'HU','CZE'=>'CZ','SVK'=>'SK','ROU'=>'RO',
        'BUL'=>'BG','CRO'=>'HR','SLO'=>'SI','SRB'=>'RS','TUR'=>'TR','POR'=>'PT',
        'ISR'=>'IL','USA'=>'US','CAN'=>'CA','AUS'=>'AU','JPN'=>'JP','CHN'=>'CN',
        'RSA'=>'ZA','BRA'=>'BR','ARG'=>'AR','MEX'=>'MX','UKR'=>'UA','RUS'=>'RU',
        'LTU'=>'LT','LAT'=>'LV','EST'=>'EE','IRL'=>'IE','NZL'=>'NZ','IND'=>'IN',
        'SGP'=>'SG','HKG'=>'HK','AHO'=>'CW','MDA'=>'MD','LUX'=>'LU','GRE'=>'GR',
        'CYP'=>'CY','MLT'=>'MT','MKD'=>'MK','ALG'=>'DZ',
        'TTO'=>'TT','ARU'=>'AW','JAM'=>'JM','BAH'=>'BS','BAR'=>'BB','GUY'=>'GY','SUR'=>'SR',
        'PAN'=>'PA','CRC'=>'CR','GUA'=>'GT','CHI'=>'CL','COL'=>'CO','VEN'=>'VE',
        'PER'=>'PE','ECU'=>'EC','URU'=>'UY','PAR'=>'PY','BOL'=>'BO',
        'KAZ'=>'KZ','UZB'=>'UZ','AZE'=>'AZ','ARM'=>'AM','GEO'=>'GE','BLR'=>'BY',
        'KOR'=>'KR','TPE'=>'TW','THA'=>'TH','MAS'=>'MY','INA'=>'ID','PHI'=>'PH',
        'VIE'=>'VN','KEN'=>'KE','GHA'=>'GH','NGR'=>'NG','MAR'=>'MA','TUN'=>'TN',
        'EGY'=>'EG','ZIM'=>'ZW','ISL'=>'IS','SVN'=>'SI','SCO'=>'GB-SCT',
        'QAT'=>'QA','UAE'=>'AE','KUW'=>'KW','BRN'=>'BH','OMA'=>'OM','JOR'=>'JO',
        'LIB'=>'LB','SYR'=>'SY','IRQ'=>'IQ','IRN'=>'IR','PAK'=>'PK','SRI'=>'LK',
    ];

    const STROKE_MAP = [
        '1'=>'Freestyle','2'=>'Backstroke','3'=>'Breaststroke','4'=>'Butterfly',
        '5'=>'Individual Medley','6'=>'Freestyle','7'=>'Medley',
    ];

    const ROUND_MAP = [
        '1'=>'Finals','2'=>'Semifinals','3'=>'Heats','4'=>'Heats','11'=>'Other',
    ];

    public static function register(): void {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('wp_ajax_sta_sr_import_athletes',     [__CLASS__, 'ajax_import_athletes']);
        add_action('wp_ajax_sta_sr_import_program',      [__CLASS__, 'ajax_import_program']);
        add_action('wp_ajax_sta_sr_fetch_meet',          [__CLASS__, 'ajax_fetch_meet']);
        add_action('wp_ajax_sta_sr_link_meet',           [__CLASS__, 'ajax_link_meet']);
        add_action('wp_ajax_sta_sr_link_athlete_events', [__CLASS__, 'ajax_link_athlete_events']);
        add_action('wp_ajax_sta_sr_delete_all_athletes', [__CLASS__, 'ajax_delete_all_athletes']);
        add_action('wp_ajax_sta_sr_save_preset',         [__CLASS__, 'ajax_save_preset']);
        add_action('wp_ajax_sta_sr_set_active_preset',   [__CLASS__, 'ajax_set_active_preset']);
        add_action('wp_ajax_sta_sr_delete_preset',       [__CLASS__, 'ajax_delete_preset']);
        add_action('wp_ajax_sta_sr_fetch_logos',         [__CLASS__, 'ajax_fetch_logos']);
        add_action('wp_ajax_sta_sr_import_results',      [__CLASS__, 'ajax_import_results']);
        add_action('wp_ajax_sta_sr_merge_duplicates',        [__CLASS__, 'ajax_merge_duplicates']);
        add_action('wp_ajax_sta_sr_unlink_relays',           [__CLASS__, 'ajax_unlink_relays']);
        add_action('wp_ajax_sta_sr_clear_results',          [__CLASS__, 'ajax_clear_results']);
        add_action('wp_ajax_sta_sr_clear_heats_cache',      [__CLASS__, 'ajax_clear_heats_cache']);
        add_action('wp_ajax_sta_save_startlist_autoopen',   [__CLASS__, 'ajax_save_startlist_autoopen']);
        add_action('wp_ajax_sta_live_refresh',              [__CLASS__, 'ajax_live_refresh']);
        add_action('wp_ajax_nopriv_sta_live_refresh',       [__CLASS__, 'ajax_live_refresh']);
        add_action('sta_program_refresh_cron',              [__CLASS__, 'cron_refresh_program']);
        add_filter('cron_schedules',                        [__CLASS__, 'add_twohour_schedule']);
        if ( ! wp_next_scheduled('sta_program_refresh_cron') ) {
            wp_schedule_event( time() + 300, 'sta_every_2_hours', 'sta_program_refresh_cron' );
        }
    }

    public static function add_twohour_schedule( array $schedules ): array {
        $schedules['sta_every_2_hours'] = [
            'interval' => 2 * HOUR_IN_SECONDS,
            'display'  => 'Every 2 hours',
        ];
        return $schedules;
    }

    public static function cron_refresh_program(): void {
        $meet_id = get_option('sta_swimrankings_meet_id', '');
        if ( ! $meet_id ) return;
        self::do_import_program( $meet_id );
    }

    public static function do_import_program( string $meet_id ): array {
        $sessions_data = self::api_get(self::API_BASE . $meet_id . '/eventsBySession.json');
        $events_raw    = self::api_get(self::API_BASE . $meet_id . '/events.json');
        if ( ! $sessions_data || ! $events_raw ) return ['error' => 'Ophalen mislukt'];

        $events = [];
        foreach ($events_raw as $key => $ev) {
            if (!is_array($ev)) continue;
            $id = $ev['id'] ?? $ev['eid'] ?? null;
            if ($id !== null) {
                $events[(string)$id] = $ev;
                $events[(int)$id]    = $ev;
            }
        }

        $sessions = $sessions_data['sessions'] ?? [];
        $program  = [];
        $skipped  = 0;

        foreach ($sessions as $session) {
            $date      = $session['date']   ?? '';
            $sess_name = $session['name']   ?? '';
            $sess_time = $session['time']   ?? '';
            $sess_num  = $session['number'] ?? 0;
            $sess_id   = $session['id']     ?? '';

            foreach (($session['events'] ?? []) as $se) {
                $eid = $se['id'] ?? null;
                if ($eid === null) { $skipped++; continue; }
                $ev = $events[(string)$eid] ?? $events[(int)$eid] ?? null;
                if (!$ev) { $skipped++; continue; }
                $dist = $ev['distance'] ?? '';
                if ($dist === '' || $dist === null) { $skipped++; continue; }
                $gender_code = (string)($ev['gender'] ?? '0');
                if ($gender_code === '0' && empty($ev['stroke'])) { $skipped++; continue; }
                $stroke_code = (string)($ev['stroke'] ?? '');
                $round_code  = (string)($ev['round']  ?? '');
                $stroke   = self::STROKE_MAP[$stroke_code] ?? $stroke_code;
                $round    = self::ROUND_MAP[$round_code]   ?? $round_code;
                $gender_c = $gender_code === '2' ? 'Vrouwen' : ($gender_code === '1' ? 'Mannen' : 'Mixed');
                $program[] = [
                    'date'         => $date,
                    'session'      => (int)$sess_num,
                    'session_id'   => (string)$sess_id,
                    'session_name' => $sess_name,
                    'session_time' => $sess_time,
                    'event_id'     => (string)$eid,
                    'number'       => (int)($ev['number'] ?? 0),
                    'gender'       => $gender_c,
                    'gender_code'  => $gender_code,
                    'distance'     => (string)$dist,
                    'stroke'       => $stroke,
                    'stroke_code'  => $stroke_code,
                    'round'        => $round,
                    'round_code'   => $round_code,
                    'time'         => $ev['time']     ?? '',
                    'is_relay'     => !empty($ev['isrelay']),
                    'agegroup'     => $ev['agegroup']['name'] ?? '',
                ];
            }
        }

        update_option('sta_swimrankings_program', $program);

        $eid_term_map = [];
        foreach (STA_Program::get_program() as $pe) {
            $term_obj = get_term_by('name', $pe['term_name'], 'afstand');
            if (!$term_obj) continue;
            $eids_map = !empty($pe['event_ids']) ? $pe['event_ids'] : (!empty($pe['event_id']) ? [$pe['event_id']] : []);
            foreach ($eids_map as $eid) { $eid_term_map[$eid] = $term_obj->slug; }
        }
        update_option('sta_eid_term_map', $eid_term_map);

        return ['imported' => count($program), 'sessions' => count($sessions), 'skipped' => $skipped];
    }

    // ── Preset helpers ────────────────────────────────────────────────────────
    public static function default_preset(): array {
        return [
            'name'                 => 'NK sprint',
            // Session type detection (regex against session name)
            'session_heats'        => 'series|voorronde|heat',
            'session_semis'        => 'halve.finale',
            'session_finals'       => 'finale',
            // Relay display labels per gender
            'relay_f'              => 'Dames',
            'relay_m'              => 'Heren',
            'relay_x'              => 'Mixed',
            // Age/para badge rules
            'senior_pattern'       => 'senior|open|absoluut|master|elite|para',
            'para_keyword'         => 'para',   // regex: agegroup contains this → para event
            'show_age_in_heats'    => false,    // suppress age badges in series (swimmers share heats)
            'relay_separate_by_age'=> true,     // Onder 18 relay gets its own taxonomy page
            'u18_shared_heats'     => true,     // NK sprint: U18 + seniors share heats bucket
            // NK sprint: round=2 = regular heats, round=3 = swim-offs → exclude from program
            'heats_exclude_rounds' => ['3'],
        ];
    }

    public static function swimming_trials_preset(): array {
        return array_merge( self::default_preset(), [
            'name'                    => 'Swimming Trials',
            // Finals in sessions 2/5/7/10 bevatten round=9 (B&C) én round=1 (A)
            // in dezelfde API-sessie — split ze als twee aparte blokken
            'split_finals_rounds'     => true,
            'bc_finals_round'         => '9',
            'bc_finals_label'         => 'B & C Finales',
            'a_finals_label'          => 'A Finales',
            // NJK-sessies behouden hun eigen naam i.p.v. generiek "A Finales"
            'session_label_overrides' => [
                'NJK lange afstanden' => 'NJK Lange Afstanden',
            ],
            // Swim-offs hebben round='3' in series-sessies (normale series = round='2')
            'heats_exclude_rounds'    => ['3'],
        ]);
    }

    public static function get_presets(): array {
        $presets = get_option('sta_presets', []);
        $changed = false;
        if (empty($presets)) {
            $presets['nk_sprint'] = self::default_preset();
            $changed = true;
        }
        // Merge new default fields into existing nk_sprint preset without overwriting user changes.
        $nk_defaults = self::default_preset();
        if (isset($presets['nk_sprint'])) {
            $merged = array_merge($nk_defaults, $presets['nk_sprint']);
            if ($merged !== $presets['nk_sprint']) {
                $presets['nk_sprint'] = $merged;
                $changed = true;
            }
        }
        // Merge new default fields into existing swimming_trials preset without overwriting user changes.
        $st_defaults = self::swimming_trials_preset();
        if (!isset($presets['swimming_trials'])) {
            $presets['swimming_trials'] = $st_defaults;
            $changed = true;
        } else {
            $merged = array_merge($st_defaults, $presets['swimming_trials']);
            if ($merged !== $presets['swimming_trials']) {
                $presets['swimming_trials'] = $merged;
                $changed = true;
            }
        }
        if ($changed) update_option('sta_presets', $presets);
        return $presets;
    }

    public static function get_active_preset(): array {
        $presets = self::get_presets();
        $active  = get_option('sta_active_preset', '');
        return $presets[$active] ?? reset($presets) ?: self::default_preset();
    }

    // ── AJAX: save (create/update) preset ────────────────────────────────────
    public static function ajax_save_preset(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $key  = sanitize_key($_POST['preset_key'] ?? '');
        $is_new = !empty($_POST['is_new']);

        $presets = self::get_presets();

        if ($is_new) {
            // Copy from existing or blank
            $copy_from = sanitize_key($_POST['copy_from'] ?? '');
            $base      = $presets[$copy_from] ?? self::default_preset();
            $key       = sanitize_key($_POST['new_key'] ?? '');
            if (!$key) wp_send_json_error('Ongeldige sleutel.');
            $preset    = $base;
        } else {
            $preset = $presets[$key] ?? self::default_preset();
        }

        $preset['name']           = sanitize_text_field($_POST['name']           ?? $preset['name']);
        $preset['session_heats']  = sanitize_text_field($_POST['session_heats']  ?? $preset['session_heats']);
        $preset['session_semis']  = sanitize_text_field($_POST['session_semis']  ?? $preset['session_semis']);
        $preset['session_finals'] = sanitize_text_field($_POST['session_finals'] ?? $preset['session_finals']);
        $preset['relay_f']        = sanitize_text_field($_POST['relay_f']        ?? $preset['relay_f']);
        $preset['relay_m']        = sanitize_text_field($_POST['relay_m']        ?? $preset['relay_m']);
        $preset['relay_x']        = sanitize_text_field($_POST['relay_x']        ?? $preset['relay_x']);
        $preset['senior_pattern']        = sanitize_text_field($_POST['senior_pattern']        ?? $preset['senior_pattern']);
        $preset['para_keyword']          = sanitize_text_field($_POST['para_keyword']          ?? ($preset['para_keyword'] ?? 'para'));
        $preset['show_age_in_heats']     = !empty($_POST['show_age_in_heats']);
        $preset['relay_separate_by_age'] = isset($_POST['relay_separate_by_age']) ? (bool)$_POST['relay_separate_by_age'] : ($preset['relay_separate_by_age'] ?? true);
        $preset['u18_shared_heats']      = !empty($_POST['u18_shared_heats']);
        $preset['split_finals_rounds']   = !empty($_POST['split_finals_rounds']);
        $preset['bc_finals_round']       = sanitize_text_field($_POST['bc_finals_round'] ?? ($preset['bc_finals_round'] ?? '9'));
        $preset['bc_finals_label']       = sanitize_text_field($_POST['bc_finals_label'] ?? ($preset['bc_finals_label'] ?? 'B & C Finales'));
        $preset['a_finals_label']        = sanitize_text_field($_POST['a_finals_label']  ?? ($preset['a_finals_label']  ?? 'A Finales'));
        // heats_exclude_rounds: comma-separated round codes to skip in heats sessions
        $ex_raw = sanitize_text_field($_POST['heats_exclude_rounds'] ?? '');
        $preset['heats_exclude_rounds'] = array_values(array_filter(array_map('trim', explode(',', $ex_raw))));

        // session_label_overrides: one "API naam|Display label" per line
        $overrides_raw = sanitize_textarea_field($_POST['session_label_overrides'] ?? '');
        $overrides = [];
        foreach (array_filter(array_map('trim', explode("\n", $overrides_raw))) as $line) {
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) $overrides[trim($parts[0])] = trim($parts[1]);
        }
        $preset['session_label_overrides'] = $overrides;

        $presets[$key] = $preset;
        update_option('sta_presets', $presets);

        if ($is_new) {
            update_option('sta_active_preset', $key);
        }

        wp_send_json_success(['key' => $key, 'preset' => $preset]);
    }

    // ── AJAX: set active preset ───────────────────────────────────────────────
    public static function ajax_set_active_preset(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        $key = sanitize_key($_POST['preset_key'] ?? '');
        $presets = self::get_presets();
        if (!isset($presets[$key])) wp_send_json_error('Preset niet gevonden.');
        update_option('sta_active_preset', $key);
        wp_send_json_success();
    }

    // ── AJAX: delete preset ───────────────────────────────────────────────────
    public static function ajax_delete_preset(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        $key     = sanitize_key($_POST['preset_key'] ?? '');
        $presets = self::get_presets();
        if (count($presets) <= 1) wp_send_json_error('Laatste preset kan niet worden verwijderd.');
        unset($presets[$key]);
        update_option('sta_presets', $presets);
        if (get_option('sta_active_preset') === $key) {
            update_option('sta_active_preset', array_key_first($presets));
        }
        wp_send_json_success();
    }

    public static function add_menu(): void {
        add_submenu_page(
            'swimming-trials-ai',
            'SwimRankings import',
            'SwimRankings import',
            'manage_options',
            'sta-swimrankings',
            [__CLASS__, 'render']
        );
    }

    /**
     * Extract first and last heat times and write them into $program_times.
     * First heat time → BC block (if present), last heat time → A block.
     * For events with only one block, first time → that block.
     */
    private static function extract_heat_times(
        array $heats,
        string $event_id,
        array $event_bc_key,
        array $event_a_key,
        array $event_solo_key,
        array &$program_times,
        bool &$times_changed
    ): void {
        $times = [];
        foreach ($heats as $heat) {
            $t = $heat['heatinfo']['time'] ?? '';
            if ($t) $times[] = $t;
        }
        if (empty($times)) return;
        sort($times);
        $first = $times[0];
        $last  = end($times);

        if (isset($event_bc_key[$event_id])) {
            $program_times[$event_bc_key[$event_id]] = $first;
            if (isset($event_a_key[$event_id])) {
                $program_times[$event_a_key[$event_id]] = $last;
            }
            $times_changed = true;
        } elseif (isset($event_a_key[$event_id])) {
            $program_times[$event_a_key[$event_id]] = $first;
            $times_changed = true;
        }
        // Note: heats/semis start_time comes directly from the API event's scheduled time,
        // so we intentionally do not write solo-key entries here.
    }

    private static function api_get(string $url): ?array {
        $resp = wp_remote_get($url, ['timeout' => 20, 'sslverify' => false]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    // ── AJAX: fetch single meet info ──────────────────────────────────────────
    public static function ajax_fetch_meet(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        $id   = sanitize_text_field($_POST['meet_id'] ?? '');
        $data = self::api_get(self::API_BASE . $id . '/main.json');
        if (!$data) wp_send_json_error('Meet niet gevonden.');
        wp_send_json_success([
            'name'     => $data['name'] ?? '',
            'city'     => $data['city'] ?? '',
            'start'    => $data['startdate'] ?? '',
            'end'      => $data['enddate'] ?? '',
            'athletes' => $data['statistic']['athletes'] ?? '?',
            'events'   => $data['statistic']['events']   ?? '?',
        ]);
    }

    // ── AJAX: link meet (save option) ─────────────────────────────────────────
    public static function ajax_link_meet(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        $id = sanitize_text_field($_POST['meet_id'] ?? '');
        if (!$id) wp_send_json_error('Geen ID.');
        $old_id = get_option('sta_swimrankings_meet_id', '');
        update_option('sta_swimrankings_meet_id', $id);
        if ($old_id !== $id) {
            delete_option('sta_event_results');
        }
        wp_send_json_success();
    }

    // ── AJAX: link athletes to afstand taxonomy via results per event ─────────
    public static function ajax_link_athlete_events(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $meet_id = sanitize_text_field($_POST['meet_id'] ?? '');
        if (!$meet_id) wp_send_json_error('Geen meet ID.');

        // Load stored program to build event_id → term_name map
        $program = get_option('sta_swimrankings_program', []);
        if (empty($program)) wp_send_json_error('Importeer eerst het programma.');

        // Build event_id → term_name map from raw program (all event IDs, before dedup)
        $event_terms = []; // event_id => ['term' => term_name, 'relay' => bool]
        $relay_gender_labels = ['f' => 'Dames', 'm' => 'Heren', 'x' => 'Mixed'];
        foreach ($program as $e) {
            $eid = $e['event_id'] ?? '';
            if (!$eid || isset($event_terms[$eid])) continue;
            $dist     = $e['distance']; // already "50m" / "4 x 50m"
            $is_relay = !empty($e['is_relay']) || strpos($dist, 'x') !== false;
            $term     = $dist . ' ' . ($e['stroke'] ?? '');
            if ($is_relay) {
                $gc  = (string)($e['gender_code'] ?? '1');
                $g   = ($gc === '3' || $gc === '0') ? 'x' : (substr($gc, -1) === '2' ? 'f' : 'm');
                $term .= ' ' . ($relay_gender_labels[$g] ?? '');
                // Include age category for non-senior relay events (creates separate pages)
                $ag = $e['agegroup'] ?? '';
                if ($ag && !preg_match('/senior|open|absoluut|master|elite|para/i', $ag)) {
                    $term .= ' ' . $ag;
                }
            }
            $event_terms[$eid] = ['term' => $term, 'relay' => $is_relay, 'gender' => $is_relay ? $g : ''];
        }

        if (empty($event_terms)) wp_send_json_error('Geen events gevonden in programma.');

        // Build event_id → program-entry-key map so we can auto-fill finale times
        // while fetching heats (no extra API calls needed).
        $processed_program = STA_Program::get_program();
        $event_bc_key   = []; // event_id → key of bc_finals program entry
        $event_a_key    = []; // event_id → key of a_finals program entry
        $event_solo_key = []; // event_id → key for single-block finals / semis
        foreach ($processed_program as $pe) {
            $eid  = $pe['event_id'] ?? '';
            if (!$eid) continue;
            $slug = $pe['session_slug'] ?? '';
            $k    = STA_Program::event_key($pe);
            if ($slug === 'bc_finals')                         $event_bc_key[$eid]   = $k;
            elseif ($slug === 'a_finals')                      $event_a_key[$eid]    = $k;
            elseif (in_array($slug, ['finals','semis','heats'])) $event_solo_key[$eid] = $k;
        }
        $program_times = [];  // auto-generated times (bc/a finals from heat data) → sta_program_times_auto
        $times_changed = false;

        // Fetch athletes.json once to build clubid → [post_ids] map for relay matching
        $athletes_json = self::api_get(self::API_BASE . $meet_id . '/athletes.json') ?: [];
        $club_athlete_map   = []; // clubid → [post_id, ...] (all genders)
        $club_athlete_map_g = []; // clubid → gender → [post_id, ...]

        // Build name → post_id lookup from WordPress athletes
        global $wpdb;
        $wp_athlete_posts = get_posts([
            'post_type'      => 'athlete',
            'post_status'    => ['publish','draft'],
            'numberposts'    => -1,
            'fields'         => 'ids',
        ]);
        $name_map  = []; // normalized name → post_id
        $swrid_map = []; // swrid string → post_id
        $mid_map   = []; // meet athlete id → post_id
        foreach ($wp_athlete_posts as $pid) {
            $name_map[strtolower(trim(get_the_title($pid)))] = $pid;
        }
        $rows = $wpdb->get_results(
            "SELECT meta_key, meta_value, post_id FROM {$wpdb->postmeta}
             WHERE meta_key IN ('sta_swrid','sta_meet_athlete_id')
               AND post_id IN (" . implode(',', array_map('intval', $wp_athlete_posts)) . ")"
        );
        foreach ($rows as $r) {
            if ($r->meta_key === 'sta_swrid')           $swrid_map[$r->meta_value] = (int)$r->post_id;
            if ($r->meta_key === 'sta_meet_athlete_id') $mid_map[$r->meta_value]   = (int)$r->post_id;
        }

        // Build clubid → gender → [post_ids] using athletes.json swrid/id → post_id
        foreach ($athletes_json as $a) {
            $clubid = (string)($a['clubid'] ?? '');
            if (!$clubid) continue;
            $swrid = (string)($a['swrid'] ?? '');
            $amid  = (string)($a['id']    ?? '');
            $pid   = null;
            if ($swrid && isset($swrid_map[$swrid])) $pid = $swrid_map[$swrid];
            elseif ($amid && isset($mid_map[$amid]))  $pid = $mid_map[$amid];
            if ($pid) {
                $club_athlete_map[$clubid][] = $pid; // all genders (kept for BC)
                $ag = ($a['gender'] ?? '') === '2' ? 'f' : 'm';
                $club_athlete_map_g[$clubid][$ag][] = $pid;
            }
        }

        // Ensure all afstand terms exist; collect term_name → term_id + slug
        $term_ids   = [];
        $term_slugs = [];
        foreach ($event_terms as $info) {
            $tname = $info['term'];
            if (isset($term_ids[$tname])) continue;
            $existing = get_term_by('name', $tname, 'afstand');
            if ($existing) {
                $term_ids[$tname]   = $existing->term_id;
                $term_slugs[$tname] = $existing->slug;
            } else {
                $created = wp_insert_term($tname, 'afstand');
                if (!is_wp_error($created)) {
                    $term_ids[$tname]   = $created['term_id'];
                    $t = get_term($created['term_id'], 'afstand');
                    $term_slugs[$tname] = $t ? $t->slug : sanitize_title($tname);
                }
            }
        }

        // Clear existing afstand terms from all athletes
        foreach ($wp_athlete_posts as $pid) {
            wp_set_object_terms($pid, [], 'afstand');
        }

        // Fetch heats per event and link athletes
        $linked           = 0;
        $not_found        = 0;
        $processed_events = 0;

        foreach ($event_terms as $event_id => $info) {
            $tname     = $info['term'];
            $term_id   = $term_ids[$tname] ?? null;
            $term_slug = $term_slugs[$tname] ?? sanitize_title($tname);
            if (!$term_id) continue;

            if ($info['relay']) {
                // Relay: heats entries have club names, not athlete names.
                // Link all athletes from clubs that have a relay entry in this event.
                $data = self::api_get(self::API_BASE . $meet_id . '/heats/' . $event_id . '.json');
                if (!$data || empty($data['heats'])) continue;
                $processed_events++;

                $relay_gender = $info['gender'] ?? '';
                $linked_pids  = []; // avoid duplicate term assignments per event
                foreach ($data['heats'] as $heat) {
                    foreach (($heat['entries'] ?? []) as $entry) {
                        $clubid = (string)($entry['clubid'] ?? '');
                        if (!$clubid) continue;
                        // Filter club athletes by relay gender ('x' = mixed → include all)
                        if ( $relay_gender !== 'x' && $relay_gender !== '' && isset($club_athlete_map_g[$clubid][$relay_gender]) ) {
                            $pids = $club_athlete_map_g[$clubid][$relay_gender];
                        } elseif ( isset($club_athlete_map[$clubid]) ) {
                            $pids = $club_athlete_map[$clubid];
                        } else {
                            continue;
                        }
                        foreach ($pids as $pid) {
                            if (isset($linked_pids[$pid])) continue;
                            wp_add_object_terms($pid, $term_id, 'afstand');
                            $linked_pids[$pid] = true;
                            $linked++;
                        }
                    }
                }

                // Extract heat time for relay (relay only has A finals block)
                self::extract_heat_times($data['heats'], $event_id, $event_bc_key, $event_a_key, $event_solo_key, $program_times, $times_changed);

            } else {
                // Individual: try entries/{eventId}.json first (works before heats are drawn),
                // fall back to heats/{eventId}.json (works once startlists are published).
                $entries_flat = [];
                $entries_data = self::api_get(self::API_BASE . $meet_id . '/entries/' . $event_id . '.json');
                if ($entries_data && !empty($entries_data['entries'])) {
                    $entries_flat = $entries_data['entries'];
                } else {
                    $heats_data = self::api_get(self::API_BASE . $meet_id . '/heats/' . $event_id . '.json');
                    if ($heats_data && !empty($heats_data['heats'])) {
                        foreach ($heats_data['heats'] as $heat) {
                            foreach (($heat['entries'] ?? []) as $e) {
                                $entries_flat[] = $e;
                            }
                        }
                        // Extract heat times from heats data
                        self::extract_heat_times($heats_data['heats'], $event_id, $event_bc_key, $event_a_key, $event_solo_key, $program_times, $times_changed);
                    }
                }

                if (empty($entries_flat)) continue;
                $processed_events++;

                foreach ($entries_flat as $entry) {
                    $post_id = null;

                    // entries/ uses 'swrid'; heats/ uses 'nametext' + 'id'
                    $swrid = (string)($entry['swrid'] ?? '');
                    if ($swrid && isset($swrid_map[$swrid])) $post_id = $swrid_map[$swrid];

                    if (!$post_id) {
                        $v = (string)($entry['athleteid'] ?? $entry['id'] ?? '');
                        if ($v && isset($mid_map[$v])) $post_id = $mid_map[$v];
                    }

                    if (!$post_id) {
                        $name = strtolower(trim($entry['nametext'] ?? ''));
                        if ($name && isset($name_map[$name])) $post_id = $name_map[$name];
                    }

                    if (!$post_id) { $not_found++; continue; }

                    wp_add_object_terms($post_id, $term_id, 'afstand');
                    $entrytime = trim($entry['entrytime'] ?? '');
                    if ($entrytime) update_post_meta($post_id, 'sta_entry_time_' . $term_slug, $entrytime);
                    $linked++;
                }
            }
        }

        if ($times_changed) {
            update_option('sta_program_times_auto', $program_times);
            // Remove auto-generated keys from manual overrides (migration: old code stored these in sta_program_times)
            $manual_times = get_option('sta_program_times', []);
            $changed = false;
            foreach (array_keys($program_times) as $key) {
                if (isset($manual_times[$key])) {
                    unset($manual_times[$key]);
                    $changed = true;
                }
            }
            if ($changed) update_option('sta_program_times', $manual_times);
        }

        wp_send_json_success([
            'events'    => $processed_events,
            'linked'    => $linked,
            'not_found' => $not_found,
            'terms'     => count($term_ids),
        ]);
    }

    // ── AJAX: import results ─────────────────────────────────────────────────
    public static function ajax_import_results(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $meet_id = sanitize_text_field($_POST['meet_id'] ?? '');
        if (!$meet_id) wp_send_json_error('Geen meet ID.');

        $program = STA_Program::get_program();
        if (empty($program)) wp_send_json_error('Importeer eerst het programma.');

        // Fetch agegroup name lookup
        $agegroups_raw  = self::api_get(self::API_BASE . $meet_id . '/agegroups.json') ?: [];
        $agegroup_names = []; // string id => name
        foreach ($agegroups_raw as $agid => $ag) {
            $id = (string)($ag['id'] ?? $agid);
            $agegroup_names[$id] = $ag['name'] ?? $id;
        }

        // Collect unique event_ids across all sessions (heats, semis, finals), including U18 siblings
        $all_events = [];
        foreach ($program as $pe) {
            $eids = !empty($pe['event_ids']) ? $pe['event_ids'] : (!empty($pe['event_id']) ? [$pe['event_id']] : []);
            foreach ($eids as $eid) {
                $all_events[$eid] = true;
            }
        }

        $results_store = get_option('sta_event_results', []);
        $fetched = 0;

        foreach (array_keys($all_events) as $event_id) {
            $data = self::api_get(self::API_BASE . $meet_id . '/results/' . $event_id . '.json');
            if (!$data || empty($data['agegroups'])) continue;
            $fetched++;

            $by_ag = [];
            foreach ($data['agegroups'] as $ag) {
                $agid      = (string)($ag['id'] ?? '');
                $scoretype = $ag['scoretype'] ?? 'points';
                $entries   = [];
                foreach (($ag['results'] ?? []) as $entry) {
                    $entries[] = [
                        'place'        => (int)($entry['place'] ?? 0),
                        'nametext'     => $entry['nametext'] ?? '',
                        'swimtime'     => $entry['swimtime'] ?? '',
                        'nation'       => strtolower($entry['nation'] ?? ''),
                        'clubname'     => $entry['clubname'] ?? '',
                        'qualcode'     => $entry['qualcode'] ?? '',
                        'reactiontime' => $entry['reactiontime'] ?? '',
                        'heatkey'        => $entry['heatinfo']['key'] ?? '',
                        'heatcode'       => $entry['heatinfo']['code'] ?? '',
                        'medal'          => (int)($entry['medal'] ?? 0),
                        'diff'           => $entry['diff'] ?? '',
                        'handicap'       => $entry['handicap'] ?? '',
                        'points'         => $entry['points'] ?? '',
                        'handicappoints' => $entry['handicappoints'] ?? '',
                        'gender'         => $entry['gender'] ?? '',
                        'swrid'          => (string)($entry['swrid']     ?? ''),
                        'athleteid'      => (string)($entry['athleteid'] ?? ''),
                        'athletes'       => array_map(fn($a) => [
                            'name' => $a['fullname'] ?? '',
                            'pos'  => (int)($a['position'] ?? 0),
                            'time' => $a['swimtime'] ?? '',
                        ], $entry['athletes'] ?? []),
                    ];
                }
                usort($entries, fn($a, $b) => ($a['place'] > 0 ? $a['place'] : 9999) - ($b['place'] > 0 ? $b['place'] : 9999));
                $by_ag[$agid] = [
                    'name'      => $agegroup_names[$agid] ?? $agid,
                    'scoretype' => $scoretype,
                    'entries'   => $entries,
                ];
            }
            $results_store[$event_id] = $by_ag;
        }

        update_option('sta_event_results', $results_store);

        wp_send_json_success([
            'fetched' => $fetched,
            'total'   => count($all_events),
        ]);
    }

    // ── AJAX: live refresh (public, no auth required) ────────────────────────
    // Called by client-side JS when buildnr changes. Fetches fresh results from
    // SwimRankings for the supplied event IDs and updates sta_event_results.
    public static function ajax_live_refresh(): void {
        $meet_id   = sanitize_text_field($_POST['meet_id']   ?? '');
        $event_ids = array_map('sanitize_text_field', (array)($_POST['event_ids'] ?? []));

        if ( ! $meet_id || empty($event_ids) ) {
            wp_send_json_error('Missing params');
        }

        // Allow only event IDs that exist in the current program (prevents fishing)
        if ( class_exists('STA_Program') ) {
            $valid = [];
            foreach ( STA_Program::get_program() as $pe ) {
                $eids_valid = ! empty($pe['event_ids']) ? $pe['event_ids'] : array_filter([$pe['event_id'] ?? '']);
                foreach ( $eids_valid as $eid ) { $valid[$eid] = true; }
            }
            $event_ids = array_values( array_filter($event_ids, fn($e) => isset($valid[$e])) );
        }
        if ( empty($event_ids) ) wp_send_json_error('No valid event IDs');

        // Agegroup name map
        $ag_raw = self::api_get(self::API_BASE . $meet_id . '/agegroups.json') ?: [];
        $ag_names = [];
        foreach ( $ag_raw as $agid => $ag ) {
            $ag_names[(string)($ag['id'] ?? $agid)] = $ag['name'] ?? '';
        }

        $store   = get_option('sta_event_results', []);
        $changed = false;

        foreach ( $event_ids as $eid ) {
            $data = self::api_get(self::API_BASE . $meet_id . '/results/' . $eid . '.json');
            if ( ! $data || empty($data['agegroups']) ) continue;

            $by_ag = [];
            foreach ( $data['agegroups'] as $ag ) {
                $agid    = (string)($ag['id'] ?? '');
                $entries = [];
                foreach ( ($ag['results'] ?? []) as $entry ) {
                    $entries[] = [
                        'place'          => (int)($entry['place'] ?? 0),
                        'nametext'       => $entry['nametext'] ?? '',
                        'swimtime'       => $entry['swimtime'] ?? '',
                        'nation'         => strtolower($entry['nation'] ?? ''),
                        'clubname'       => $entry['clubname'] ?? '',
                        'qualcode'       => $entry['qualcode'] ?? '',
                        'reactiontime'   => $entry['reactiontime'] ?? '',
                        'heatkey'        => $entry['heatinfo']['key'] ?? '',
                        'heatcode'       => $entry['heatinfo']['code'] ?? '',
                        'medal'          => (int)($entry['medal'] ?? 0),
                        'diff'           => $entry['diff'] ?? '',
                        'handicap'       => $entry['handicap'] ?? '',
                        'points'         => $entry['points'] ?? '',
                        'handicappoints' => $entry['handicappoints'] ?? '',
                        'gender'         => $entry['gender'] ?? '',
                        'swrid'          => (string)($entry['swrid']     ?? ''),
                        'athleteid'      => (string)($entry['athleteid'] ?? ''),
                        'athletes'       => array_map(fn($a) => [
                            'name' => $a['fullname'] ?? '',
                            'pos'  => (int)($a['position'] ?? 0),
                            'time' => $a['swimtime'] ?? '',
                        ], $entry['athletes'] ?? []),
                    ];
                }
                usort($entries, fn($a,$b) => ($a['place'] > 0 ? $a['place'] : 9999) - ($b['place'] > 0 ? $b['place'] : 9999));
                $by_ag[$agid] = [
                    'name'      => $ag_names[$agid] ?? $agid,
                    'scoretype' => $ag['scoretype'] ?? 'points',
                    'entries'   => $entries,
                ];
            }
            $store[$eid] = $by_ag;
            $changed = true;
        }

        if ($changed) update_option('sta_event_results', $store);

        // Render updated results HTML for the requested term (avoids page-fetch + cache issues)
        $term_slug = sanitize_text_field($_POST['term_slug'] ?? '');
        $html      = '';
        if ($term_slug && class_exists('STA_Results_Renderer')) {
            $term = get_term_by('slug', $term_slug, 'afstand');
            if ($term) $html = STA_Results_Renderer::render($term);
        }

        wp_send_json_success(['updated' => count($event_ids), 'html' => $html]);
    }

    // ── AJAX: fetch club logos from SwimRankings clubs endpoint ─────────────
    public static function ajax_fetch_logos(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $meet_id = get_option('sta_swimrankings_meet_id', '');
        if (!$meet_id) wp_send_json_error('Geen meet gekoppeld.');

        $clubs = self::api_get(self::API_BASE . $meet_id . '/clubs.json');
        if (empty($clubs) || !is_array($clubs)) wp_send_json_error('Geen clubs gevonden.');

        $logo_map = [];
        foreach ($clubs as $club) {
            $name = $club['name'] ?? '';
            if ($name) {
                $logo_map[$name] = 'https://yannz.nl/logo/logo/' . rawurlencode(strtoupper($name)) . '.PNG';
            }
        }

        update_option('sta_club_logos', $logo_map);
        wp_send_json_success(['count' => count($logo_map)]);
    }

    // ── AJAX: clear stored results ───────────────────────────────────────────
    public static function ajax_clear_results(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        delete_option('sta_event_results');
        wp_send_json_success();
    }

    public static function ajax_save_startlist_autoopen(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        $mins = max( 0, min( 120, (int)( $_POST['mins'] ?? 30 ) ) );
        update_option( 'sta_startlist_autoopen_mins', $mins );
        wp_send_json_success();
    }

    public static function ajax_clear_heats_cache(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_sta_heats_startlijst_%'
                OR option_name LIKE '_transient_timeout_sta_heats_startlijst_%'
                OR option_name LIKE '_transient_sta_relay_heats_%'
                OR option_name LIKE '_transient_timeout_sta_relay_heats_%'"
        );
        wp_send_json_success();
    }

    // ── AJAX: delete all athletes ────────────────────────────────────────────
    public static function ajax_delete_all_athletes(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $ids = get_posts([
            'post_type'      => 'athlete',
            'post_status'    => ['publish', 'draft', 'trash'],
            'numberposts'    => -1,
            'fields'         => 'ids',
        ]);

        foreach ($ids as $id) {
            wp_delete_post($id, true); // true = permanent, skip trash
        }

        wp_send_json_success(['deleted' => count($ids)]);
    }

    // ── AJAX: import athletes ─────────────────────────────────────────────────
    public static function ajax_import_athletes(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        $meet_id = sanitize_text_field($_POST['meet_id'] ?? '');
        if (!$meet_id) wp_send_json_error('Geen meet ID.');

        $athletes  = self::api_get(self::API_BASE . $meet_id . '/athletes.json');
        $clubs_raw = self::api_get(self::API_BASE . $meet_id . '/clubs.json');
        if (!$athletes) wp_send_json_error('Atleten ophalen mislukt.');

        // Build club lookup by id
        $clubs = [];
        if ($clubs_raw) {
            foreach ($clubs_raw as $c) {
                if (isset($c['id'], $c['name'])) {
                    $clubs[$c['id']] = $c['name'];
                }
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($athletes as $a) {
            if (empty($a['swrid']) || empty($a['fullname'])) {
                $skipped++;
                continue;
            }

            $swrid  = sanitize_text_field($a['swrid']);
            $name   = sanitize_text_field($a['fullname']);
            $gender = ($a['gender'] ?? '') === '2' ? 'f' : 'm';
            $nation_3 = strtoupper($a['nation'] ?? '');
            $nation = self::NATION_MAP[$nation_3] ?? $nation_3;
            $club   = isset($a['clubid']) && isset($clubs[$a['clubid']])
                      ? $clubs[$a['clubid']]
                      : sanitize_text_field($a['clubname'] ?? '');
            $yob    = absint($a['yob'] ?? 0);
            $meet_athlete_id = sanitize_text_field($a['id'] ?? '');

            // Strip "HPC - " prefix (bijv. "HPC - PSV" → "PSV")
            if (preg_match('/^HPC\s*[-–]\s*(.+)$/u', $club, $hpc_m)) {
                $club = trim($hpc_m[1]);
            }

            // Match existing by swrid
            global $wpdb;
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = 'sta_swrid' AND pm.meta_value = %s
                   AND p.post_type = 'athlete'
                   AND p.post_status IN ('publish','draft')
                 LIMIT 1",
                $swrid
            ));

            // Fallback: match by naam + geslacht (HPC-dubbelganger samenvoegen)
            if (!$existing_id) {
                $existing_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT p.ID FROM {$wpdb->posts} p
                     JOIN {$wpdb->postmeta} pg ON pg.post_id = p.ID AND pg.meta_key = 'sta_gender' AND pg.meta_value = %s
                     WHERE p.post_type = 'athlete'
                       AND p.post_status IN ('publish','draft')
                       AND p.post_title = %s
                     LIMIT 1",
                    $gender, $name
                ));
            }

            $is_merge = false;
            if ($existing_id) {
                wp_update_post(['ID' => (int)$existing_id, 'post_title' => $name]);
                $post_id  = (int)$existing_id;
                $is_merge = (get_post_meta($post_id, 'sta_swrid', true) !== $swrid);
                $updated++;
            } else {
                $post_id = wp_insert_post([
                    'post_type'   => 'athlete',
                    'post_status' => 'publish',
                    'post_title'  => $name,
                ]);
                if (is_wp_error($post_id)) {
                    $skipped++;
                    continue;
                }
                $created++;
            }

            update_post_meta($post_id, 'sta_swrid',   $swrid);
            update_post_meta($post_id, 'sta_gender',  $gender);
            update_post_meta($post_id, 'sta_country', $nation);
            update_post_meta($post_id, 'sta_club',    sanitize_text_field($club));
            update_post_meta($post_id, 'sta_yob',     $yob);

            // Sla meet-athlete-id op; bij samenvoegen extra waarde toevoegen zodat beide IDs vindbaar zijn
            if ($is_merge) {
                $existing_ids = get_post_meta($post_id, 'sta_meet_athlete_id');
                if (!in_array($meet_athlete_id, $existing_ids, true)) {
                    add_post_meta($post_id, 'sta_meet_athlete_id', $meet_athlete_id);
                }
            } else {
                // Wis alle bestaande en zet één nieuwe primaire waarde
                delete_post_meta($post_id, 'sta_meet_athlete_id');
                add_post_meta($post_id, 'sta_meet_athlete_id', $meet_athlete_id);
            }

            if ($club) {
                wp_set_object_terms($post_id, $club, 'sta_club', false);
            }
        }

        wp_send_json_success([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    // ── AJAX: merge duplicate athletes ───────────────────────────────────────
    public static function ajax_merge_duplicates(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT p.post_title AS name, pm.meta_value AS gender, GROUP_CONCAT(p.ID ORDER BY p.ID ASC) AS ids, COUNT(*) AS cnt
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'sta_gender'
             WHERE p.post_type = 'athlete' AND p.post_status IN ('publish','draft')
             GROUP BY p.post_title, pm.meta_value
             HAVING cnt > 1",
            ARRAY_A
        );

        $merged  = 0;
        $deleted = 0;

        foreach ($rows as $row) {
            $ids = array_map('intval', explode(',', $row['ids']));

            $keeper = null;
            foreach ($ids as $id) {
                if (has_post_thumbnail($id)) { $keeper = $id; break; }
            }
            if (!$keeper) {
                foreach ($ids as $id) {
                    $p = get_post($id);
                    if ($p && trim($p->post_content) !== '') { $keeper = $id; break; }
                }
            }
            if (!$keeper) $keeper = $ids[0];

            $all_mid_values = [];
            foreach ($ids as $id) {
                $vals = get_post_meta($id, 'sta_meet_athlete_id');
                foreach ($vals as $v) {
                    if ($v !== '' && !in_array($v, $all_mid_values, true)) {
                        $all_mid_values[] = $v;
                    }
                }
            }

            delete_post_meta($keeper, 'sta_meet_athlete_id');
            foreach ($all_mid_values as $v) {
                add_post_meta($keeper, 'sta_meet_athlete_id', $v);
            }

            foreach ($ids as $id) {
                if ($id === $keeper) continue;
                wp_delete_post($id, true);
                $deleted++;
            }
            $merged++;
        }

        wp_send_json_success(['merged' => $merged, 'deleted' => $deleted]);
    }

    // ── AJAX: unlink relay events from athletes ───────────────────────────────
    public static function ajax_unlink_relays(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');

        // Haal alle afstand-termen op die estafettes zijn (naam bevat '\d+ x \d+')
        $all_terms = get_terms(['taxonomy' => 'afstand', 'hide_empty' => false]);
        if (is_wp_error($all_terms)) wp_send_json_error('Geen afstand-taxonomie gevonden.');

        $relay_term_ids = [];
        foreach ($all_terms as $term) {
            if (preg_match('/\d+\s*[xX]\s*\d+/', $term->name)) {
                $relay_term_ids[] = $term->term_id;
            }
        }

        if (empty($relay_term_ids)) {
            wp_send_json_success(['removed' => 0, 'athletes' => 0]);
        }

        // Zoek alle atleten die aan een estafette-term gekoppeld zijn
        $athletes = get_posts([
            'post_type'      => 'athlete',
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => 'afstand',
                'field'    => 'term_id',
                'terms'    => $relay_term_ids,
                'operator' => 'IN',
            ]],
        ]);

        $athletes_count = 0;
        foreach ($athletes as $post_id) {
            wp_remove_object_terms((int) $post_id, $relay_term_ids, 'afstand');
            $athletes_count++;
        }

        wp_send_json_success(['removed' => count($relay_term_ids), 'athletes' => $athletes_count]);
    }

    // ── AJAX: import program ──────────────────────────────────────────────────
    public static function ajax_import_program(): void {
        check_ajax_referer('sta_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Geen toegang.');
        $meet_id = sanitize_text_field($_POST['meet_id'] ?? '');
        if (!$meet_id) wp_send_json_error('Geen meet ID.');
        $result = self::do_import_program($meet_id);
        if (isset($result['error'])) wp_send_json_error($result['error']);
        wp_send_json_success($result);
    }

    // ── Render ────────────────────────────────────────────────────────────────
    public static function render(): void {
        $current_meet_id = get_option('sta_swimrankings_meet_id', '');
        $current_main    = $current_meet_id
                           ? self::api_get(self::API_BASE . $current_meet_id . '/main.json')
                           : null;

        // Fetch meets list for dropdown
        $meets_json = self::api_get(self::MEETS_URL);
        $meetgroups = $meets_json['meetgroups'] ?? [];

        $presets       = self::get_presets();
        $active_key    = get_option('sta_active_preset', array_key_first($presets));
        $active_preset = $presets[$active_key] ?? self::default_preset();
        $nonce = wp_create_nonce('sta_nonce');
        ?>
        <div class="wrap">
            <h1>SwimRankings import</h1>

            <!-- ── Preset manager ─────────────────────────────────────────── -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:24px;">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <strong style="font-size:15px;">Preset:</strong>
                    <select id="sta-preset-select" style="font-size:14px;padding:4px 8px;border-radius:4px;border:1px solid #ccc;">
                        <?php foreach ($presets as $pk => $pv) : ?>
                            <option value="<?php echo esc_attr($pk); ?>" <?php selected($pk, $active_key); ?>>
                                <?php echo esc_html($pv['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button" id="sta-preset-activate" style="border-radius:20px;">Activeer</button>
                    <button class="button" id="sta-preset-copy" style="border-radius:20px;">Kopieer als nieuw</button>
                    <button class="button" id="sta-preset-delete" style="border-radius:20px;color:#c00;border-color:#c00;">Verwijder</button>
                    <span id="sta-preset-msg" style="font-size:13px;color:#46b450;"></span>
                </div>

                <div id="sta-preset-form" style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
                    <input type="hidden" id="sta-preset-key" value="<?php echo esc_attr($active_key); ?>">

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">Naam</label>
                        <input type="text" id="sta-pf-name" value="<?php echo esc_attr($active_preset['name']); ?>"
                               style="width:300px;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">Sessienaam → series (regex)</label>
                        <input type="text" id="sta-pf-heats" value="<?php echo esc_attr($active_preset['session_heats']); ?>"
                               style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">Sessienaam → halve finale (regex)</label>
                        <input type="text" id="sta-pf-semis" value="<?php echo esc_attr($active_preset['session_semis']); ?>"
                               style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">Sessienaam → finale (regex)</label>
                        <input type="text" id="sta-pf-finals" value="<?php echo esc_attr($active_preset['session_finals']); ?>"
                               style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">"Senior" patroon — geen leeftijdsbadge (regex)</label>
                        <input type="text" id="sta-pf-senior" value="<?php echo esc_attr($active_preset['senior_pattern']); ?>"
                               style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">Para-sleutelwoord (regex, bijv. "para")</label>
                        <input type="text" id="sta-pf-para" value="<?php echo esc_attr($active_preset['para_keyword'] ?? 'para'); ?>"
                               style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:2px;">Swim-off round-codes uitsluiten (kommagescheiden)</label>
                        <input type="text" id="sta-pf-excl-rounds" value="<?php echo esc_attr(implode(',', $active_preset['heats_exclude_rounds'] ?? [])); ?>"
                               placeholder="bijv. 3"
                               style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:6px;">Badge-regels</label>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <label style="font-size:13px;display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="sta-pf-age-in-heats" <?php checked(!empty($active_preset['show_age_in_heats'])); ?>>
                                Leeftijdsbadge wél tonen in series (ook als senioren meerijden)
                            </label>
                            <label style="font-size:13px;display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="sta-pf-relay-age" <?php checked($active_preset['relay_separate_by_age'] ?? true); ?>>
                                Onder 18-estafettes op eigen pagina (aparte bucket per leeftijd)
                            </label>
                            <label style="font-size:13px;display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="sta-pf-u18-shared" <?php checked(!empty($active_preset['u18_shared_heats'])); ?>>
                                U18 zwemt in gedeelde series met senioren (NK sprint): event_ids & live-venster samenvoegen
                            </label>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:6px;">Finale-splitsing (B&C / A)</label>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <label style="font-size:13px;display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="sta-pf-split-finals" <?php checked(!empty($active_preset['split_finals_rounds'])); ?>>
                                Splits finalesessie in B&C-blok en A-blok
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;" id="sta-pf-split-details" <?php echo empty($active_preset['split_finals_rounds']) ? 'style="opacity:.4;pointer-events:none;"' : ''; ?>>
                                <div>
                                    <label style="font-size:11px;color:#777;display:block;margin-bottom:2px;">Round-code B&C (bijv. "9")</label>
                                    <input type="text" id="sta-pf-bc-round" value="<?php echo esc_attr($active_preset['bc_finals_round'] ?? '9'); ?>"
                                           style="width:100%;padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                                </div>
                                <div>
                                    <label style="font-size:11px;color:#777;display:block;margin-bottom:2px;">Label B&C-blok</label>
                                    <input type="text" id="sta-pf-bc-label" value="<?php echo esc_attr($active_preset['bc_finals_label'] ?? 'B & C Finales'); ?>"
                                           style="width:100%;padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                                </div>
                                <div>
                                    <label style="font-size:11px;color:#777;display:block;margin-bottom:2px;">Label A-blok</label>
                                    <input type="text" id="sta-pf-a-label" value="<?php echo esc_attr($active_preset['a_finals_label'] ?? 'A Finales'); ?>"
                                           style="width:100%;padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Sessie-label overrides</label>
                        <p style="font-size:11px;color:#888;margin:0 0 6px;">Één per regel: <code>API-sessienaam|Gewenst label</code><br>Bijv.: <code>NJK lange afstanden|NJK Lange Afstanden</code></p>
                        <?php
                        $overrides = $active_preset['session_label_overrides'] ?? [];
                        $overrides_text = implode("\n", array_map(fn($k,$v) => "$k|$v", array_keys($overrides), $overrides));
                        ?>
                        <textarea id="sta-pf-label-overrides" rows="4"
                                  style="width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:12px;font-family:monospace;box-sizing:border-box;"><?php echo esc_textarea($overrides_text); ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:6px;">Estafette labels</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;">
                            <label style="font-size:13px;">♀ <input type="text" id="sta-pf-rf" value="<?php echo esc_attr($active_preset['relay_f']); ?>"
                                style="width:90px;padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></label>
                            <label style="font-size:13px;">♂ <input type="text" id="sta-pf-rm" value="<?php echo esc_attr($active_preset['relay_m']); ?>"
                                style="width:90px;padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></label>
                            <label style="font-size:13px;">♀♂ <input type="text" id="sta-pf-rx" value="<?php echo esc_attr($active_preset['relay_x']); ?>"
                                style="width:90px;padding:4px 7px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></label>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;margin-top:4px;">
                        <button class="button button-primary" id="sta-preset-save" style="border-radius:20px;">Opslaan</button>
                    </div>
                </div>
            </div>
            <!-- ── /Preset manager ────────────────────────────────────────── -->

            <?php if ($current_meet_id && $current_main) : ?>
            <div style="background:#edfaee;border:1px solid #46b450;border-radius:8px;padding:12px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
                <span style="font-size:18px;color:#46b450;">&#10003;</span>
                <div>
                    <strong>Gekoppeld meet:</strong>
                    <?php echo esc_html($current_main['name'] ?? $current_meet_id); ?>
                    <?php if (!empty($current_main['city'])) echo ' &mdash; ' . esc_html($current_main['city']); ?>
                    <?php if (!empty($current_main['startdate'])) echo ' (' . esc_html($current_main['startdate']) . ')'; ?>
                    &nbsp;&mdash;&nbsp;ID: <code><?php echo esc_html($current_meet_id); ?></code>
                </div>
            </div>
            <?php elseif ($current_meet_id) : ?>
            <div style="background:#fff8e5;border:1px solid #f0b849;border-radius:8px;padding:12px 18px;margin-bottom:24px;">
                Meet ID opgeslagen (<code><?php echo esc_html($current_meet_id); ?></code>), maar kon meet-info niet ophalen.
            </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                <button id="sta-delete-athletes" class="button" style="border-color:#c00;color:#c00;border-radius:20px;">
                    Alle atleten wissen
                </button>
                <div id="sta-delete-result" style="margin-left:12px;font-size:13px;line-height:30px;"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

                <!-- Meet selectie -->
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                    <h2 style="margin-top:0;font-size:16px;">1. Meet koppelen</h2>

                    <?php if (empty($meetgroups)) : ?>
                    <p style="color:#c00;">Kon meet-lijst niet ophalen van SwimRankings. Controleer de verbinding.</p>
                    <?php else : ?>

                    <label style="display:block;margin-bottom:4px;font-weight:600;font-size:13px;">Zoek op naam of land</label>
                    <input type="text" id="sta-meet-search" placeholder="Bijv. NK, Dutch, NED&#8230;"
                           style="width:100%;padding:6px 10px;border:1px solid #ccc;border-radius:4px;font-size:14px;margin-bottom:8px;box-sizing:border-box;">

                    <select id="sta-meet-select" size="14"
                            style="width:100%;font-size:13px;border:1px solid #ccc;border-radius:4px;margin-bottom:10px;">
                        <?php foreach ($meetgroups as $group) :
                            foreach ($group['meets'] as $meet) :
                                $selected = selected($current_meet_id, $meet['id'], false);
                                $label    = '[' . $group['code'] . '] ' . $meet['name'] . ' &ndash; ' . $meet['city'] . ' (' . substr($meet['startdate'], 0, 10) . ')';
                                $search   = strtolower($meet['name'] . ' ' . $meet['city'] . ' ' . $group['code']);
                        ?>
                            <option value="<?php echo esc_attr($meet['id']); ?>"
                                    data-search="<?php echo esc_attr($search); ?>"
                                    <?php echo $selected; ?>>
                                <?php echo esc_html('[' . $group['code'] . '] ' . $meet['name'] . ' - ' . $meet['city'] . ' (' . substr($meet['startdate'], 0, 10) . ')'); ?>
                            </option>
                        <?php endforeach; endforeach; ?>
                    </select>

                    <div id="sta-meet-info" style="font-size:13px;color:#555;min-height:36px;margin-bottom:10px;line-height:1.5;"></div>

                    <button class="button button-primary" id="sta-link-meet" style="border-radius:20px;">
                        Meet koppelen
                    </button>

                    <?php endif; ?>
                </div>

                <!-- Import acties -->
                <div>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h2 style="margin-top:0;font-size:16px;">2. Programma importeren</h2>
                        <p style="font-size:13px;color:#555;margin-top:0;">
                            Importeert sessions, events, afstanden, slagen, rondes en tijden.
                            Wordt opgeslagen als WordPress optie <code>sta_swimrankings_program</code>.
                        </p>
                        <p style="font-size:12px;color:#888;margin-top:-8px;">
                            Let op: overschrijft het bestaande programma.
                        </p>
                        <button class="button button-primary" id="sta-import-program"
                                style="border-radius:20px;"
                                <?php echo !$current_meet_id ? 'disabled' : ''; ?>>
                            Programma importeren
                        </button>
                        <div id="sta-program-result" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
                    </div>

                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h2 style="margin-top:0;font-size:16px;">3. Club logo's ophalen</h2>
                        <p style="font-size:13px;color:#555;margin-top:0;">
                            Haalt de lijst van deelnemende verenigingen op uit het gekoppelde meet en slaat de logo-URL's op.
                            Logo's worden weergegeven naast de clubnaam in de uitslagen.
                        </p>
                        <p style="font-size:12px;color:#888;margin-top:-8px;">
                            Logo's zijn afkomstig van yannz.nl en worden live ingeladen — geen opslag op de server.
                        </p>
                        <button class="button button-primary" id="sta-fetch-logos"
                                style="border-radius:20px;"
                                <?php echo !$current_meet_id ? 'disabled' : ''; ?>>
                            Club logo's ophalen
                        </button>
                        <div id="sta-logos-result" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
                    </div>

                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h2 style="margin-top:0;font-size:16px;">4. Atleten importeren</h2>
                        <p style="font-size:13px;color:#555;margin-top:0;">
                            Haalt alle atleten op uit het gekoppelde meet.
                            Bestaande atleten worden gematcht op <code>swrid</code> en bijgewerkt.
                            Nieuwe atleten worden aangemaakt als WordPress post.
                        </p>
                        <p style="font-size:12px;color:#888;margin-top:-8px;">
                            Geeft: naam, geslacht, geboortejaar, land, club.
                        </p>
                        <button class="button button-primary" id="sta-import-athletes"
                                style="border-radius:20px;"
                                <?php echo !$current_meet_id ? 'disabled' : ''; ?>>
                            Atleten importeren
                        </button>
                        <div id="sta-athletes-result" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
                        <div style="margin-top:12px;">
                            <button class="button" id="sta-merge-duplicates" style="border-radius:20px;">
                                Dubbele atleten samenvoegen
                            </button>
                            <div id="sta-merge-result" style="margin-top:8px;font-size:13px;min-height:18px;"></div>
                        </div>
                    </div>

                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-top:16px;">
                        <h2 style="margin-top:0;font-size:16px;">5. Atleten koppelen aan events</h2>
                        <p style="font-size:13px;color:#555;margin-top:0;">
                            Haalt per event de inschrijvingen op en koppelt elke atleet aan de juiste afstand-taxonomie.
                            Voer dit uit na stap 3.
                        </p>
                        <p style="font-size:12px;color:#888;margin-top:-8px;">
                            Maakt ~<?php
                                $prog = get_option('sta_swimrankings_program', []);
                                $eids = array_unique(array_column($prog, 'event_id'));
                                echo count($eids);
                            ?> API-calls (één per event). Dit kan ~30 seconden duren.
                        </p>
                        <button class="button button-primary" id="sta-link-events"
                                style="border-radius:20px;"
                                <?php echo !$current_meet_id ? 'disabled' : ''; ?>>
                            Atleten koppelen aan events
                        </button>
                        <div id="sta-events-result" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
                        <div style="margin-top:12px;">
                            <button class="button" id="sta-unlink-relays" style="border-radius:20px;">
                                Estafettes ontkoppelen van atleten
                            </button>
                            <div id="sta-relays-result" style="margin-top:8px;font-size:13px;min-height:18px;"></div>
                        </div>
                    </div>

                    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-top:16px;">
                        <h2 style="margin-top:0;font-size:16px;">6. Uitslagen importeren</h2>
                        <p style="font-size:13px;color:#555;margin-top:0;">
                            Haalt per finale-event de uitslagen op en slaat ze op voor weergave op de afstand-pagina's.
                            Voer dit uit na afloop van de finale-sessies.
                        </p>
                        <p style="font-size:12px;color:#888;margin-top:-8px;">
                            Maakt één API-call per finale-event. Bestaande uitslagen worden bijgewerkt.
                        </p>
                        <button class="button button-primary" id="sta-import-results"
                                style="border-radius:20px;"
                                <?php echo !$current_meet_id ? 'disabled' : ''; ?>>
                            Uitslagen importeren
                        </button>
                        <button class="button" id="sta-clear-results"
                                style="border-radius:20px;margin-left:8px;">
                            Uitslagen wissen
                        </button>
                        <button class="button" id="sta-clear-heats-cache"
                                style="border-radius:20px;margin-left:8px;">
                            Wis startlijst cache
                        </button>
                        <div id="sta-results-result" style="margin-top:10px;font-size:13px;min-height:18px;"></div>
                    </div>
                </div>

                <div class="postbox" style="margin-top:16px;">
                    <div class="inside">
                        <h2 style="margin-top:0;font-size:16px;">7. Weergave-instellingen</h2>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="padding:8px 0;font-size:13px;width:260px;">
                                    Startlijst automatisch openen
                                </th>
                                <td style="padding:8px 0;">
                                    <label style="font-size:13px;">
                                        <input type="number" min="0" max="120" style="width:60px;"
                                               id="sta-startlist-autoopen"
                                               value="<?php echo (int)get_option('sta_startlist_autoopen_mins', 30); ?>">
                                        &nbsp;minuten vóór starttijd
                                    </label>
                                    <p style="font-size:12px;color:#888;margin:4px 0 0;">
                                        Klapt de startlijst automatisch open op de afstandspagina als het event binnen dit aantal minuten begint. Stel in op 0 om uit te schakelen.
                                    </p>
                                    <button type="button" class="button" id="sta-save-autoopen" style="margin-top:8px;border-radius:20px;">Opslaan</button>
                                    <span id="sta-autoopen-msg" style="font-size:13px;margin-left:8px;"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <script>
        jQuery(function($) {
            var nonce      = '<?php echo esc_js($nonce); ?>';
            var ajaxUrl    = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var meetId     = '<?php echo esc_js($current_meet_id); ?>';

            // ── Preset helpers ───────────────────────────────────────────────
            var presets    = <?php echo json_encode($presets); ?>;
            var $pMsg      = $('#sta-preset-msg');

            function showMsg(txt, ok) {
                $pMsg.text(txt).css('color', ok === false ? '#c00' : '#46b450').show();
                setTimeout(function() { $pMsg.fadeOut(); }, 3000);
            }

            function fillForm(preset) {
                $('#sta-pf-name').val(preset.name || '');
                $('#sta-pf-heats').val(preset.session_heats || '');
                $('#sta-pf-semis').val(preset.session_semis || '');
                $('#sta-pf-finals').val(preset.session_finals || '');
                $('#sta-pf-senior').val(preset.senior_pattern || '');
                $('#sta-pf-para').val(preset.para_keyword || 'para');
                $('#sta-pf-excl-rounds').val((preset.heats_exclude_rounds || []).join(','));
                $('#sta-pf-age-in-heats').prop('checked', !!preset.show_age_in_heats);
                $('#sta-pf-relay-age').prop('checked', preset.relay_separate_by_age !== false);
                $('#sta-pf-u18-shared').prop('checked', !!preset.u18_shared_heats);
                var splitOn = !!preset.split_finals_rounds;
                $('#sta-pf-split-finals').prop('checked', splitOn);
                $('#sta-pf-split-details').css({opacity: splitOn ? 1 : .4, 'pointer-events': splitOn ? '' : 'none'});
                $('#sta-pf-bc-round').val(preset.bc_finals_round || '9');
                $('#sta-pf-bc-label').val(preset.bc_finals_label || 'B & C Finales');
                $('#sta-pf-a-label').val(preset.a_finals_label  || 'A Finales');
                var overridesObj = preset.session_label_overrides || {};
                var overridesText = Object.entries(overridesObj).map(function(kv){ return kv[0]+'|'+kv[1]; }).join('\n');
                $('#sta-pf-label-overrides').val(overridesText);
                $('#sta-pf-rf').val(preset.relay_f || '');
                $('#sta-pf-rm').val(preset.relay_m || '');
                $('#sta-pf-rx').val(preset.relay_x || '');
            }

            $('#sta-preset-select').on('change', function() {
                var key = $(this).val();
                $('#sta-preset-key').val(key);
                if (presets[key]) fillForm(presets[key]);
            });

            $('#sta-pf-split-finals').on('change', function() {
                var on = $(this).is(':checked');
                $('#sta-pf-split-details').css({opacity: on ? 1 : .4, 'pointer-events': on ? '' : 'none'});
            });

            $('#sta-preset-activate').on('click', function() {
                var key = $('#sta-preset-select').val();
                $.post(ajaxUrl, { action: 'sta_sr_set_active_preset', nonce: nonce, preset_key: key }, function(r) {
                    if (r.success) showMsg('Geactiveerd ✓');
                    else showMsg(r.data || 'Fout', false);
                });
            });

            $('#sta-preset-save').on('click', function() {
                var key = $('#sta-preset-key').val();
                $.post(ajaxUrl, {
                    action:          'sta_sr_save_preset',
                    nonce:           nonce,
                    preset_key:      key,
                    name:            $('#sta-pf-name').val(),
                    session_heats:   $('#sta-pf-heats').val(),
                    session_semis:   $('#sta-pf-semis').val(),
                    session_finals:  $('#sta-pf-finals').val(),
                    senior_pattern:        $('#sta-pf-senior').val(),
                    para_keyword:          $('#sta-pf-para').val(),
                    heats_exclude_rounds:  $('#sta-pf-excl-rounds').val(),
                    show_age_in_heats:     $('#sta-pf-age-in-heats').is(':checked') ? '1' : '',
                    relay_separate_by_age: $('#sta-pf-relay-age').is(':checked') ? '1' : '',
                    u18_shared_heats:      $('#sta-pf-u18-shared').is(':checked') ? '1' : '',
                    split_finals_rounds:   $('#sta-pf-split-finals').is(':checked') ? '1' : '',
                    bc_finals_round:       $('#sta-pf-bc-round').val(),
                    bc_finals_label:       $('#sta-pf-bc-label').val(),
                    a_finals_label:           $('#sta-pf-a-label').val(),
                    session_label_overrides:  $('#sta-pf-label-overrides').val(),
                    relay_f:         $('#sta-pf-rf').val(),
                    relay_m:         $('#sta-pf-rm').val(),
                    relay_x:         $('#sta-pf-rx').val(),
                }, function(r) {
                    if (r.success) {
                        presets[key] = r.data.preset;
                        // Update select label
                        $('#sta-preset-select option[value="' + key + '"]').text(r.data.preset.name);
                        showMsg('Opgeslagen ✓');
                    } else {
                        showMsg(r.data || 'Fout', false);
                    }
                });
            });

            $('#sta-preset-copy').on('click', function() {
                var copyFrom = $('#sta-preset-select').val();
                var newName  = prompt('Naam voor het nieuwe preset:', (presets[copyFrom]?.name || '') + ' (kopie)');
                if (!newName) return;
                var newKey   = newName.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                $.post(ajaxUrl, {
                    action:    'sta_sr_save_preset',
                    nonce:     nonce,
                    is_new:    1,
                    copy_from: copyFrom,
                    new_key:   newKey,
                    name:      newName,
                }, function(r) {
                    if (r.success) {
                        presets[newKey] = r.data.preset;
                        $('#sta-preset-select').append(
                            $('<option>').val(newKey).text(newName)
                        ).val(newKey).trigger('change');
                        showMsg('Aangemaakt en geactiveerd ✓');
                    } else {
                        showMsg(r.data || 'Fout', false);
                    }
                });
            });

            $('#sta-preset-delete').on('click', function() {
                var key = $('#sta-preset-select').val();
                if (!confirm('Preset "' + (presets[key]?.name || key) + '" verwijderen?')) return;
                $.post(ajaxUrl, { action: 'sta_sr_delete_preset', nonce: nonce, preset_key: key }, function(r) {
                    if (r.success) {
                        delete presets[key];
                        $('#sta-preset-select option[value="' + key + '"]').remove();
                        $('#sta-preset-select').trigger('change');
                        showMsg('Verwijderd');
                    } else {
                        showMsg(r.data || 'Fout', false);
                    }
                });
            });
            // ── /Preset helpers ──────────────────────────────────────────────
            var $select    = $('#sta-meet-select');
            var $info      = $('#sta-meet-info');

            // Search filter
            $('#sta-meet-search').on('input', function() {
                var q = $(this).val().toLowerCase();
                $select.find('option').each(function() {
                    var match = !q || $(this).data('search').indexOf(q) !== -1;
                    $(this).toggle(match);
                });
            });

            // Show info on select
            function loadMeetInfo(id) {
                if (!id) return;
                $info.html('<span style="color:#999;">Ophalen&#8230;</span>');
                $.post(ajaxUrl, { action: 'sta_sr_fetch_meet', nonce: nonce, meet_id: id }, function(r) {
                    if (r.success) {
                        $info.html(
                            '<strong>' + $('<span>').text(r.data.name).html() + '</strong>'
                            + ' &mdash; ' + $('<span>').text(r.data.city).html()
                            + '<br>' + r.data.start + ' t/m ' + r.data.end
                            + ' | <strong>' + r.data.athletes + '</strong> atleten'
                            + ', <strong>' + r.data.events + '</strong> events'
                        );
                    } else {
                        $info.html('<span style="color:#c00;">Ophalen mislukt.</span>');
                    }
                });
            }

            $select.on('change', function() {
                loadMeetInfo($(this).val());
            });

            // Pre-load info for currently selected option
            if ($select.val()) loadMeetInfo($select.val());

            // Link meet
            $('#sta-link-meet').on('click', function() {
                var id = $select.val();
                if (!id) { alert('Selecteer eerst een meet.'); return; }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Koppelen&#8230;');
                $.post(ajaxUrl, { action: 'sta_sr_link_meet', nonce: nonce, meet_id: id }, function(r) {
                    if (r.success) {
                        meetId = id;
                        $('#sta-import-athletes, #sta-import-program').prop('disabled', false);
                        $btn.prop('disabled', false).text('Meet koppelen');
                        // Show confirmation
                        $btn.after('<span id="sta-link-ok" style="color:#46b450;margin-left:10px;">&#10003; Gekoppeld</span>');
                        setTimeout(function() { $('#sta-link-ok').fadeOut(); }, 3000);
                        // Reload to update header banner
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $btn.prop('disabled', false).text('Meet koppelen');
                        alert('Fout: ' + (r.data || 'Onbekend'));
                    }
                });
            });

            // Merge duplicates
            $('#sta-merge-duplicates').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig&#8230;');
                $('#sta-merge-result').html('<span style="color:#999;">Duplicaten zoeken en samenvoegen&#8230;</span>');
                $.post(ajaxUrl, { action: 'sta_sr_merge_duplicates', nonce: nonce }, function(r) {
                    $btn.prop('disabled', false).text('Dubbele atleten samenvoegen');
                    if (r.success) {
                        $('#sta-merge-result').html(
                            '<span style="color:#46b450;">&#10003; ' + r.data.merged + ' duplicaten samengevoegd, ' + r.data.deleted + ' verwijderd.</span>'
                        );
                    } else {
                        $('#sta-merge-result').html('<span style="color:#c00;">&#10007; ' + (r.data || 'Fout') + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Dubbele atleten samenvoegen');
                    $('#sta-merge-result').html('<span style="color:#c00;">&#10007; Verbindingsfout.</span>');
                });
            });

            // Import athletes
            $('#sta-import-athletes').on('click', function() {
                var id = meetId || $select.val();
                if (!id) { alert('Koppel eerst een meet.'); return; }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig&#8230;');
                $('#sta-athletes-result').html('<span style="color:#999;">Atleten ophalen en importeren&#8230;</span>');
                $.post(ajaxUrl, { action: 'sta_sr_import_athletes', nonce: nonce, meet_id: id }, function(r) {
                    $btn.prop('disabled', false).text('Atleten importeren');
                    if (r.success) {
                        $('#sta-athletes-result').html(
                            '<span style="color:#46b450;">&#10003; '
                            + r.data.created + ' aangemaakt, '
                            + r.data.updated + ' bijgewerkt, '
                            + r.data.skipped + ' overgeslagen.</span>'
                        );
                    } else {
                        $('#sta-athletes-result').html(
                            '<span style="color:#c00;">&#10007; ' + (r.data || 'Fout') + '</span>'
                        );
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Atleten importeren');
                    $('#sta-athletes-result').html('<span style="color:#c00;">&#10007; Verbindingsfout.</span>');
                });
            });

            // Import program
            $('#sta-import-program').on('click', function() {
                var id = meetId || $select.val();
                if (!id) { alert('Koppel eerst een meet.'); return; }
                if (!confirm('Dit overschrijft het bestaande programma. Doorgaan?')) return;
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig&#8230;');
                $('#sta-program-result').html('<span style="color:#999;">Programma ophalen&#8230;</span>');
                $.post(ajaxUrl, { action: 'sta_sr_import_program', nonce: nonce, meet_id: id }, function(r) {
                    $btn.prop('disabled', false).text('Programma importeren');
                    if (r.success) {
                        $('#sta-program-result').html(
                            '<span style="color:#46b450;">&#10003; '
                            + r.data.imported + ' events in '
                            + r.data.sessions + ' sessions ge&iuml;mporteerd'
                            + (r.data.skipped ? ' (' + r.data.skipped + ' overgeslagen)' : '')
                            + '.</span>'
                        );
                    } else {
                        $('#sta-program-result').html(
                            '<span style="color:#c00;">&#10007; ' + (r.data || 'Fout') + '</span>'
                        );
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Programma importeren');
                    $('#sta-program-result').html('<span style="color:#c00;">&#10007; Verbindingsfout.</span>');
                });
            });
            // Fetch club logos
            $('#sta-fetch-logos').on('click', function() {
                var id = meetId || $select.val();
                if (!id) { alert('Koppel eerst een meet.'); return; }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig…');
                $('#sta-logos-result').html('<span style="color:#999;">Logo\'s ophalen…</span>');
                $.post(ajaxUrl, { action: 'sta_sr_fetch_logos', nonce: nonce }, function(r) {
                    $btn.prop('disabled', false).text('Club logo\'s ophalen');
                    if (r.success) {
                        $('#sta-logos-result').html(
                            '<span style="color:#46b450;">✓ ' + r.data.count + ' verenigingen opgeslagen.</span>'
                        );
                    } else {
                        $('#sta-logos-result').html(
                            '<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>'
                        );
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Club logo\'s ophalen');
                    $('#sta-logos-result').html('<span style="color:#c00;">✗ Verbindingsfout.</span>');
                });
            });

            // Delete all athletes
            $('#sta-delete-athletes').on('click', function() {
                if (!confirm('Alle atleten permanent verwijderen? Dit kan niet ongedaan worden gemaakt.')) return;
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig…');
                $.post(ajaxUrl, { action: 'sta_sr_delete_all_athletes', nonce: nonce }, function(r) {
                    $btn.prop('disabled', false).text('Alle atleten wissen');
                    if (r.success) {
                        $('#sta-delete-result').html('<span style="color:#46b450;">✓ ' + r.data.deleted + ' atleten verwijderd.</span>');
                    } else {
                        $('#sta-delete-result').html('<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>');
                    }
                });
            });

            // Link athletes to events
            // Unlink relays
            $('#sta-unlink-relays').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig…');
                $('#sta-relays-result').html('<span style="color:#999;">Estafettes ontkoppelen…</span>');
                $.post(ajaxUrl, { action: 'sta_sr_unlink_relays', nonce: nonce }, function(r) {
                    $btn.prop('disabled', false).text('Estafettes ontkoppelen van atleten');
                    if (r.success) {
                        $('#sta-relays-result').html(
                            '<span style="color:#46b450;">✓ ' + r.data.removed + ' estafette-afstanden ontkoppeld bij ' + r.data.athletes + ' atleten.</span>'
                        );
                    } else {
                        $('#sta-relays-result').html('<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Estafettes ontkoppelen van atleten');
                    $('#sta-relays-result').html('<span style="color:#c00;">✗ Verbindingsfout.</span>');
                });
            });

            $('#sta-link-events').on('click', function() {
                var id = meetId || $select.val();
                if (!id) { alert('Koppel eerst een meet.'); return; }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig…');
                $('#sta-events-result').html('<span style="color:#999;">Inschrijvingen ophalen per event… (kan ±30s duren)</span>');
                $.post(ajaxUrl, { action: 'sta_sr_link_athlete_events', nonce: nonce, meet_id: id }, function(r) {
                    $btn.prop('disabled', false).text('Atleten koppelen aan events');
                    if (r.success) {
                        console.log('sta_link_events debug:', r.data.debug);
                        $('#sta-events-result').html(
                            '<span style="color:#46b450;">✓ '
                            + r.data.terms + ' afstand-termen, '
                            + r.data.linked + ' koppelingen gelegd via '
                            + r.data.events + ' events'
                            + (r.data.not_found ? ' (' + r.data.not_found + ' atleten niet gevonden)' : '')
                            + '.</span>'
                            + (r.data.debug ? '<pre style="font-size:11px;margin-top:8px;background:#f5f5f5;padding:8px;overflow:auto;">' + JSON.stringify(r.data.debug, null, 2) + '</pre>' : '')
                        );
                    } else {
                        $('#sta-events-result').html('<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>');
                    }
                }, 'json').fail(function() {
                    $btn.prop('disabled', false).text('Atleten koppelen aan events');
                    $('#sta-events-result').html('<span style="color:#c00;">✗ Verbindingsfout of timeout.</span>');
                });
            });
            // Import results
            $('#sta-import-results').on('click', function() {
                var id = meetId || $select.val();
                if (!id) { alert('Koppel eerst een meet.'); return; }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig…');
                $('#sta-results-result').html('<span style="color:#999;">Uitslagen ophalen…</span>');
                $.post(ajaxUrl, { action: 'sta_sr_import_results', nonce: nonce, meet_id: id }, function(r) {
                    $btn.prop('disabled', false).text('Uitslagen importeren');
                    if (r.success) {
                        $('#sta-results-result').html(
                            '<span style="color:#46b450;">✓ '
                            + r.data.fetched + ' van ' + r.data.total + ' finale-events opgehaald.</span>'
                        );
                    } else {
                        $('#sta-results-result').html('<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>');
                    }
                }, 'json').fail(function() {
                    $btn.prop('disabled', false).text('Uitslagen importeren');
                    $('#sta-results-result').html('<span style="color:#c00;">✗ Verbindingsfout.</span>');
                });
            });

            // Clear heats cache
            $('#sta-clear-heats-cache').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Bezig…');
                $.post(ajaxUrl, { action: 'sta_sr_clear_heats_cache', nonce: nonce }, function(r) {
                    $btn.prop('disabled', false).text('Wis startlijst cache');
                    if (r.success) {
                        $('#sta-results-result').html('<span style="color:#46b450;">✓ Startlijst cache gewist.</span>');
                    } else {
                        $('#sta-results-result').html('<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>');
                    }
                }, 'json');
            });

            // Clear results
            $('#sta-clear-results').on('click', function() {
                if (!confirm('Alle geïmporteerde uitslagen wissen?')) return;
                var $btn = $(this);
                $btn.prop('disabled', true);
                $.post(ajaxUrl, { action: 'sta_sr_clear_results', nonce: nonce }, function(r) {
                    $btn.prop('disabled', false);
                    if (r.success) {
                        $('#sta-results-result').html('<span style="color:#46b450;">✓ Uitslagen gewist.</span>');
                    } else {
                        $('#sta-results-result').html('<span style="color:#c00;">✗ ' + (r.data || 'Fout') + '</span>');
                    }
                }, 'json');
            });

            $('#sta-save-autoopen').on('click', function() {
                var mins = parseInt($('#sta-startlist-autoopen').val(), 10);
                if (isNaN(mins) || mins < 0) return;
                $.post(ajaxUrl, { action: 'sta_save_startlist_autoopen', nonce: nonce, mins: mins }, function(r) {
                    var $msg = $('#sta-autoopen-msg');
                    if (r.success) {
                        $msg.text('✓ Opgeslagen').css('color', '#46b450');
                    } else {
                        $msg.text('✗ Fout').css('color', '#c00');
                    }
                    setTimeout(function() { $msg.text(''); }, 3000);
                }, 'json');
            });
        });
        </script>
        <?php
    }
}
