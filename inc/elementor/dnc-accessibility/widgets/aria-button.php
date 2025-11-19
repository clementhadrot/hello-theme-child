<?php
/**
 * Plugin Name: Elementor Button ARIA Addon (Light)
 * Description: Ajoute des champs ARIA éditables aux boutons Elementor standard et les applique sur la balise <a>.
 * Author: DN Consultants
 * Version: 1.0.0
 */

// --- 0) Vérifier Elementor et prévenir proprement en admin
add_action('plugins_loaded', function () {
    if ( ! did_action('elementor/loaded') ) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Elementor Button ARIA Addon</strong> : Elementor n\'est pas chargé. Installe/Active Elementor pour utiliser les champs ARIA sur les boutons.</p></div>';
        });
        return;
    }
}, 5);

// --- 1) Ajouter des contrôles ARIA au widget Bouton
add_action('elementor/element/button/section_button/before_section_end', function( $element ) {
    // Regrouper dans une petite sous-section « Accessibilité »
    $element->add_control(
        'aria_heading',
        [
            'type' => \Elementor\Controls_Manager::RAW_HTML,
            'raw'  => '<strong style="display:block;margin:4px 0 8px;">Accessibilité (ARIA)</strong>',
            'content_classes' => 'elementor-panel-heading-title',
        ]
    );

    // aria-label
    $element->add_control(
        'aria_label',
        [
            'label' => __('ARIA label', 'elementor-button-aria'),
            'type'  => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __('Ex. : Voir les tarifs', 'elementor-button-aria'),
            'label_block' => true,
        ]
    );

    // aria-describedby (IDs séparés par un espace)
    $element->add_control(
        'aria_describedby',
        [
            'label' => __('ARIA describedby (IDs)', 'elementor-button-aria'),
            'type'  => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __('ex: hint1 hint2', 'elementor-button-aria'),
            'description' => __('Un ou plusieurs IDs d’éléments (séparés par des espaces) contenant une aide/description.', 'elementor-button-aria'),
            'label_block' => true,
        ]
    );

    // aria-expanded + aria-controls (pour les boutons qui ouvrent/ferment un panneau)
    $element->add_control(
        'aria_expanded',
        [
            'label' => __('ARIA expanded (toggle)', 'elementor-button-aria'),
            'type'  => \Elementor\Controls_Manager::SWITCHER,
            'label_on'  => __('true', 'elementor-button-aria'),
            'label_off' => __('false', 'elementor-button-aria'),
            'return_value' => 'true',
            'default' => '',
        ]
    );

    $element->add_control(
        'aria_controls',
        [
            'label' => __('ARIA controls (ID cible)', 'elementor-button-aria'),
            'type'  => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __('ex: accordion-panel-1', 'elementor-button-aria'),
            'description' => __('ID de l’élément contrôlé (obligatoire si aria-expanded est utilisé).', 'elementor-button-aria'),
            'condition' => [ 'aria_expanded!' => '' ],
            'label_block' => true,
        ]
    );

    // aria-pressed (pour un bouton “ON/OFF”)
    $element->add_control(
        'aria_pressed',
        [
            'label' => __('ARIA pressed (bouton ON/OFF)', 'elementor-button-aria'),
            'type'  => \Elementor\Controls_Manager::SWITCHER,
            'label_on'  => __('true', 'elementor-button-aria'),
            'label_off' => __('false', 'elementor-button-aria'),
            'return_value' => 'true',
            'default' => '',
        ]
    );

    // role="button" (optionnel — seulement si c’est un lien qui agit comme un vrai bouton)
    $element->add_control(
        'role_override',
        [
            'label' => __('Forcer role="button" (optionnel)', 'elementor-button-aria'),
            'type'  => \Elementor\Controls_Manager::SELECT,
            'options' => [
                '' => __('(aucun — recommandé)', 'elementor-button-aria'),
                'button' => 'button',
            ],
            'default' => '',
            'description' => __('À éviter si le lien fait une vraie navigation. À réserver aux liens utilisés comme boutons JS.', 'elementor-button-aria'),
        ]
    );

}, 10);

// --- 2) Appliquer les attributs ARIA sur la balise <a> du bouton
add_action('elementor/frontend/widget/before_render', function( $widget ){
    if ( 'button' !== $widget->get_name() ) return;

    $s = $widget->get_settings_for_display();

    // Helpers de sanitization
    $sanitize_ids = function($val){
        // Autoriser lettres/chiffres/underscore/trait d’union + espaces (liste d’IDs séparés par espace)
        $val = preg_replace('/[^A-Za-z0-9_\-\s]/', '', (string)$val);
        return trim(preg_replace('/\s+/', ' ', $val));
    };

    if ( ! empty($s['aria_label']) ) {
        $widget->add_render_attribute('button', 'aria-label', sanitize_text_field($s['aria_label']));
    }

    if ( ! empty($s['aria_describedby']) ) {
        $widget->add_render_attribute('button', 'aria-describedby', $sanitize_ids($s['aria_describedby']));
    }

    // aria-expanded / aria-controls en duo
    if ( ! empty($s['aria_expanded']) ) {
        $widget->add_render_attribute('button', 'aria-expanded', 'true');
        if ( ! empty($s['aria_controls']) ) {
            $widget->add_render_attribute('button', 'aria-controls', $sanitize_ids($s['aria_controls']));
        }
    } else {
        // Si explicitement off, on peut fixer à false (utile si l’état est connu au chargement)
        // $widget->add_render_attribute('button', 'aria-expanded', 'false');
        if ( ! empty($s['aria_controls']) ) {
            $widget->add_render_attribute('button', 'aria-controls', $sanitize_ids($s['aria_controls']));
        }
    }

    // aria-pressed (bouton toggle)
    if ( isset($s['aria_pressed']) && $s['aria_pressed'] !== '' ) {
        $widget->add_render_attribute('button', 'aria-pressed', ($s['aria_pressed'] === 'true') ? 'true' : 'false');
    }

    // role (optionnel)
    if ( ! empty($s['role_override']) ) {
        $widget->add_render_attribute('button', 'role', sanitize_text_field($s['role_override']));
    }
}, 10);
