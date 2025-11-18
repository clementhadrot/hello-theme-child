<?php // Liste des shorcodes du thème 

add_shortcode('parent-menu', 'getParentMenu');

function getParentMenu() {
    $post_id = _pm_current_post_id();
    if (!$post_id) return '';

    // Cache par requête pour éviter de rescanner les menus dans un loop
    static $pm_cache = [];
    if (isset($pm_cache[$post_id])) {
        return $pm_cache[$post_id];
    }

    $post = get_post($post_id);
    if (!$post) return '';

    // Si ce n'est PAS une page → libellé du post type
    if ($post->post_type !== 'page') {
        $pto = get_post_type_object($post->post_type);
        $out = '';
        if ($pto && !empty($pto->labels->singular_name)) {
            $out = $pto->labels->singular_name;
        } elseif ($pto && !empty($pto->labels->name)) {
            $out = $pto->labels->name;
        } else {
            $out = $post->post_type;
        }
        $out = esc_html($out);
        $pm_cache[$post_id] = $out;
        return $out;
    }

    // C'est une page → chercher son parent top-level dans les menus (toutes locations)
    $title = _pm_find_menu_top_parent_title_for_post($post_id);
    if ($title !== null && $title !== '') {
        $out = esc_html($title);
        $pm_cache[$post_id] = $out;
        return $out;
    }

    // Fallback : ancêtre racine (ou titre de la page)
    $ancestor_title = _pm_get_top_ancestor_title($post_id);
    $out = $ancestor_title !== '' ? esc_html($ancestor_title) : esc_html(get_the_title($post_id));
    $pm_cache[$post_id] = $out;
    return $out;
}

/**
 * Récupère l'ID du post courant en priorité depuis la boucle (global $post),
 * sinon depuis l’objet query (utile hors boucle).
 */
function _pm_current_post_id() {
    global $post;
    if ($post instanceof WP_Post && !empty($post->ID)) {
        return (int) $post->ID;
    }
    $qid = get_queried_object_id();
    return $qid ? (int) $qid : 0;
}

/**
 * Retourne le titre du parent top-level dans n'importe quel menu enregistré.
 * Si aucun menu ne contient l'item, retourne null.
 */
function _pm_find_menu_top_parent_title_for_post($post_id) {
    $locations = get_nav_menu_locations();
    if (!is_array($locations) || empty($locations)) return null;

    foreach ($locations as $menu_term_id) {
        $menu_obj = wp_get_nav_menu_object($menu_term_id);
        if (!$menu_obj) continue;

        $items = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);
        if (empty($items)) continue;

        // Index des items par ID pour remonter les parents rapidement
        $by_id = [];
        foreach ($items as $it) $by_id[$it->ID] = $it;

        // Trouver l'item qui pointe vers cette page
        $current = null;
        foreach ($items as $it) {
            if (isset($it->object_id, $it->object) && (int)$it->object_id === (int)$post_id && $it->object === 'page') {
                $current = $it;
                break;
            }
        }
        if (!$current) continue;

        // Remonter jusqu'au top-level
        $top = $current;
        while (!empty($top->menu_item_parent) && isset($by_id[$top->menu_item_parent])) {
            $top = $by_id[$top->menu_item_parent];
        }

        return isset($top->title) ? $top->title : '';
    }
    return null;
}

/**
 * Renvoie le titre de l’ancêtre racine d’une page, sinon le titre de la page.
 */
function _pm_get_top_ancestor_title($post_id) {
    $ancestor_id = $post_id;
    while ($parent_id = wp_get_post_parent_id($ancestor_id)) {
        $ancestor_id = $parent_id;
    }
    $title = get_the_title($ancestor_id);
    return is_string($title) ? $title : '';
}

add_shortcode('legende-copyright-hero','LegendeCopyrightHero');

function LegendeCopyrightHero(){
    global $post;
    
    $legend = get_field('legende_image_hero');
    $copyright = false;
    $urlimage = get_field('image_hero');
    if ($urlimage) {
        $id_image = attachment_url_to_postid($urlimage);
        $caption = wp_get_attachment_caption($id_image);
        if(!empty($caption))
            $copyright = true;
    }
    
    if($copyright && !empty($legend)){
        echo $legend.' <span class="copyright"></span>';
    } elseif(!empty($legend)) {
        echo $legend;
    } elseif($copyright){
        echo '<span class="copyright"></span>';
    } else {
        return;
    }
    
}