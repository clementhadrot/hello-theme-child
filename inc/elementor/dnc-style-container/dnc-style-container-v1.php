<?php
/**
 * Charger dans functions.php via:
 * require get_stylesheet_directory() . '/inc/elementor/dnc-style-container/bootstrap.php';
 */

if (!function_exists('dnc_style_container_options')) {
  function dnc_style_container_options() {
    static $opts;
    if ($opts) return $opts;
    $path = get_stylesheet_directory() . '/inc/elementor/dnc-style-container/options.php';
    $opts = file_exists($path) ? include $path : [];
    $opts += [
      'css_classes' => ['' => __('(aucune)', 'dnc-theme')],
      'icons'       => ['' => __('(par défaut)', 'dnc-theme')],
      'icons-position-hor' => ['' => __('(auto)', 'dnc-theme')],
      'icons-position-ver' => ['' => __('(auto)', 'dnc-theme')],
    ];
    return $opts;
  }
}

add_action('elementor/init', function () {
  // Ajout des contrôles UNIQUEMENT pour Section + Container
  add_action('elementor/element/section/section_layout/before_section_end', 'dnc_add_style_controls', 20, 2);
  add_action('elementor/element/container/section_layout/before_section_end', 'dnc_add_style_controls', 20, 2);

  // Injection des attributs au rendu UNIQUEMENT pour Section + Container
  add_action('elementor/frontend/section/before_render', 'dnc_apply_style_attributes');
  add_action('elementor/frontend/container/before_render', 'dnc_apply_style_attributes');
  add_action('elementor/frontend/element/before_render', 'dnc_apply_style_attributes');
});

function dnc_add_style_controls($element, $args) {
  // On ajoute deux contrôles dans la section "Mise en page" de l’élément
  $opts = dnc_style_container_options();

  $element->add_control(
    'dnc_root_css_class',
    [
      'label'   => __('Couleur de fond', 'dnc-theme'),
      'type'    => \Elementor\Controls_Manager::SELECT2,
      'options' => $opts['css_classes'],
      'default' => '',
      'label_block' => true,
      'render_type' => 'template',
    ]
  );

  $element->add_control(
    'dnc_custom_icon',
    [
      'label'   => __('Icone de fond', 'dnc-theme'),
      'type'    => \Elementor\Controls_Manager::SELECT2,
      'options' => $opts['icons'],
      'default' => '',
      'label_block' => true,
      'description' => __('Ajoute data-dnc-icon sur l’élément (pour un style CSS).', 'dnc-theme'),
      'render_type' => 'template',
    ]
  );
    
    $element->add_control(
    'dnc_custom_icon_position_hor',
    [
      'label'   => __('Position de l\'icone de fond Horizontale', 'dnc-theme'),
      'type'    => \Elementor\Controls_Manager::SELECT2,
      'options' => $opts['icons-position-hor'],
      'default' => '',
      'label_block' => true,
      'description' => __('Ajoute data-dnc-icon-position-hor sur l’élément (pour un style CSS).', 'dnc-theme'),
      'render_type' => 'template',
    ]
  );
    $element->add_control(
    'dnc_custom_icon_position_ver',
    [
      'label'   => __('Position de l\'icone de fond Verticale', 'dnc-theme'),
      'type'    => \Elementor\Controls_Manager::SELECT2,
      'options' => $opts['icons-position-ver'],
      'default' => '',
      'label_block' => true,
      'description' => __('Ajoute data-dnc-icon-position-ver sur l’élément (pour un style CSS).', 'dnc-theme'),
      'render_type' => 'template',
    ]
  );
}

/**
 * Enregistre la feuille de style (sans l’enqueuer).
 * Appelé en front et en éditeur pour que le handle existe quoi qu’il arrive.
 */
function dnc_style_container_register_style() {
  $default_rel = '/inc/elementor/dnc-style-container/assets/styles-container.css';
  $css_path = get_stylesheet_directory() . $default_rel;
  $css_uri  = get_stylesheet_directory_uri() . $default_rel;

  // Permettre de surcharger l’URI/chemin via filtres si besoin
  $css_path = apply_filters('dnc_style_container_css_path', $css_path);
  $css_uri  = apply_filters('dnc_style_container_css_uri',  $css_uri);

  if (file_exists($css_path)) {
    $ver = (string) @filemtime($css_path) ?: null;
    // Dépend d’Elementor frontend pour charger après ses styles
    wp_register_style('dnc-style-container', $css_uri, ['elementor-frontend'], $ver);
  } else {
    // On n’enregistre rien si le fichier n’existe pas (pas d’erreur bruyante)
  }
}

add_action('wp_enqueue_scripts', 'dnc_style_container_register_style', 5);
add_action('elementor/editor/after_enqueue_styles', 'dnc_style_container_register_style', 1);

/**
 * Enqueue le style une seule fois, la première fois qu’un élément éligible
 * (section/container) a des paramètres renseignés.
 */
function dnc_style_container_enqueue_style_once() {
  static $done = false;
  if ($done) return;

  // Si pas enregistré (par ex. si un autre plugin a changé l’ordre), on (re)registre vite fait.
  if (!wp_style_is('dnc-style-container', 'registered')) {
    dnc_style_container_register_style();
  }
  if (wp_style_is('dnc-style-container', 'registered')) {
    wp_enqueue_style('dnc-style-container');
    $done = true;
  }
}

/**
 * Charger le CSS automatiquement en éditeur pour l’aperçu (pratique).
 * Tu peux désactiver ce comportement en filtrant le retour à false.
 */
function dnc_style_container_editor_force_enqueue() {
  $should = apply_filters('dnc_style_container_editor_enqueue', true);
  if ($should) {
    dnc_style_container_register_style();
    dnc_style_container_enqueue_style_once();
  }
}
add_action('elementor/editor/after_enqueue_styles', 'dnc_style_container_editor_force_enqueue', 20);


function dnc_apply_style_attributes($element) {
  $type = $element->get_type();
  if ($type !== 'section' && $type !== 'container') return;
  if (!method_exists($element, 'get_settings_for_display') || !method_exists($element, 'add_render_attribute')) return;

  $settings   = $element->get_settings_for_display();
  $root_class = !empty($settings['dnc_root_css_class']) ? $settings['dnc_root_css_class'] : '';
  $icon_class = !empty($settings['dnc_custom_icon'])    ? $settings['dnc_custom_icon']    : '';
  $icon_pos_hor_class = !empty($settings['dnc_custom_icon_position_hor'])    ? $settings['dnc_custom_icon_position_hor']    : '';
  $icon_pos_ver_class = !empty($settings['dnc_custom_icon_position_ver'])    ? $settings['dnc_custom_icon_position_ver']    : '';

  if ($root_class) {
    $element->add_render_attribute('_wrapper', 'class', $root_class);
  }
  $element->add_render_attribute('_wrapper', 'data-dnc-icon', $icon_class);
  $element->add_render_attribute('_wrapper', 'data-dnc-icon-position-hor', $icon_pos_hor_class);
  $element->add_render_attribute('_wrapper', 'data-dnc-icon-position-ver', $icon_pos_ver_class);

  $should_enqueue = (bool) apply_filters(
    'dnc_style_container_should_enqueue',
    ($root_class !== '' || $icon_class !== ''),
    $settings,
    $element
  );
  if ($should_enqueue) {
    dnc_style_container_enqueue_style_once();
  }
}


// +++ AJOUT POUR CHARGER LE CSS DANS L’EDITEUR ET LA PREVIEW +++
add_action('elementor/preview/enqueue_styles', 'dnc_style_container_register_style', 1);
add_action('elementor/preview/enqueue_styles', 'dnc_style_container_editor_force_enqueue', 20);

add_action('elementor/frontend/after_enqueue_styles', 'dnc_style_container_register_style', 1);
add_action('elementor/frontend/after_enqueue_styles', 'dnc_style_container_enqueue_style_once', 20);
// +++ FIN AJOUT +++
