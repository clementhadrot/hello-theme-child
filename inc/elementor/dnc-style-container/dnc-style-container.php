<?php
/**
 * Charger dans functions.php via :
 * require get_stylesheet_directory() . '/inc/elementor/dnc-style-container/bootstrap.php';
 */

/** ------------------------------------------------------------------------
 * Options (valeurs = SUFFIXES pour prefix_class)
 * ------------------------------------------------------------------------- */
if (!function_exists('dnc_style_container_options')) {
  function dnc_style_container_options() {
    static $opts;
    if ($opts) return $opts;

    $path = get_stylesheet_directory() . '/inc/elementor/dnc-style-container/options.php';
    $opts = file_exists($path) ? include $path : [];

    // Attendu : clés = suffixes sûrs (ex: 'primary', 'secondary', 'star', 'left', 'right', 'top', 'bottom', ...)
    $opts += [
      'css_classes'        => ['' => __('(aucune)', 'dnc-theme')],
      'icons'              => ['' => __('(par défaut)', 'dnc-theme')],
      'icons-position-hor' => ['' => __('(auto)', 'dnc-theme')],
      'icons-position-ver' => ['' => __('(auto)', 'dnc-theme')],
    ];
    return $opts;
  }
}

/** ------------------------------------------------------------------------
 * Contrôles (Section + Container) — uniquement via prefix_class (pas de data-*)
 * ------------------------------------------------------------------------- */
add_action('elementor/init', function () {
  add_action('elementor/element/section/section_layout/before_section_end', 'dnc_add_style_controls', 20, 2);
  add_action('elementor/element/container/section_layout/before_section_end', 'dnc_add_style_controls', 20, 2);
});

function dnc_add_style_controls($element, $args) {
  $opts = dnc_style_container_options();

  // Classe racine (.dnc-root--{val})
  $element->add_control(
    'dnc_root_css_class',
    [
      'label'              => __('Couleur de fond', 'dnc-theme'),
      'type'               => \Elementor\Controls_Manager::SELECT2,
      'options'            => $opts['css_classes'],
      'default'            => '',
      'label_block'        => true,
      'render_type'        => 'template',
      'frontend_available' => true,
      'prefix_class'       => '',
    ]
  );

  // Icône (.dnc-icon--{val})
  $element->add_control(
    'dnc_custom_icon',
    [
      'label'              => __('Icône de fond', 'dnc-theme'),
      'type'               => \Elementor\Controls_Manager::SELECT2,
      'options'            => $opts['icons'],
      'default'            => '',
      'label_block'        => true,
      'render_type'        => 'template',
      'frontend_available' => true,
      'prefix_class'       => '',
    ]
  );

  // Position horizontale (.dnc-icon-h--{val})
  $element->add_control(
    'dnc_custom_icon_position_hor',
    [
      'label'              => __('Position de l’icône (horizontale)', 'dnc-theme'),
      'type'               => \Elementor\Controls_Manager::SELECT2,
      'options'            => $opts['icons-position-hor'],
      'default'            => '',
      'label_block'        => true,
      'render_type'        => 'template',
      'frontend_available' => true,
      'prefix_class'       => 'dnc-icon-h-',
    ]
  );

  // Position verticale (.dnc-icon-v--{val})
  $element->add_control(
    'dnc_custom_icon_position_ver',
    [
      'label'              => __('Position de l’icône (verticale)', 'dnc-theme'),
      'type'               => \Elementor\Controls_Manager::SELECT2,
      'options'            => $opts['icons-position-ver'],
      'default'            => '',
      'label_block'        => true,
      'render_type'        => 'template',
      'frontend_available' => true,
      'prefix_class'       => 'dnc-icon-v-',
    ]
  );
}

/** ------------------------------------------------------------------------
 * Styles : register/enqueue
 * ------------------------------------------------------------------------- */
function dnc_style_container_register_style() {
  $default_rel = '/inc/elementor/dnc-style-container/assets/styles-container.css';
  $css_path = get_stylesheet_directory() . $default_rel;
  $css_uri  = get_stylesheet_directory_uri() . $default_rel;

  $css_path = apply_filters('dnc_style_container_css_path', $css_path);
  $css_uri  = apply_filters('dnc_style_container_css_uri',  $css_uri);

  if (file_exists($css_path)) {
    $ver = (string) @filemtime($css_path) ?: null;
    wp_register_style('dnc-style-container', $css_uri, ['elementor-frontend'], $ver);
  }
}
add_action('wp_enqueue_scripts', 'dnc_style_container_register_style', 5);
add_action('elementor/editor/after_enqueue_styles', 'dnc_style_container_register_style', 1);

function dnc_style_container_enqueue_style_once() {
  static $done = false;
  if ($done) return;

  if (!wp_style_is('dnc-style-container', 'registered')) {
    dnc_style_container_register_style();
  }
  if (wp_style_is('dnc-style-container', 'registered')) {
    wp_enqueue_style('dnc-style-container');
    $done = true;
  }
}

function dnc_style_container_editor_force_enqueue() {
  $should = apply_filters('dnc_style_container_editor_enqueue', true);
  if ($should) {
    dnc_style_container_register_style();
    dnc_style_container_enqueue_style_once();
  }
}
add_action('elementor/editor/after_enqueue_styles', 'dnc_style_container_editor_force_enqueue', 20);

add_action('elementor/preview/enqueue_styles', 'dnc_style_container_register_style', 1);
add_action('elementor/preview/enqueue_styles', 'dnc_style_container_editor_force_enqueue', 20);

add_action('elementor/frontend/after_enqueue_styles', 'dnc_style_container_register_style', 1);
add_action('elementor/frontend/after_enqueue_styles', 'dnc_style_container_enqueue_style_once', 20);

/** ------------------------------------------------------------------------
 * Frontend : on garde un hook pour déclencher l'enqueue à la volée si besoin
 * (aucun add_render_attribute : TOUT passe par prefix_class)
 * ------------------------------------------------------------------------- */
function dnc_maybe_enqueue_on_render($element) {
  $type = $element->get_type();
  if ($type !== 'section' && $type !== 'container') return;
  if (!method_exists($element, 'get_settings_for_display')) return;

  $s = $element->get_settings_for_display();
  $has_any =
    (!empty($s['dnc_root_css_class'])) ||
    (!empty($s['dnc_custom_icon'])) ||
    (!empty($s['dnc_custom_icon_position_hor'])) ||
    (!empty($s['dnc_custom_icon_position_ver']));

  if ($has_any) dnc_style_container_enqueue_style_once();
}
add_action('elementor/frontend/element/before_render', 'dnc_maybe_enqueue_on_render');
