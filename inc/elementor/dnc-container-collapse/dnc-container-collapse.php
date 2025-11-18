<?php
/**
 * Plugin Name: Elementor – Container Collapse (zone ciblée)
 * Description: Ajoute une option "Collapse" aux containers/sections Elementor pour replier une zone ciblée + bouton Ouvrir/Fermer.
 * Version: 1.0.0
 * Author: DN Consultants
 */

if ( ! defined('ABSPATH') ) exit;

use Elementor\Controls_Manager;

add_action('wp_enqueue_scripts', function () {
    $base_uri  = get_stylesheet_directory_uri() . '/inc/elementor/dnc-container-collapse/';
    $base_path = get_stylesheet_directory()     . '/inc/elementor/dnc-container-collapse/';

    $css_uri  = $base_uri  . 'assets/dnc-collapse.css';
    $css_path = $base_path . 'assets/dnc-collapse.css';
    $js_uri   = $base_uri  . 'assets/dnc-collapse.js';
    $js_path  = $base_path . 'assets/dnc-collapse.js';

    $css_ver = file_exists($css_path) ? filemtime($css_path) : null;
    $js_ver  = file_exists($js_path)  ? filemtime($js_path)  : null;

    wp_enqueue_style('dnc-collapse-style', $css_uri, [], $css_ver, 'all');

    wp_enqueue_script('dnc-collapse-script', $js_uri, ['jquery'], $js_ver, [
        'in_footer' => true,
        'strategy'  => 'defer',
    ]);
}, 20);

// (Optionnel) charge aussi dans l’éditeur Elementor
add_action('elementor/frontend/after_enqueue_styles', fn() => wp_enqueue_style('dnc-collapse-style'));
add_action('elementor/editor/after_enqueue_styles',   fn() => wp_enqueue_style('dnc-collapse-style'));



function dnc_add_collapse_controls( $element, $section_id ) {
    // On ajoute notre section de contrôles à la fin des réglages de mise en page
    $element->start_controls_section(
        'dnc_section_collapse',
        [
            'label' => __('Collapse (zone ciblée)', 'dnc-theme'),
            'tab'   => Controls_Manager::TAB_LAYOUT,
        ]
    );

    $element->add_control(
        'dnc_enable_collapse',
        [
            'label'        => __('Activer le collapse', 'dnc-theme'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __('Oui', 'dnc-theme'),
            'label_off'    => __('Non', 'dnc-theme'),
            'return_value' => 'yes',
            'default'      => '',
        ]
    );

    $element->add_control(
        'dnc_target_selector',
        [
            'label'       => __('Sélecteur CSS de la zone à replier', 'dnc-theme'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => '> .e-con-inner', // Container moderne
            'default'     => '> .e-con-inner',
            'description' => __('Exemples : "> .e-con-inner" (Container) • "> .elementor-container" (Section) • ".ma-zone" (widget/colonne spécifique).', 'dnc-theme'),
            'condition'   => [ 'dnc_enable_collapse' => 'yes' ],
        ]
    );

    $element->add_control(
        'dnc_default_state',
        [
            'label'     => __('État par défaut', 'dnc-theme'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'open'   => __('Ouvert', 'dnc-theme'),
                'closed' => __('Fermé', 'dnc-theme'),
            ],
            'default'   => 'open',
            'condition' => [ 'dnc_enable_collapse' => 'yes' ],
        ]
    );

    $element->add_control(
        'dnc_open_label',
        [
            'label'       => __('Libellé bouton (quand fermé)', 'dnc-theme'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('Ouvrir', 'dnc-theme'),
            'placeholder' => __('Ouvrir', 'dnc-theme'),
            'condition'   => [ 'dnc_enable_collapse' => 'yes' ],
        ]
    );

    $element->add_control(
        'dnc_close_label',
        [
            'label'       => __('Libellé bouton (quand ouvert)', 'dnc-theme'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('Fermer', 'dnc-theme'),
            'placeholder' => __('Fermer', 'dnc-theme'),
            'condition'   => [ 'dnc_enable_collapse' => 'yes' ],
        ]
    );

    $element->add_control(
        'dnc_button_position',
        [
            'label'     => __('Position du bouton', 'dnc-theme'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'before' => __('Avant la zone', 'dnc-theme'),
                'after'  => __('Après la zone', 'dnc-theme'),
            ],
            'default'   => 'before',
            'condition' => [ 'dnc_enable_collapse' => 'yes' ],
        ]
    );

        $element->add_control(
        'dnc_group_key',
        [
            'label'       => __('Group Key (même valeur pour l’ensemble de la zone)', 'dnc-theme'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('ex: bloc-offre-1', 'dnc-theme'),
            'description' => __('Utilise la même clé sur tous les containers/sections qui appartiennent à la même grande zone à replier.', 'dnc-theme'),
            'condition'   => [ 'dnc_enable_collapse' => 'yes' ],
        ]
    );

    $element->add_control(
        'dnc_role',
        [
            'label'     => __('Rôle dans le groupe', 'dnc-theme'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'auto'       => __('Auto (1er trouvé crée le bouton)', 'dnc-theme'),
                'controller' => __('Contrôleur (crée le bouton)', 'dnc-theme'),
                'member'     => __('Membre (pas de bouton)', 'dnc-theme'),
            ],
            'default'   => 'auto',
            'condition' => [ 'dnc_enable_collapse' => 'yes', 'dnc_group_key!' => '' ],
        ]
    );

    $element->add_control(
        'dnc_wrapper_selector',
        [
            'label'       => __('Wrapper de la grande zone (sélecteur CSS)', 'dnc-theme'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => ':scope', // si vide ou :scope, on calcule l’ancêtre commun
            'description' => __('Ex: .ma-grande-zone ou .elementor-container > .e-con-inner. Si vide ou ":scope", le script cherchera automatiquement l’ancêtre commun.', 'dnc-theme'),
            'condition'   => [ 'dnc_enable_collapse' => 'yes', 'dnc_group_key!' => '' ],
        ]
    );


    $element->end_controls_section();
}



// Ajoute les contrôles aux CONTAINERS (flexbox) et aux SECTIONS (legacy)
add_action('elementor/element/container/section_layout/after_section_end', 'dnc_add_collapse_controls', 10, 2);
add_action('elementor/element/section/section_layout/after_section_end', 'dnc_add_collapse_controls', 10, 2);

// Ajoute les data-* au rendu pour que le JS sache quoi faire
function dnc_attach_data_attributes( $element ) {
    if ( ! in_array( $element->get_name(), ['container', 'section'], true ) ) {
        return;
    }

    $settings = $element->get_settings_for_display();
    if ( empty($settings['dnc_enable_collapse']) || 'yes' !== $settings['dnc_enable_collapse'] ) {
        return;
    }

    

    // Valeurs
    $target   = $settings['dnc_target_selector'] ?: '> .e-con-inner';
    $state    = $settings['dnc_default_state'] ?: 'open';
    $openLbl  = $settings['dnc_open_label'] ?: __('Ouvrir', 'dnc-theme');
    $closeLbl = $settings['dnc_close_label'] ?: __('Fermer', 'dnc-theme');
    $pos      = $settings['dnc_button_position'] ?: 'before';
    $group    = $settings['dnc_group_key'] ?: '';
    $role     = $settings['dnc_role'] ?: 'auto';
    $wrapper  = $settings['dnc_wrapper_selector'] ?: '';

    $attrs = [
        'data-dnc-collapse'        => 'yes',
        'data-dnc-target'          => esc_attr($target),
        'data-dnc-default'         => esc_attr($state),
        'data-dnc-open-label'      => esc_attr($openLbl),
        'data-dnc-close-label'     => esc_attr($closeLbl),
        'data-dnc-button-position' => esc_attr($pos),
    ];

    if ( $group ) {
        $attrs['data-dnc-group']   = esc_attr($group);
        $attrs['data-dnc-role']    = esc_attr($role);
        if ( $wrapper ) {
            $attrs['data-dnc-wrapper'] = esc_attr($wrapper);
        }
    }

    // On pose des attributs data-* sur l’élément conteneur
    $element->add_render_attribute('_wrapper', $attrs);
}
add_action('elementor/frontend/container/before_render', 'dnc_attach_data_attributes');
add_action('elementor/frontend/section/before_render',   'dnc_attach_data_attributes');
