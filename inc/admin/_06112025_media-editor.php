<?php
add_action('admin_menu', function () {
    add_media_page('Ã‰diteur DNC', 'Ã‰diteur DNC', 'upload_files', 'dn-media-editor', 'render_alt_editor_page');
});

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'media_page_dn-media-editor') return;
    wp_enqueue_script('jquery');
});

// --- RÃ©glages perfs ---
if (!defined('DNC_USAGE_TRANSIENT_TTL')) define('DNC_USAGE_TRANSIENT_TTL', 24 * HOUR_IN_SECONDS);

// on ne scanne que ces familles d'options (Ã©norme gain sur sites volumineux)
function dnc_options_allowlist_patterns() {
    return [
        'theme_mods_%',
        'widget_%',
        'sidebars_widgets',
        'acf_%',
        'elementor_%',
        'polylang_%',
        'wpseo_%',
        'woocommerce_%',
        'nav_menu_options',
        'customize_%',
    ];
}

// nombre dâ€™options Ã  montrer dans lâ€™info-bulle
if (!defined('DNC_TOOLTIP_OPTIONS_PREVIEW_MAX')) define('DNC_TOOLTIP_OPTIONS_PREVIEW_MAX', 5);

// Helper pour le tooltip
function dnc_build_usage_tooltip(array $b) {
    $by = $b['by_source'];
    $opt_hits = $b['options_hits'];
    $lines = [];
    $lines[] = "Total : {$b['total']} (posts : {$b['posts_total']})";
    $lines[] = "- Contenu des posts : {$by['posts_content']}";
    $lines[] = "- Metas de post (ACF/Elementor, galleriesâ€¦) : {$by['postmeta']}";
    $lines[] = "- Image Ã  la une : {$by['thumbnails']}";
    $lines[] = "- Term meta : {$by['termmeta']}";
    $lines[] = "- Options (widgets/theme_mods/ACF options) : {$by['options']}";
    if (!empty($opt_hits)) {
        $lines[] = "Exemples dâ€™options : " . implode(', ', $opt_hits) . ( $by['options'] > count($opt_hits) ? 'â€¦' : '' );
    }
    return implode("\n", $lines);
}



function dnc_is_attachment_missing( $att_id ) {
    $file = get_attached_file($att_id);
    if ($file) {
        return ! file_exists($file);
    }
    $url = wp_get_attachment_url($att_id);
    if ( ! $url ) return true;

    $uploads = wp_get_upload_dir();
    // Si URL sous /uploads, on reconstruit le chemin local au lieu d’un HEAD
    if (strpos($url, $uploads['baseurl']) === 0) {
        $local = $uploads['basedir'] . str_replace($uploads['baseurl'], '', $url);
        return ! file_exists($local);
    }

    // Pour les hôtes externes : HEAD très court, mais idéalement désactivable via un filtre
    $resp = wp_remote_head($url, ['timeout' => 1, 'redirection' => 1]);
    if ( is_wp_error($resp) ) return true;
    return ((int) wp_remote_retrieve_response_code($resp)) >= 400;
}


function dnc_get_image_usage_breakdown( $att_id, $force_refresh = false ) {
    global $wpdb;

    $cache_key = 'dnc_usage_breakdown_' . $att_id;
    if ( ! $force_refresh ) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $url = wp_get_attachment_url($att_id);
    if (!$url) {
        $empty = [
            'total'        => 0,
            'posts_total'  => 0,
            'by_source'    => [
                'posts_content' => 0,
                'postmeta'      => 0,
                'thumbnails'    => 0,
                'termmeta'      => 0,
                'options'       => 0,
            ],
            'options_hits' => [],
        ];
        set_transient($cache_key, $empty, DNC_USAGE_TRANSIENT_TTL);
        return $empty;
    }

    // Patterns communs
    $like_url       = '%' . $wpdb->esc_like($url) . '%';
    $like_class     = '%wp-image-' . $att_id . '%';
    $like_attach_id = '%attachment_id=' . $att_id . '%';
    $like_json_id   = '%"id":' . $att_id . '%';   // Elementor JSON
    $like_serial_i  = '%i:' . $att_id . ';%';     // sÃ©rialisÃ© PHP (int)
    $like_serial_s  = '%"' . $att_id . '"%';      // sÃ©rialisÃ© PHP (string)
    $like_csv_start = $att_id . ',%';
    $like_csv_mid   = '%,' . $att_id . ',%';
    $like_csv_end   = '%,' . $att_id;

    // 1) POSTS : contenu
    $sql_posts_content = $wpdb->prepare("
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        WHERE p.post_status NOT IN ('auto-draft','trash')
          AND p.post_type NOT IN ('revision','nav_menu_item')
          AND (p.post_content LIKE %s OR p.post_content LIKE %s OR p.post_content LIKE %s)
    ", $like_url, $like_class, $like_attach_id);
    $post_ids_content = $wpdb->get_col($sql_posts_content);
    $count_content = count($post_ids_content);

    // 2) POSTS : metas (ACF, Elementor, galleries, etc.)
    $sql_postmeta = $wpdb->prepare("
        SELECT DISTINCT pm.post_id
        FROM {$wpdb->postmeta} pm
        WHERE
             pm.meta_value = %d
          OR pm.meta_value LIKE %s  /* CSV start: 123, */
          OR pm.meta_value LIKE %s  /* CSV mid: ,123, */
          OR pm.meta_value LIKE %s  /* CSV end: ,123 */
          OR pm.meta_value LIKE %s  /* JSON: \"id\":123 */
          OR pm.meta_value LIKE %s  /* URL */
          OR pm.meta_value LIKE %s  /* sÃ©rialisÃ©: i:123; */
          OR pm.meta_value LIKE %s  /* sÃ©rialisÃ©: \"123\" */
          OR pm.meta_value LIKE %s  /* attachment_id=123 */
    ", $att_id, $like_csv_start, $like_csv_mid, $like_csv_end, $like_json_id, $like_url, $like_serial_i, $like_serial_s, $like_attach_id);
    $post_ids_meta = $wpdb->get_col($sql_postmeta);
    $count_postmeta = count($post_ids_meta);

    // 3) POSTS : image Ã  la une
    $sql_thumbs = $wpdb->prepare("
        SELECT DISTINCT pm.post_id
        FROM {$wpdb->postmeta} pm
        WHERE pm.meta_key = '_thumbnail_id'
          AND pm.meta_value = %d
    ", $att_id);
    $post_ids_thumbs = $wpdb->get_col($sql_thumbs);
    $count_thumbs = count($post_ids_thumbs);

    // Union pour le total posts distincts
    $posts_total = count( array_unique( array_merge($post_ids_content, $post_ids_meta, $post_ids_thumbs) ) );

    // 4) TERMMETA
    $count_termmeta = 0;
    if (!empty($wpdb->termmeta)) {
        $sql_termmeta = $wpdb->prepare("
            SELECT COUNT(DISTINCT tm.term_id)
            FROM {$wpdb->termmeta} tm
            WHERE
                 tm.meta_value = %d
              OR tm.meta_value LIKE %s
              OR tm.meta_value LIKE %s
              OR tm.meta_value LIKE %s
              OR tm.meta_value LIKE %s
              OR tm.meta_value LIKE %s
              OR tm.meta_value LIKE %s
              OR tm.meta_value LIKE %s
        ", $att_id, $like_csv_start, $like_csv_mid, $like_csv_end, $like_json_id, $like_url, $like_serial_i, $like_serial_s);
        $count_termmeta = (int) $wpdb->get_var($sql_termmeta);
    }

    // 5) OPTIONS (filtrÃ©es par allowlist)
    $option_names = [];
    $patterns = dnc_options_allowlist_patterns();

    // Construire une clause WHERE option_name LIKE ... en OR
    $where_name = [];
    $params = [];
    foreach ($patterns as $pat) {
        $where_name[] = "option_name LIKE %s";
        $params[] = $pat;
    }
    $where_name_sql = '(' . implode(' OR ', $where_name) . ')';

    // On restreint d'abord par option_name (indexÃ©), puis on vÃ©rifie la valeur
    $sql_options = "
        SELECT option_name
        FROM {$wpdb->options}
        WHERE {$where_name_sql}
          AND (
                 option_value = %d
              OR option_value LIKE %s
              OR option_value LIKE %s
              OR option_value LIKE %s
              OR option_value LIKE %s
              OR option_value LIKE %s
              OR option_value LIKE %s
              OR option_value LIKE %s
              OR option_value LIKE %s
          )
    ";
    $prepared = $wpdb->prepare(
        $sql_options,
        array_merge(
            $params,
            [$att_id, $like_csv_start, $like_csv_mid, $like_csv_end, $like_json_id, $like_url, $like_serial_i, $like_serial_s, $like_attach_id]
        )
    );
    $option_names = $wpdb->get_col($prepared);
    $count_options = is_array($option_names) ? count($option_names) : 0;

    // AperÃ§u lisible dâ€™options
    if ($count_options > 0) {
        usort($option_names, function($a, $b){
            $prio = function($n){
                if (strpos($n, 'theme_mods_') === 0) return 0;
                if (strpos($n, 'widget_') === 0)     return 1;
                if (strpos($n, 'acf') !== false)     return 2;
                if ($n === 'sidebars_widgets')       return 0;
                return 3;
            };
            return $prio($a) <=> $prio($b);
        });
    }
    $opt_preview = array_slice((array) $option_names, 0, DNC_TOOLTIP_OPTIONS_PREVIEW_MAX);

    // Total global = posts distincts + â€œsite-wideâ€ (termmeta + options)
    $total = (int) $posts_total + (int) $count_termmeta + (int) $count_options;

    $data = [
        'total'        => $total,
        'posts_total'  => $posts_total,
        'by_source'    => [
            'posts_content' => $count_content,
            'postmeta'      => $count_postmeta,
            'thumbnails'    => $count_thumbs,
            'termmeta'      => $count_termmeta,
            'options'       => $count_options,
        ],
        'options_hits' => $opt_preview,
    ];

    set_transient($cache_key, $data, DNC_USAGE_TRANSIENT_TTL);
    return $data;
}

function dnc_count_image_usage( $att_id, $force_refresh = false ) {
    if ($force_refresh) {
        delete_transient('dnc_usage_breakdown_' . $att_id);
    }
    $b = dnc_get_image_usage_breakdown($att_id, $force_refresh);
    return (int) $b['total'];
}


add_action('wp_ajax_dnc_delete_orphan', function () {
    check_ajax_referer('dnc_media_editor', 'nonce');
    if ( ! current_user_can('delete_posts') ) {
        wp_send_json_error(['message' => 'Permission refusÃ©e'], 403);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if ( ! $id ) {
        wp_send_json_error(['message' => 'ID invalide']);
    }

    // SÃ©curitÃ© : sâ€™assurer que câ€™est bien une piÃ¨ce jointe image
    $post = get_post($id);
    if ( ! $post || $post->post_type !== 'attachment' || strpos($post->post_mime_type, 'image/') !== 0 ) {
        wp_send_json_error(['message' => 'PiÃ¨ce jointe invalide']);
    }

    $deleted = wp_delete_attachment($id, true);
    if ($deleted) {
        // Purge tous les caches d'usage
        delete_transient('dnc_usage_' . $id);
        delete_transient('dnc_usage_breakdown_' . $id); // <-- ajout
        wp_send_json_success(['message' => 'Image supprimée']);
    }
    wp_send_json_error(['message' => 'Ã‰chec de suppression']);
});

add_action('wp_ajax_dnc_delete_attachment', function () {
    check_ajax_referer('dnc_media_editor', 'nonce');
    if ( ! current_user_can('delete_posts') ) {
        wp_send_json_error(['message' => 'Permission refusÃ©e'], 403);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if ( ! $id ) {
        wp_send_json_error(['message' => 'ID invalide']);
    }

    $post = get_post($id);
    if ( ! $post || $post->post_type !== 'attachment' || strpos($post->post_mime_type, 'image/') !== 0 ) {
        wp_send_json_error(['message' => 'PiÃ¨ce jointe invalide']);
    }

    $deleted = wp_delete_attachment($id, true);
    if ($deleted) {
        delete_transient('dnc_usage_' . $id);
        delete_transient('dnc_usage_breakdown_' . $id);
        wp_send_json_success(['message' => 'Image supprimÃ©e']);
    }
    wp_send_json_error(['message' => 'Ã‰chec de suppression']);
});

add_action('wp_ajax_dnc_recalc_usage', function(){
    check_ajax_referer('dnc_media_editor', 'nonce');
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission refusÃ©e'], 403);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) wp_send_json_error(['message' => 'ID invalide'], 400);

    delete_transient('dnc_usage_breakdown_' . $id);
    $b = dnc_get_image_usage_breakdown($id, true);
    $tooltip = dnc_build_usage_tooltip($b);

    wp_send_json_success([
        'count'   => (int) $b['total'],
        'tooltip' => $tooltip,
    ]);
});

add_action('wp_ajax_dnc_media_list', function(){
    check_ajax_referer('dnc_media_editor', 'nonce');
    if ( ! current_user_can('upload_files') ) {
        wp_send_json_error(['message'=>'Permissions insuffisantes'], 403);
    }

    $tab    = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : 'valid';
    $page   = max(1, absint($_POST['page'] ?? 1));
    $pp     = min(200, max(1, absint($_POST['per_page'] ?? 50)));
    $search = sanitize_text_field($_POST['search'] ?? '');
    $onlyMissingAlt = !empty($_POST['only_missing_alt']);

    // On fait deux requÃªtes distinctes selon onglet
    if ($tab === 'orphans') {
        dnc_ajax_list_orphans($page, $pp, $search);
    } elseif ($tab === 'unused') { 
        dnc_ajax_list_unused($page, $pp, $search, $onlyMissingAlt);
    } else {
        dnc_ajax_list_valid($page, $pp, $search, $onlyMissingAlt);
    }
});

add_action('wp_ajax_dnc_recalc_usage_batch', function(){
    check_ajax_referer('dnc_media_editor','nonce');
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission refusée'], 403);
    }

    $ids = array_map('absint', (array)($_POST['ids'] ?? []));
    if (empty($ids)) {
        wp_send_json_error(['message' => 'IDs manquants'], 400);
    }

    // On invalide le cache existant pour ces IDs
    foreach ($ids as $id) {
        delete_transient('dnc_usage_breakdown_' . $id);
    }

    $usage = [];

    // OPTION A (rapide à intégrer) : on réutilise ta fonction existante, par ID
    foreach ($ids as $id) {
        $b = dnc_get_image_usage_breakdown($id, true);
        $usage[$id] = [
            'count'   => (int)$b['total'],
            'tooltip' => dnc_build_usage_tooltip($b),
        ];
        // On peut re-cacher le résultat pour la prochaine fois
        set_transient('dnc_usage_breakdown_' . $id, $b, DNC_USAGE_TRANSIENT_TTL);
    }

    // OPTION B (optimal, si tu as implémenté dnc_prefetch_usage_for_ids($ids))
    // $map = dnc_prefetch_usage_for_ids($ids);
    // foreach ($map as $id => $b) {
    //    $usage[$id] = [
    //        'count'   => (int)$b['total'],
    //        'tooltip' => dnc_build_usage_tooltip($b),
    //    ];
    //    set_transient('dnc_usage_breakdown_' . $id, $b, DNC_USAGE_TRANSIENT_TTL);
    // }

    wp_send_json_success(['usage' => $usage]);
});



// ------ Helpers de rendu ------
function dnc_escape_attr_soft($s){ return esc_attr( (string) $s ); }

// Images valides
function dnc_ajax_list_valid($page, $pp, $search, $onlyMissingAlt){
    // WP_Query pour rÃ©cupÃ©rer des attachments image/*, avec recherche sur titre
    $args = [
      'post_type'      => 'attachment',
      'post_status'    => 'inherit',
      'post_mime_type' => 'image',
      'posts_per_page' => $pp,
      'paged'          => $page,
      'orderby'        => 'date',
      'order'          => 'DESC',
      's'              => $search,
      'no_found_rows'  => false,   // ou true si tu peux
      // 'fields'      => 'ids',   // retire ça
      'update_post_term_cache' => false,
      'update_post_meta_cache' => true,
    ];
    $q = new WP_Query($args);
    $posts = $q->posts; // objets WP_Post
    $ids = wp_list_pluck($posts, 'ID');
    update_meta_cache('post', $ids); // par sécurité
    $total_found = (int) $q->found_posts;
    $total_pages = max(1, (int) ceil($total_found / $pp));

    ob_start();
    $shown = 0;

    $usage_map = dnc_prefetch_usage_for_ids($ids);
    
    foreach ($ids as $id) {
        // Filtre ALT vides uniquement (si demandÃ©)
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        if ($onlyMissingAlt && !empty($alt)) continue;

        // Ignore orphelines dans lâ€™onglet "valides"
        //if ( dnc_is_attachment_missing($id) ) continue;

        $title   = get_the_title($id);
        $legende = get_post_field('post_excerpt', $id);

        //$analysis = dnc_get_image_usage_breakdown($id);
        $analysis = $usage_map[$id] ?? ['total'=>0,'by_source'=>['posts_content'=>0,'postmeta'=>0,'thumbnails'=>0,'termmeta'=>0,'options'=>0],'options_hits'=>[]];
        $tooltip  = dnc_build_usage_tooltip($analysis);

        echo '<tr data-id="'.esc_attr($id).'" data-title="'.esc_attr(mb_strtolower($title)).'">';
        echo '<td>'. wp_get_attachment_image($id, 'thumbnail') .'</td>';
        echo '<td><label>Titre : </label><input type="text" class="title-field" value="'. dnc_escape_attr_soft($title) .'">';
        echo '<br /><label>LÃ©gende/Copyright : </label><input type="text" class="legende-field" value="'. dnc_escape_attr_soft($legende) .'">';
        echo '<br /><label>ALT : </label><input type="text" class="alt-field" value="'. dnc_escape_attr_soft($alt) .'"></td>';
        echo '<td><span class="usage-count" title="'. esc_attr($tooltip) .'">'. (int)$analysis['total'] .'</span></td>';
        echo '<td>
                <button class="generate-alt button">GÃ©nÃ©rer depuis le titre</button>
                <button class="refresh-usage button button-secondary" title="Recalculer les utilisations">â†»</button>
              </td>';
        echo '</tr>';

        $shown++;
    }

    if ($shown === 0) {
        echo '<tr><td colspan="4"><em>Aucun rÃ©sultat pour ces critÃ¨res.</em></td></tr>';
    }

    $rows_html = ob_get_clean();

    $summary = sprintf(
        'Page %d/%d â€” %d rÃ©sultat(s) trouvÃ©s',
        $total_pages ? min($page,$total_pages) : 1,
        max(1,$total_pages),
        $total_found
    );

    wp_send_json_success([
        'rows_html'    => $rows_html,
        'summary'      => $summary,
        'current_page' => min($page,$total_pages),
        'total_pages'  => $total_pages,
    ]);
}

function dnc_prefetch_usage_for_ids(array $ids) {
    global $wpdb;
    if (!$ids) return [];

    $map = [];
    foreach ($ids as $id) $map[$id] = [
        'total'=>0, 'posts_total'=>0,
        'by_source'=>['posts_content'=>0,'postmeta'=>0,'thumbnails'=>0,'termmeta'=>0,'options'=>0],
        'options_hits'=>[],
    ];

    // 1) Thumbnails en un seul SELECT
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $thumbs = $wpdb->get_results(
        $wpdb->prepare("
          SELECT meta_value AS att_id, COUNT(*) AS c
          FROM {$wpdb->postmeta}
          WHERE meta_key = '_thumbnail_id' AND meta_value IN ($placeholders)
          GROUP BY meta_value
        ", $ids), ARRAY_A
    );
    foreach ($thumbs as $row) {
        $id = (int)$row['att_id'];
        $c  = (int)$row['c'];
        if (isset($map[$id])) {
            $map[$id]['by_source']['thumbnails'] = $c;
            $map[$id]['total'] += $c;
        }
    }

    // 2) posts_content : on cherche par ID (rapide) plutôt que par URL
    //    NB: on évite les leading wildcards quand c’est possible
    $regexIds = implode('|', array_map('intval', $ids));
    $posts = $wpdb->get_results("
      SELECT ID, post_content
      FROM {$wpdb->posts}
      WHERE post_status NOT IN ('auto-draft','trash')
        AND post_type NOT IN ('revision','nav_menu_item')
        AND (post_content REGEXP 'wp-image-($regexIds)' OR post_content REGEXP 'attachment_id=($regexIds)')
    ");
    // distribue en PHP (plus rapide qu’une flopée de LIKE)
    foreach ($posts as $p) {
      foreach ($ids as $id) {
        if (strpos($p->post_content, 'wp-image-'.$id) !== false
            || strpos($p->post_content, 'attachment_id='.$id) !== false) {
          $map[$id]['by_source']['posts_content']++;
          $map[$id]['posts_total']++;  // on approxime ici
          $map[$id]['total']++;
        }
      }
    }

    // 3) postmeta : égalité directe sur meta_value (un seul scan)
    $pm = $wpdb->get_results(
      $wpdb->prepare("
        SELECT meta_value AS att_id, COUNT(DISTINCT post_id) AS c
        FROM {$wpdb->postmeta}
        WHERE meta_value IN ($placeholders)
        GROUP BY meta_value
      ", $ids), ARRAY_A
    );
    foreach ($pm as $row) {
        $id = (int)$row['att_id'];
        $c  = (int)$row['c'];
        if (isset($map[$id])) {
            $map[$id]['by_source']['postmeta'] += $c;
            $map[$id]['posts_total']          += $c;
            $map[$id]['total']                += $c;
        }
    }

    // 4) termmeta/options : charge une fois, filtre en PHP
    // Options allowlist
    $pat = dnc_options_allowlist_patterns();
    $where = [];
    $params = [];
    foreach ($pat as $p) { $where[]='option_name LIKE %s'; $params[]=$p; }
    $opts = $wpdb->get_results($wpdb->prepare("
      SELECT option_name, option_value FROM {$wpdb->options}
      WHERE ".implode(' OR ', $where), $params), ARRAY_A);

    // Construire une regex d’IDs une fois
    $re = '/(?<!\d)('.implode('|', array_map('intval',$ids)).')(?!\d)/';
    foreach ($opts as $o) {
        if (preg_match_all($re, (string)$o['option_value'], $m)) {
            foreach ($m[1] as $hit) {
                $hid = (int)$hit;
                if (!isset($map[$hid])) continue;
                $map[$hid]['by_source']['options']++;
                $map[$hid]['total']++;
                if (count($map[$hid]['options_hits']) < DNC_TOOLTIP_OPTIONS_PREVIEW_MAX) {
                    $map[$hid]['options_hits'][] = $o['option_name'];
                }
            }
        }
    }

    // (Termmeta sur le même principe si utile)

    return $map;
}


// Orphelines
function dnc_ajax_list_orphans($page, $pp, $search){
    // On doit repÃ©rer les orphelines. On commence par une page d'IDs avec recherche sur titre,
    // puis on filtre par orpheline.
    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => $pp * 3, // on â€œsurÃ©chantillonneâ€ pour compenser le filtre orphelines
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        's'              => $search,
        'fields'         => 'ids',
        'no_found_rows'  => true, // on fait une estimation pour la pagination
    ];
    $q = new WP_Query($args);
    $ids = $q->posts ?: [];

    $orphans = [];
    foreach ($ids as $id) {
        if ( dnc_is_attachment_missing($id) ) {
            $orphans[] = $id;
            if (count($orphans) >= $pp) break;
        }
    }

    ob_start();
    $count_page = 0;
    foreach ($orphans as $id) {
        $title   = get_the_title($id);
        $legende = get_post_field('post_excerpt', $id);
        $alt     = get_post_meta($id, '_wp_attachment_image_alt', true);
        $url     = wp_get_attachment_url($id);

        echo '<tr data-id="'.esc_attr($id).'">';
        echo '<td>'. wp_get_attachment_image($id, 'thumbnail') .'</td>';
        echo '<td>'. esc_html($title) .'';
        echo '<br />'. esc_html($legende) .'';
        echo '<br />'. esc_html($alt) .'</td>';
        echo '<td style="word-break:break-all;"><a href="'. esc_url($url) .'" target="_blank" rel="noopener">'. esc_html($url) .'</a></td>';
        echo '<td><button class="delete-orphan button button-link-delete">ðŸ—‘ï¸ Supprimer</button></td>';
        echo '</tr>';

        $count_page++;
    }

    if ($count_page === 0) {
        echo '<tr><td colspan="6"><em>Aucune photo orpheline sur cette page de rÃ©sultats.</em></td></tr>';
    }

    $rows_html = ob_get_clean();

    // NB : on nâ€™a pas un â€œtotal orphelinesâ€ exact sans scanner tout â€” on affiche un rÃ©sumÃ© indicatif
    $summary = sprintf('Page %d â€” affichage jusquâ€™Ã  %d orphelines (recherche: %s)',
        $page, $pp, $search ? esc_html($search) : 'aucune');

    // Pagination approximative : on permet dâ€™avancer/retourner tant quâ€™on trouve des rÃ©sultats
    // Pour faire un total exact, on pourrait avoir un cron/CLI qui calcule et stocke le nombre dâ€™orphelines.
    $total_pages = ($count_page < $pp && $page===1) ? 1 : max(1, $page + 1); // simple garde-fou

    wp_send_json_success([
        'rows_html'    => $rows_html,
        'summary'      => $summary,
        'current_page' => $page,
        'total_pages'  => $total_pages,
    ]);
}

function dnc_ajax_list_unused($page, $pp, $search, $onlyMissingAlt){
    // On sur-Ã©chantillonne car on filtre ensuite (usage total = 0)
    $args = [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => $pp * 3,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        's'              => $search,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ];
    $q = new WP_Query($args);
    $ids = $q->posts ?: [];

    $rows = [];
    foreach ($ids as $id) {
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        if ($onlyMissingAlt && !empty($alt)) continue;

        $analysis = dnc_get_image_usage_breakdown($id);
        if ( (int)$analysis['total'] !== 0 ) continue; // on ne garde que les non utilisÃ©es

        $title   = get_the_title($id);
        $legende = get_post_field('post_excerpt', $id);
        $tooltip = dnc_build_usage_tooltip($analysis); // montrera Total:0 â€¦
        $is_orphan = dnc_is_attachment_missing($id);

        ob_start();
        echo '<tr data-id="'.esc_attr($id).'">';
        echo '<td>'. wp_get_attachment_image($id, 'thumbnail') .'</td>';

        // <-- une seule cellule "Informations" avec 3 champs, comme dans "valid"
        echo '<td><label>Titre : </label><input type="text" class="title-field" value="'. dnc_escape_attr_soft($title) .'">';
        echo '<br /><label>Légende/Copyright : </label><input type="text" class="legende-field" value="'. dnc_escape_attr_soft($legende) .'">';
        echo '<br /><label>ALT : </label><input type="text" class="alt-field" value="'. dnc_escape_attr_soft($alt) .'"></td>';

        echo '<td><span class="usage-count" title="'. esc_attr($tooltip) .'">0</span></td>';
        echo '<td>'. ( $is_orphan
            ? '<span class="status-badge" style="background:#ffe0e0;border:1px solid #e99;padding:2px 6px;border-radius:4px;">Orpheline</span>'
            : '<span class="status-badge" style="background:#eef;border:1px solid #99c;padding:2px 6px;border-radius:4px;">Non utilisée</span>'
        ) .'</td>';
        echo '<td><button class="delete-attachment button button-link-delete">🗑️ Supprimer</button></td>';
        echo '</tr>';
        $rows[] = ob_get_clean();


        if (count($rows) >= $pp) break;
    }

    if (empty($rows)) {
        $rows_html = '<tr><td colspan="7"><em>Aucune image non utilisÃ©e trouvÃ©e sur cette page de rÃ©sultats.</em></td></tr>';
    } else {
        $rows_html = implode('', $rows);
    }

    // Pagination â€œbest effortâ€ (comme orphelines). Pour un total exact, prÃ©voir un index via WP-CLI.
    $summary = sprintf('Page %d â€” jusquâ€™Ã  %d images non utilisÃ©es (recherche: %s)',
        $page, $pp, $search ? esc_html($search) : 'aucune');

    $total_pages = (count($rows) < $pp && $page===1) ? 1 : max(1, $page + 1);

    wp_send_json_success([
        'rows_html'    => $rows_html,
        'summary'      => $summary,
        'current_page' => $page,
        'total_pages'  => $total_pages,
    ]);
}

function render_alt_editor_page() {
    $nonce = wp_create_nonce('dnc_media_editor');
    ?>
    <div class="wrap">
        <h1>Ã‰diteur de texte ALT / Copyright des images</h1>

        <div class="dnc-toolbar" style="display:flex;gap:12px;align-items:center;margin:12px 0;">
            <input type="text" id="image-search" placeholder="ðŸ” Rechercher par titre..." style="width: 280px;">
            <label style="display:flex;gap:6px;align-items:center;">
                <input type="checkbox" id="filter-missing-alt">
                <span>ALT vides uniquement</span>
            </label>
            <label>Par page
                <select id="per-page">
                    <option selected>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </label>
            <button id="generate-all-alt" class="button button-secondary">ðŸ”„ GÃ©nÃ©rer tous les ALT vides (page)</button>
            <button id="dnc-recalc-page" class="button">â†» Tout recalculer (page)</button>
        </div>

        <h2 class="nav-tab-wrapper" style="margin-top:10px;">
            <a href="#" class="nav-tab nav-tab-active" data-tab="valid">Images valides</a>
            <a href="#" class="nav-tab" data-tab="orphans">ðŸ“· Photos orphelines</a>
            <a href="#" class="nav-tab" data-tab="unused">ðŸ§¹ Images non utilisÃ©es</a>
        </h2>

        <div id="dnc-tab-valid" class="dnc-tab active">
            <table class="wp-list-table widefat fixed striped" id="media-editor-table">
                <thead>
                <tr>
                    <th style="width:200px;">Image</th>
                    <th>Informations</th>
                    <th style="width:90px;">Utilisations</th>
                    <th style="width:200px;">Actions</th>
                </tr>
                </thead>
                <tbody><!-- rempli via AJAX --></tbody>
            </table>
            <div class="tab-footer" style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div id="valid-summary" style="color:#666;"></div>
                <div id="valid-pagination" class="tab-pagination"></div>
            </div>
        </div>

        <div id="dnc-tab-orphans" class="dnc-tab" style="display:none;">
            <table class="wp-list-table widefat fixed striped" id="orphans-table">
                <thead>
                <tr>
                    <th style="width:200px;">Image</th>
                    <th>Informations</th>
                    <th>URL</th>
                    <th style="width:120px;">Suppression</th>
                </tr>
                </thead>
                <tbody><!-- rempli via AJAX --></tbody>
            </table>
            <div class="tab-footer" style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div id="orphans-summary" style="color:#666;"></div>
                <div id="orphans-pagination" class="tab-pagination"></div>
            </div>
        </div>

        <div id="dnc-tab-unused" class="dnc-tab" style="display:none;">
            <table class="wp-list-table widefat fixed striped" id="unused-table">
                <thead>
                <tr>
                    <th style="width:80px;">Image</th>
                    <th>Informations</th>
                    <th style="width:90px;">Utilisations</th>
                    <th style="width:120px;">Statut</th>
                    <th style="width:120px;">Suppression</th>
                </tr>
                </thead>
                <tbody><!-- rempli via AJAX --></tbody>
            </table>
            <div class="tab-footer" style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div id="unused-summary" style="color:#666;"></div>
                <div id="unused-pagination" class="tab-pagination"></div>
            </div>
        </div>
    </div>

    <style>
        #media-editor-table input { width: 100%; box-sizing: border-box; }
        .tab-pagination .page-link { padding:4px 8px; margin:0 2px; border:1px solid #ddd; background:#fff; text-decoration:none; }
        .tab-pagination .current { font-weight:bold; background:#f0f0f0; }
        .nav-tab-wrapper .nav-tab { cursor:pointer; }
    </style>

    <script>
    jQuery(function($){
        const nonce = '<?php echo esc_js($nonce); ?>';

        // ---- Etat & helpers
        const state = {
            activeTab: 'valid', // 'valid' | 'orphans'
            page: { valid: 1, orphans: 1, unused: 1 },
            perPage: 10,
            search: '',
            onlyMissingAlt: false,
            debounce: null
        };

        function renderPagination($wrap, current, totalPages){
            $wrap.empty();
            if (totalPages <= 1) return;
            const mk = (p, label, cls='') => $('<a href="#" class="page-link '+cls+'">').text(label).data('page', p);
            if (current > 1) $wrap.append(mk(current-1, 'â€¹'));
            for (let p=Math.max(1,current-2); p<=Math.min(totalPages,current+2); p++){
                $wrap.append(mk(p, p, p===current?'current':''));
            }
            if (current < totalPages) $wrap.append(mk(current+1, 'â€º'));
        }

        function rowsEventBinding($scope){
            // GÃ©nÃ©rer ALT depuis titre (local)
            $scope.on('click', '.generate-alt', function(){
                const $tr = $(this).closest('tr');
                const title = $tr.find('.title-field').val() || '';
                $tr.find('.alt-field').val(title);
            });

            // Recalc usage (AJAX unitaire)
            $scope.on('click', '.refresh-usage', function(e){
                e.preventDefault();
                const $btn = $(this);
                const $tr  = $btn.closest('tr');
                const id   = $tr.data('id');
                const $cnt = $tr.find('.usage-count');

                $btn.prop('disabled', true).text('â€¦');
                $.post(ajaxurl, { action:'dnc_recalc_usage', nonce, id }, function(resp){
                    if(resp && resp.success){
                        $cnt.text(resp.data.count).attr('title', resp.data.tooltip);
                    } else {
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Ã‰chec recalcul.');
                    }
                }).fail(()=>alert('Erreur rÃ©seau.'))
                  .always(()=> $btn.prop('disabled', false).text('â†»'));
            });

            // Supprimer orpheline (AJAX)
            $scope.on('click', '.delete-orphan', function(e){
                e.preventDefault();
                if(!confirm('Supprimer dÃ©finitivement cette image orpheline ?')) return;
                const $tr = $(this).closest('tr');
                const id  = $tr.data('id');
                $.post(ajaxurl, { action:'dnc_delete_orphan', nonce, id }, function(resp){
                    if(resp && resp.success){
                        $tr.fadeOut(150, function(){ $(this).remove(); });
                    } else {
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Ã‰chec de suppression.');
                    }
                }).fail(()=>alert('Erreur rÃ©seau.'));
            });

            // Suppression image gÃ©nÃ©rique (unused)
            $scope.on('click', '.delete-attachment', function(e){
                e.preventDefault();
                if(!confirm('Supprimer dÃ©finitivement cette image ?')) return;
                const $tr = $(this).closest('tr');
                const id  = $tr.data('id');
                $.post(ajaxurl, { action:'dnc_delete_attachment', nonce, id }, function(resp){
                    if(resp && resp.success){
                        $tr.fadeOut(150, function(){ $(this).remove(); });
                    } else {
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Ã‰chec de suppression.');
                    }
                }).fail(()=>alert('Erreur rÃ©seau.'));
            });

        }

        // ---- Chargement AJAX d'une page
        function loadTabPage(tab){
            const page = state.page[tab];
            const perPage = state.perPage;
            const search = state.search;
            const onlyMissingAlt = state.onlyMissingAlt;

            let $tbody, $summary, $pager, colspan = 4;
            if (tab==='valid') {
                $tbody = $('#media-editor-table tbody');
                $summary = $('#valid-summary');
                $pager = $('#valid-pagination');
                colspan = 4;
            } else if (tab==='orphans') {
                $tbody = $('#orphans-table tbody');
                $summary = $('#orphans-summary');
                $pager = $('#orphans-pagination');
                colspan = 4;
            } else { // unused
                $tbody = $('#unused-table tbody');
                $summary = $('#unused-summary');
                $pager = $('#unused-pagination');
                colspan = 5;
            }

            $tbody.html('<tr><td colspan="'+colspan+'">Chargementâ€¦</td></tr>');
            $summary.text('');
            $pager.empty();

            $.post(ajaxurl, {
                action: 'dnc_media_list',
                nonce,
                tab,
                page,
                per_page: perPage,
                search,
                only_missing_alt: onlyMissingAlt ? 1 : 0
            }, function(resp){
                if(!resp || !resp.success){ $tbody.html('<tr><td colspan="'+colspan+'">Erreur de chargement.</td></tr>'); return; }
                $tbody.html(resp.data.rows_html);
                rowsEventBinding($tbody);
                $summary.text(resp.data.summary || '');
                renderPagination($pager, resp.data.current_page, resp.data.total_pages);
            }).fail(function(){
                $tbody.html('<tr><td colspan="'+colspan+'">Erreur rÃ©seau.</td></tr>');
            });
        }

        // ---- Onglets
        $('.nav-tab').on('click', function(e){
            e.preventDefault();
            const tab = $(this).data('tab');
            if (tab === state.activeTab) return;
            state.activeTab = tab;
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.dnc-tab').hide();
            $('#dnc-tab-'+tab).show();
            loadTabPage(tab);
        });

        // ---- Pagination clic
        $('#valid-pagination, #orphans-pagination, #unused-pagination').on('click', '.page-link', function(e){
          e.preventDefault();
          const p = parseInt($(this).data('page'),10);
          const wrap = $(this).closest('.tab-pagination').attr('id');
          const idToTab = {
            'valid-pagination': 'valid',
            'orphans-pagination': 'orphans',
            'unused-pagination': 'unused'
          };
          const tab = idToTab[wrap] || state.activeTab;
          state.page[tab] = p;
          loadTabPage(tab);
        });
        
        // ---- Filtres
        $('#per-page').on('change', function(){
            state.perPage = parseInt($(this).val(),10) || 50;
            state.page[state.activeTab] = 1;
            loadTabPage(state.activeTab);
        });

        $('#filter-missing-alt').on('change', function(){
          state.onlyMissingAlt = $(this).is(':checked');
          state.page.valid = 1;
          state.page.unused = 1; // <-- pour que l’onglet unused reparte de la page 1
          if (state.activeTab === 'valid' || state.activeTab === 'unused') {
            loadTabPage(state.activeTab);
          }
        });

        $('#image-search').on('input', function(){
            const v = $(this).val().trim();
            state.search = v;
            state.page[state.activeTab] = 1;
            clearTimeout(state.debounce);
            state.debounce = setTimeout(()=>loadTabPage(state.activeTab), 220);
        });

        // ---- GÃ©nÃ©rer tous les ALT vides (page courante)
        $('#generate-all-alt').on('click', function(){
            const $rows = $('#media-editor-table tbody tr');
            let changed=0;
            $rows.each(function(){
                const $tr = $(this);
                const $alt = $tr.find('.alt-field');
                if ($alt.length && !$alt.val()){
                    const title = $tr.find('.title-field').val() || '';
                    $alt.val(title);
                    changed++;
                }
            });
            if(!changed) alert("Aucun ALT vide sur cette page.");
        });

        // ---- Recalc tout (page courante)
        $('#dnc-recalc-page').on('click', function(e){
            e.preventDefault();
            const $btn = $(this);
            const tab = state.activeTab;
            if (tab !== 'valid') return; // On ne recalcule que pour l’onglet "valid"

            const $rows = $('#media-editor-table tbody tr');
            const ids = [];
            $rows.each(function(){ 
                const id = $(this).data('id'); 
                if (id) ids.push(id); 
            });
            if (!ids.length) return;

            $btn.prop('disabled', true).text('Recalcul en cours…');

            $.post(ajaxurl, { 
                action: 'dnc_recalc_usage_batch',
                nonce,
                ids
            }, function(resp){
                if (!resp || !resp.success || !resp.data || !resp.data.usage) {
                    alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Échec recalcul.');
                    return;
                }
                const usage = resp.data.usage;
                // Met à jour chaque ligne
                for (const id in usage) {
                    const data = usage[id];
                    const $tr = $('#media-editor-table tbody tr[data-id="'+id+'"]');
                    $tr.find('.usage-count').text(data.count).attr('title', data.tooltip);
                }
            }).fail(function(){
                alert('Erreur réseau.');
            }).always(function(){
                $btn.prop('disabled', false).text('↻ Tout recalculer (page)');
            });
        });


        // ---- Chargement initial
        loadTabPage('valid');
    });
    </script>
    <script>
jQuery(function($){
    // ... (ton JS existant) ...

    function dnc_formatProgress(done, total, errors) {
        const pct = total ? Math.round((done/total)*100) : 0;
        let txt = `Recalcul : ${done}/${total} (${pct}%)`;
        if (errors > 0) txt += ` â€” ${errors} Ã©checs`;
        return txt;
    }

    $('#dnc-recalc-all').on('click', function(e){
        e.preventDefault();
        const $btn = $(this);
        const $prog = $('#dnc-recalc-progress');

        const $rows = $('#media-editor-table tbody tr:visible');
        const total = $rows.length;
        let done = 0, errors = 0;

        if (!total) {
            $prog.text('Aucune image Ã  recalculer.');
            return;
        }

        $btn.prop('disabled', true).text('Recalcul en coursâ€¦');
        $prog.text(dnc_formatProgress(0, total, 0));

        // DÃ©sactive les boutons unitaires le temps du batch
        $('.refresh-usage').prop('disabled', true);

        // Processus sÃ©quentiel pour Ã©viter de saturer PHP
        const processNext = function(idx){
            if (idx >= total) {
                $btn.prop('disabled', false).text('â†» Tout recalculer');
                $('.refresh-usage').prop('disabled', false);
                $prog.text(`TerminÃ© : ${done}/${total}` + (errors ? ` â€” ${errors} erreurs` : ''));
                return;
            }
            const $tr  = $rows.eq(idx);
            const id   = $tr.data('id');
            const $cnt = $tr.find('.usage-count');

            $.post(ajaxurl, {
                action: 'dnc_recalc_usage',
                nonce : '<?php echo esc_js($nonce); ?>',
                id    : id
            }, function(resp){
                if (resp && resp.success) {
                    $cnt.text(resp.data.count).attr('title', resp.data.tooltip);
                } else {
                    errors++;
                }
            }).fail(function(){
                errors++;
            }).always(function(){
                done++;
                $prog.text(dnc_formatProgress(done, total, errors));
                // petite pause pour respirer si besoin (facultatif)
                setTimeout(function(){ processNext(idx+1); }, 60);
            });
        };

        processNext(0);
    });
});
</script>

    <?php
}
