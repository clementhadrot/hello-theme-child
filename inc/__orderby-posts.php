<?php
/**
 * TDM — Elementor : tri par mois courant > +1 > +2, avec sticky au-dessus
 * Méta utilisée : 'top-display-mois' (valeurs "1..12" OU "01..12")
 * Deux modes :
 *   - Query ID = 'mois_priorise' : priorise (sans exclure le reste)
 *   - Query ID = 'mois_filtre'   : filtre (affiche uniquement ces 3 mois)
 */

/** Helper : renvoie la fenêtre des 3 mois aux formats "1..12" ET "01..12" */
function tdm_months_3(): array {
    $n = (int) current_time('n'); // TZ WordPress
    $m = [$n, ($n % 12) + 1, ((($n % 12) + 1) % 12) + 1];
    $vals = [];
    foreach ($m as $x) {
        $vals[] = (string) $x;                                // "10" ou "1"
        $vals[] = str_pad((string) $x, 2, '0', STR_PAD_LEFT); // "10" ou "01"
    }
    return array_values(array_unique($vals));
}

// Helper : valeurs à chercher dans un tableau sérialisé ACF (avec guillemets)
function tdm_months_like_values(): array {
    $n = (int) current_time('n');
    $m = [$n, ($n % 12) + 1, ((($n % 12) + 1) % 12) + 1];
    $vals = [];
    foreach ($m as $x) {
        $vals[] = '"' . (string)$x . '"';                                  // "11"
        $vals[] = '"' . str_pad((string)$x, 2, '0', STR_PAD_LEFT) . '"';   // "01"
    }
    return array_values(array_unique($vals));
}

/**
 * Marque toutes les requêtes secondaires d’articles pour l’ordre custom
 */
add_action('pre_get_posts', function(WP_Query $q){
    if (is_admin() || $q->is_main_query()) return;

    $pt = $q->get('post_type', 'post');
    $only_posts = is_string($pt) ? ($pt === 'post') : (is_array($pt) && count($pt) === 1 && $pt[0] === 'post');
    if (!$only_posts) return;

    $q->set('tdm_month_vals', tdm_months_3());
    $q->set('tdm_custom_order', 1);
    $q->set('ignore_sticky_posts', 1);
}, 10);

/* ======================
 * B) FILTRER (3 mois) — version ACF sérialisé
 * ======================
 * Dans Elementor : Query ID = mois_filtre
 */
add_action('elementor/query/mois_filtre', function (WP_Query $q) {
    // Construit une meta_query OR avec LIKE sur chaque valeur guill.-ée
    $likes = tdm_months_like_values();

    $mq = ['relation' => 'OR'];
    foreach ($likes as $val) {
        $mq[] = [
            'key'     => 'top-display-mois',
            'value'   => $val,   // ex: `"11"`
            'compare' => 'LIKE', // match dans le tableau sérialisé
        ];
    }

    $q->set('meta_query', $mq);

    // On garde le même ordre que "prioriser"
    $q->set('ignore_sticky_posts', 1);
    $q->set('orderby', 'none');
    $q->set('tdm_month_vals', tdm_months_3());
    $q->set('tdm_custom_order', 1);
});

/** ===== ORDER BY custom commun aux deux modes ===== */
add_filter('posts_clauses', function ($clauses, WP_Query $q) {
    if (!$q->get('tdm_custom_order')) return $clauses;

    global $wpdb;

    // LEFT JOIN unique sur la méta (pour ne PAS exclure les posts sans méta)
    if (strpos($clauses['join'] ?? '', 'tdm_meta_mois') === false) {
        $clauses['join'] .= $wpdb->prepare(
            " LEFT JOIN {$wpdb->postmeta} AS tdm_meta_mois
              ON (tdm_meta_mois.post_id = {$wpdb->posts}.ID AND tdm_meta_mois.meta_key = %s) ",
            'top-display-mois'
        );
    }

    // Calcul du mois courant et des deux suivants (pour pondérer le tri)
    $now = (int) current_time('n');
    $m1  = (string) $now;                    $m1p = str_pad($m1, 2, '0', STR_PAD_LEFT);
    $m2  = (string) (($now % 12) + 1);       $m2p = str_pad($m2, 2, '0', STR_PAD_LEFT);
    $m3  = (string) ((($now % 12) + 1) % 12 + 1); $m3p = str_pad($m3, 2, '0', STR_PAD_LEFT);

    // Clause sticky en premier
    $sticky_clause = '0';
    $sticky = get_option('sticky_posts');
    if ($sticky && is_array($sticky) && !empty($sticky)) {
        $sticky_clause = "FIELD({$wpdb->posts}.ID, " . implode(',', array_map('intval', $sticky)) . ") DESC";
    }

    // Priorisation fine : mois courant (score 3) > mois+1 (2) > mois+2 (1) > le reste (0)
    $prio_clause = "
      CASE
        WHEN tdm_meta_mois.meta_value IN ('{$m1}','{$m1p}') THEN 3
        WHEN tdm_meta_mois.meta_value IN ('{$m2}','{$m2p}') THEN 2
        WHEN tdm_meta_mois.meta_value IN ('{$m3}','{$m3p}') THEN 1
        ELSE 0
      END DESC
    ";

    // Tri secondaire : conserve l’orderby existant si présent, sinon date DESC
    $secondary = trim((string) ($clauses['orderby'] ?? ''));
    if ($secondary === '' || stripos($secondary, 'rand') !== false) {
        $secondary = "{$wpdb->posts}.post_date DESC";
    }

    $clauses['orderby'] = "{$sticky_clause}, {$prio_clause}, {$secondary}";
    return $clauses;
}, 10, 2);
