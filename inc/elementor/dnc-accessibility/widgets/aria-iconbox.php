<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ARIA pour le widget Icon Box Elementor
 * Version corrigée (render_content)
 */


/* ----------------------------------------------------------
 * 1) Ajouter les contrôles ARIA dans le widget Icon Box
 * ---------------------------------------------------------- */
add_action('elementor/element/icon-box/section_icon/before_section_end', function( $element ) {

    $element->add_control(
        'aria_label',
        [
            'label'       => __( 'ARIA label', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( 'Ex : En savoir plus', 'elementor-aria-addon' ),
            'label_block' => true,
        ]
    );

    $element->add_control(
        'aria_describedby',
        [
            'label'       => __( 'ARIA describedby (IDs)', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( 'hint1 hint2', 'elementor-aria-addon' ),
            'label_block' => true,
        ]
    );

}, 10 );



/* ----------------------------------------------------------
 * 2) Injecter les ARIA dans le HTML rendu
 * ---------------------------------------------------------- */
add_filter('elementor/widget/render_content', function( $content, $widget ){

    if ( 'icon-box' !== $widget->get_name() ) {
        return $content;
    }

    $s = $widget->get_settings_for_display();

    if ( empty($s['aria_label']) && empty($s['aria_describedby']) ) {
        return $content;
    }

    $attrs = '';

    if (!empty($s['aria_label'])) {
        $attrs .= ' aria-label="'.esc_attr(trim($s['aria_label'])).'"';
    }

    if (!empty($s['aria_describedby'])) {
        $id = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $s['aria_describedby']);
        $attrs .= ' aria-describedby="'.esc_attr(trim($id)).'"';
    }

    // 1) injection sur <a class="elementor-icon-box-wrapper">
    $new = preg_replace(
        '/(<a\b[^>]*class="[^"]*elementor-icon-box-wrapper[^"]*"[^>]*)(>)/',
        '$1'.$attrs.'$2',
        $content,
        1,
        $count
    );

    if ($count > 0) return $new;

    // 2) fallback : <div class="elementor-icon-box-wrapper">
    $new = preg_replace(
        '/(<div\b[^>]*class="[^"]*elementor-icon-box-wrapper[^"]*"[^>]*)(>)/',
        '$1'.$attrs.'$2',
        $content,
        1,
        $countDiv
    );

    if ($countDiv > 0) return $new;

    return $content;

}, 10, 2 );
