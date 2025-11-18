<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Enqueue des assets thème (CSS + JS) pour la barre de filtre + refresh fragment.
 */
add_action('wp_enqueue_scripts', function () {
    $base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-loop-tax-filter';
    wp_enqueue_style('dnc-loop-tax-filter', $base . '/assets/dnc-loop-tax-filter.css', [], '1.0.0');
    wp_enqueue_script('dnc-loop-tax-filter', $base . '/assets/dnc-loop-tax-filter-script.js', ['jquery'], '1.2.0', true);
});

/**
 * Ajoute des contrôles dans Loop Grid & Loop Carousel (Elementor Pro).
 * NOTE: fonctionne en back-office (l’éditeur) et front.
 */
function dnc_el_add_tax_filter_controls( $element, $args ) {
    if ( ! class_exists('\Elementor\Controls_Manager') ) return;

    $tax_objects = get_taxonomies(['public' => true], 'objects');
    $tax_options = [];
    foreach ($tax_objects as $slug => $obj) {
        $tax_options[$slug] = $obj->labels->singular_name ?: $obj->label;
    }

    $element->add_control('dnc_enable_tax_filter', [
        'label'        => __('Activer le filtre par taxonomie', 'dnc-theme'),
        'type'         => \Elementor\Controls_Manager::SWITCHER,
        'label_on'     => __('Oui', 'dnc-theme'),
        'label_off'    => __('Non', 'dnc-theme'),
        'return_value' => 'yes',
        'default'      => '',
    ]);

    $element->add_control('dnc_filter_taxonomy', [
        'label'     => __('Taxonomie', 'dnc-theme'),
        'type'      => \Elementor\Controls_Manager::SELECT,
        'options'   => $tax_options,
        'condition' => ['dnc_enable_tax_filter' => 'yes'],
    ]);

    $element->add_control('dnc_filter_param', [
        'label'       => __('Paramètre d’URL', 'dnc-theme'),
        'type'        => \Elementor\Controls_Manager::TEXT,
        'default'     => 'filtre',
        'description' => __('Ex: ?filtre=slug-du-terme', 'dnc-theme'),
        'condition'   => ['dnc_enable_tax_filter' => 'yes'],
    ]);

    $element->add_control('dnc_filter_label', [
        'label'       => __('Nom du filtre (label)', 'dnc-theme'),
        'type'        => \Elementor\Controls_Manager::TEXT,
        'default'     => __('Filtrer par', 'dnc-theme'),
        'placeholder' => __('Nom du filtre', 'dnc-theme'),
        'condition'   => ['dnc_enable_tax_filter' => 'yes'],
    ]);

    $element->add_control('dnc_filter_notice', [
        'type'             => \Elementor\Controls_Manager::RAW_HTML,
        'raw'              => __('<strong>Important :</strong> renseignez un <em>Query ID</em> unique dans <em>Avancé → Query ID</em> (ex. <code>dnc_tax_filter</code>).', 'dnc-theme'),
        'content_classes'  => 'elementor-panel-alert elementor-panel-alert-info',
        'condition'        => ['dnc_enable_tax_filter' => 'yes'],
    ]);
}
add_action('elementor/element/loop-grid/section_query/before_section_end', 'dnc_el_add_tax_filter_controls', 10, 2);
add_action('elementor/element/loop-carousel/section_query/before_section_end', 'dnc_el_add_tax_filter_controls', 10, 2);

/**
 * Rendu de l’UI de filtre AVANT le widget + data-attrs pour le JS.
 * On garde le filtrage serveur via query param et on activera le refresh partiel côté JS.
 */
add_action('elementor/frontend/widget/before_render', function ($widget) {
    $name = $widget->get_name();

    if ( ! in_array($name, ['loop-grid', 'loop-carousel'], true) ) return;

    $s = $widget->get_settings_for_display();
    if ( empty($s['dnc_enable_tax_filter']) || 'yes' !== $s['dnc_enable_tax_filter'] ) return;
  
    $taxonomy = !empty($s['dnc_filter_taxonomy']) ? $s['dnc_filter_taxonomy'] : '';
    $param    = !empty($s['dnc_filter_param']) ? sanitize_key($s['dnc_filter_param']) : 'filtre';
    $label    = isset($s['dnc_filter_label']) ? $s['dnc_filter_label'] : __('Filtrer par', 'dnc-theme');
    $query_id = isset($s['post_query_query_id']) ? sanitize_key($s['post_query_query_id']) : '';
   
    if ( ! $taxonomy || ! taxonomy_exists($taxonomy) ||  ! $query_id ) return; 
    

    // Récupère l’ID interne du widget dans la page pour pouvoir retrouver le fragment à remplacer côté JS.
    $widget_id = $widget->get_id();
    $current   = isset($_GET[$param]) ? sanitize_text_field(wp_unslash($_GET[$param])) : '';

    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
    if ( is_wp_error($terms) || empty($terms) ) return;

    $base_url = remove_query_arg($param);

    echo '<div class="dnc-tax-filter"'
        .' data-tax="'.esc_attr($taxonomy).'"'
        .' data-param="'.esc_attr($param).'"'
        .' data-widget-id="'.esc_attr($widget_id).'"'
        .' data-widget-type="'.esc_attr($name).'"'
        .'>';

    echo '<span class="dnc-tax-filter__label">'.esc_html($label).'</span>';

    $all_active = $current ? '' : ' is-active';
    echo '<a class="dnc-tax-filter__link'.$all_active.'" href="'.esc_url($base_url).'" data-term="">'.esc_html__('Tous','dn').'</a>';

    foreach ($terms as $term) {
        $is_active = ($current === $term->slug) ? ' is-active' : '';
        $url = add_query_arg([$param => $term->slug], $base_url);
        echo '<a class="dnc-tax-filter__link'.$is_active.'" href="'.esc_url($url).'" data-term="'.esc_attr($term->slug).'">'.esc_html($term->name).'</a>';
    }
    echo '</div>';
}, 10);

/**
 * Filtrage de la requête de posts du widget via le Query ID.
 * (Compatibilité : anciens & nouveaux hooks Elementor/Elementor Pro)
 */
function dnc_bind_elementor_query_filter( $query, $taxonomy, $param ) {
    if ( empty($taxonomy) || ! taxonomy_exists($taxonomy) ) return;
    $value = isset($_GET[$param]) ? sanitize_text_field(wp_unslash($_GET[$param])) : '';
    if ( '' === $value ) return;

    $tax_query = (array) $query->get('tax_query');
    $tax_query[] = [
        'taxonomy' => $taxonomy,
        'field'    => 'slug',
        'terms'    => array_map('sanitize_title', explode(',', $value)),
        'operator' => 'IN',
    ];
    if ( count($tax_query) > 1 ) {
        $tax_query['relation'] = 'AND';
    }
    $query->set('tax_query', $tax_query);
}

/**
 * On accroche dynamiquement un hook par widget (via before_render ci-dessus).
 * Pour compatibilité, on gère à la fois:
 * - 'elementor/query/{query_id}' (Elementor)
 * - 'elementor_pro/posts/query/{query_id}' (Elementor Pro)
 */
add_action('elementor/frontend/widget/before_render', function($widget){
    $name = $widget->get_name();
    if ( ! in_array($name, ['loop-grid','loop-carousel'], true) ) return;

    $s = $widget->get_settings_for_display();
    if ( empty($s['dnc_enable_tax_filter']) || 'yes' !== $s['dnc_enable_tax_filter'] ) return;

    $taxonomy = !empty($s['dnc_filter_taxonomy']) ? $s['dnc_filter_taxonomy'] : '';
    $param    = !empty($s['dnc_filter_param']) ? sanitize_key($s['dnc_filter_param']) : 'filtre';
    $query_id = isset($s['post_query_query_id']) ? sanitize_key($s['post_query_query_id']) : '';

    if ( ! $taxonomy || ! $query_id ) return;

    // Hook Elementor (free)
    add_action("elementor/query/{$query_id}", function($q) use($taxonomy,$param){ dnc_bind_elementor_query_filter($q,$taxonomy,$param); }, 10, 1);
    // Hook Elementor Pro
    add_action("elementor_pro/posts/query/{$query_id}", function($q) use($taxonomy,$param){ dnc_bind_elementor_query_filter($q,$taxonomy,$param); }, 10, 1);
}, 11);
