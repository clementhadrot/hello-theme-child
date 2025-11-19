<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ARIA pour le widget Image Elementor
 * Version corrigée (render_content)
 */


/* ----------------------------------------------------------
 * 1) Ajouter les contrôles ARIA dans le widget Image
 * ---------------------------------------------------------- */
add_action('elementor/element/image/section_image/before_section_end', function( $element ) {

    $element->add_control(
        'aria_label',
        [
            'label'       => __( 'ARIA label', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
        ]
    );

    $element->add_control(
        'aria_describedby',
        [
            'label'       => __( 'ARIA describedby (IDs)', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
        ]
    );

}, 10 );



/* ----------------------------------------------------------
 * 2) Injecter les ARIA dans le HTML rendu
 * ---------------------------------------------------------- */
add_filter('elementor/widget/render_content', function( $content, $widget ){

    if ( 'image' !== $widget->get_name() ) {
        return $content;
    }

    $s = $widget->get_settings_for_display();

    // L'image n'a pas de lien => ARIA inutiles → on ne les applique pas
    if ( empty($s['link']['url']) ) {
        return $content;
    }

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

    // Injecter sur <a class="elementor-image">
    $new = preg_replace(
        '/(<a\b[^>]*class="[^"]*elementor-image[^"]*"[^>]*)(>)/',
        '$1'.$attrs.'$2',
        $content,
        1,
        $count
    );

    if ($count > 0) return $new;

    return $content;

}, 10, 2 );
