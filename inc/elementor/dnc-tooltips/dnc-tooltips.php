<?php
/**
 * Plugin léger : Tooltips pour Elementor (chargé depuis le thème)
 * Dossier : /wp-content/themes/votre-theme/dnc-elementor-tooltips/
 */

if (!defined('ABSPATH')) { exit; }

final class DNC_Elementor_Tooltips {
    const VERSION = '1.0.0';
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_assets']); // pour l’éditeur

        // Ajoute les contrôles "Tooltip" à tous les widgets
        add_action('elementor/element/common/_section_style/after_section_end', [$this, 'register_controls'], 10, 2);

        // Injecte les attributes data-* au wrapper si activé
        add_action('elementor/frontend/widget/before_render',  [$this, 'apply_tooltip_attributes']);
        add_action('elementor/frontend/section/before_render', [$this, 'apply_tooltip_attributes']);
        add_action('elementor/frontend/column/before_render',  [$this, 'apply_tooltip_attributes']);
    }

    public function enqueue_assets() {
        $base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-tooltips';

        // Tippy.js (léger et robuste) + Popper inclus

         wp_enqueue_script(
            'popperjs',
            'https://unpkg.com/@popperjs/core@2',
            [],
            '6',
            true
        );
        

        wp_enqueue_script(
            'tippy',
            'https://unpkg.com/tippy.js@6',
            [],
            '6',
            true
        );

        wp_enqueue_style(
            'dnc-tooltips',
            $base . '/assets/tooltips.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'dnc-tooltips',
            $base . '/assets/tooltips.js',
            ['tippy'],
            self::VERSION,
            true
        );
    }

    public function register_controls( $element, $args ) {
        /** @var \Elementor\Element_Base $element */
        $element->start_controls_section(
            'dnc_tooltip_section',
            [
                'label' => __('Tooltip', 'dnc-theme'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->add_control(
            'dnc_tooltip_enable',
            [
                'label'        => __('Activer', 'dnc-theme'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $element->add_control(
            'dnc_tooltip_content',
            [
                'label'       => __('Texte du tooltip', 'dnc-theme'),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => __('Saisir le texte du tooltip…', 'dnc-theme'),
                'condition'   => ['dnc_tooltip_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_tooltip_placement',
            [
                'label'     => __('Position', 'dnc-theme'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => [
                    'top'    => 'Top',
                    'right'  => 'Right',
                    'bottom' => 'Bottom',
                    'left'   => 'Left',
                ],
                'default'   => 'top',
                'condition' => ['dnc_tooltip_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_tooltip_trigger',
            [
                'label'     => __('Déclencheur', 'dnc-theme'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => [
                    'mouseenter focus' => __('Survol & focus', 'dnc-theme'),
                    'click'            => __('Clic', 'dnc-theme'),
                    'manual'           => __('Manuel (avancé)', 'dnc-theme'),
                ],
                'default'   => 'mouseenter focus',
                'condition' => ['dnc_tooltip_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_tooltip_theme',
            [
                'label'     => __('Thème', 'dnc-theme'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => [
                    'dark'  => __('Sombre', 'dnc-theme'),
                    'light' => __('Clair', 'dnc-theme'),
                ],
                'default'   => 'dark',
                'condition' => ['dnc_tooltip_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_tooltip_maxwidth',
            [
                'label'     => __('Largeur max (px)', 'dnc-theme'),
                'type'      => \Elementor\Controls_Manager::NUMBER,
                'min'       => 120,
                'max'       => 600,
                'step'      => 10,
                'default'   => 260,
                'condition' => ['dnc_tooltip_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_tooltip_delay',
            [
                'label'     => __('Délai (ms) ouverture/fermeture', 'dnc-theme'),
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'size_units'=> ['ms'],
                'range'     => ['ms' => ['min' => 0, 'max' => 2000, 'step' => 50]],
                'default'   => ['size' => 50],
                'condition' => ['dnc_tooltip_enable' => 'yes'],
            ]
        );

        $element->end_controls_section();
    }

    public function apply_tooltip_attributes( $element ) {
        
        $settings = $element->get_settings_for_display();
        //print_r($element);


        if ( empty($settings['dnc_tooltip_enable']) || 'yes' !== $settings['dnc_tooltip_enable'] ) {
            return;
        }


        $content = isset($settings['dnc_tooltip_content']) ? wp_kses_post( $settings['dnc_tooltip_content'] ) : '';
        if ( '' === trim( wp_strip_all_tags($content) ) ) {
            return; // rien à afficher
        }

        $placement = !empty($settings['dnc_tooltip_placement']) ? $settings['dnc_tooltip_placement'] : 'top';
        $trigger   = !empty($settings['dnc_tooltip_trigger']) ? $settings['dnc_tooltip_trigger'] : 'mouseenter focus';
        $theme     = !empty($settings['dnc_tooltip_theme']) ? $settings['dnc_tooltip_theme'] : 'dark';
        $maxwidth  = !empty($settings['dnc_tooltip_maxwidth']) ? intval($settings['dnc_tooltip_maxwidth']) : 260;
        $delay     = (isset($settings['dnc_tooltip_delay']['size'])) ? intval($settings['dnc_tooltip_delay']['size']) : 50;

        // Ajout d’attributs data-* sur le wrapper du widget
        //print_r($element);
        $element->add_render_attribute('_wrapper', 'class', 'dnc-has-tooltip');
        $element->add_render_attribute('_wrapper', 'data-dnc-tooltip', esc_attr( wp_strip_all_tags($content) ) );
        $element->add_render_attribute('_wrapper', 'data-dnc-placement', esc_attr($placement) );
        $element->add_render_attribute('_wrapper', 'data-dnc-trigger', esc_attr($trigger) );
        $element->add_render_attribute('_wrapper', 'data-dnc-theme', esc_attr($theme) );
        $element->add_render_attribute('_wrapper', 'data-dnc-maxwidth', esc_attr($maxwidth) );
        $element->add_render_attribute('_wrapper', 'data-dnc-delay', esc_attr($delay) );

        // Accessibilité : titre ARIA (fallback lecteurs d’écran)
        $element->add_render_attribute('_wrapper', 'aria-label', esc_attr( wp_strip_all_tags($content) ) );
    }
}

add_action('after_setup_theme', function() {
    // Charge uniquement si Elementor actif
    if ( did_action('elementor/loaded') ) {
        DNC_Elementor_Tooltips::instance();
    }
});
