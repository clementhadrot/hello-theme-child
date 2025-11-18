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
 * Construit une map [page_id => titre du parent top-level] pour tous les menus.
 * Traité une seule fois par requête grâce à un cache statique.
 */
function _pm_get_page_menu_top_titles() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];
    $locations = get_nav_menu_locations();
    if (!is_array($locations) || empty($locations)) return $map;

    $processed_menus = [];

    foreach ($locations as $menu_term_id) {
        if (isset($processed_menus[$menu_term_id])) {
            continue;
        }

        $menu_obj = wp_get_nav_menu_object($menu_term_id);
        if (!$menu_obj) continue;

        $processed_menus[$menu_obj->term_id] = true;

        $items = wp_get_nav_menu_items($menu_obj->term_id, ['update_post_term_cache' => false]);
        if (empty($items)) continue;

        // Index des items par ID pour remonter les parents rapidement
        $by_id = [];
        foreach ($items as $it) {
            $by_id[$it->ID] = $it;
        }

        foreach ($items as $it) {
            if (empty($it->object_id) || $it->object !== 'page') {
                continue;
            }

            $top = $it;
            $visited = [];

            while (!empty($top->menu_item_parent) && isset($by_id[$top->menu_item_parent])) {
                // Empêcher des boucles infinies si des liens parents sont corrompus
                if (isset($visited[$top->menu_item_parent])) {
                    break;
                }

                $visited[$top->menu_item_parent] = true;
                $top = $by_id[$top->menu_item_parent];
            }

            if (isset($top->title) && $top->title !== '') {
                $page_id = (int) $it->object_id;
                if (!isset($map[$page_id])) {
                    // On garde la première occurrence pour éviter d'écraser un emplacement déjà trouvé
                    $map[$page_id] = $top->title;
                }
            }
        }
    }

    return $map;
}

/**
 * Retourne le titre du parent top-level dans n'importe quel menu enregistré.
 * Si aucun menu ne contient l'item, retourne null.
 */
function _pm_find_menu_top_parent_title_for_post($post_id) {
    $map = _pm_get_page_menu_top_titles();
    return $map[$post_id] ?? null;
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
    $legend = get_field('legende_image_hero');
    $legend_text = is_string($legend) ? trim($legend) : '';
    $copyright = false;
    static $attachment_caption_cache = [];
    static $attachment_id_cache = [];
    $image_field = get_field('image_hero');
    $urlimage = is_array($image_field) && isset($image_field['url']) ? $image_field['url'] : $image_field;

    if (is_string($urlimage) && $urlimage !== '') {
        if (isset($attachment_id_cache[$urlimage])) {
            $id_image = $attachment_id_cache[$urlimage];
        } else {
            $id_image = attachment_url_to_postid($urlimage);
            $attachment_id_cache[$urlimage] = $id_image;
        }

        if ($id_image) {
            if (isset($attachment_caption_cache[$id_image])) {
                $caption = $attachment_caption_cache[$id_image];
            } else {
                $caption = wp_get_attachment_caption($id_image);
                $attachment_caption_cache[$id_image] = $caption;
            }
            if (is_string($caption) && $caption !== '') {
                $copyright = true;
            }
        }
    }

    $output = '';

    if ($legend_text !== '') {
        $output = esc_html($legend_text);
    }

    if ($copyright) {
        $output .= $output !== '' ? ' ' : '';
        $output .= '<span class="copyright"></span>';
    }

    return $output;
}
