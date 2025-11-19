<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ARIA pour le widget Icône Elementor
 * Version corrigée (sans get_render_output) :
 * - Ajoute des contrôles ARIA dans le widget Icon
 * - Injecte les attributs ARIA dans le HTML final via le filtre
 *   elementor/widget/render_content, directement sur <a> ou <span>
 */

/* ----------------------------------------------------------
 * 1) Ajouter les contrôles ARIA dans le widget "Icône"
 * ---------------------------------------------------------- */
add_action( 'elementor/element/icon/section_icon/before_section_end', function( $element ) {

    $element->add_control(
        'aria_label',
        [
            'label'       => __( 'ARIA label', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( 'Ex : Ouvrir le menu', 'elementor-aria-addon' ),
            'label_block' => true,
        ]
    );

    $element->add_control(
        'aria_describedby',
        [
            'label'       => __( 'ARIA describedby (IDs)', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( 'ex: hint1 hint2', 'elementor-aria-addon' ),
            'label_block' => true,
        ]
    );

    $element->add_control(
        'aria_expanded',
        [
            'label'        => __( 'ARIA expanded (toggle)', 'elementor-aria-addon' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'true', 'elementor-aria-addon' ),
            'label_off'    => __( 'false', 'elementor-aria-addon' ),
            'return_value' => 'true',
        ]
    );

    $element->add_control(
        'aria_controls',
        [
            'label'       => __( 'ARIA controls (ID cible)', 'elementor-aria-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __( 'ex: menu-mobile-container', 'elementor-aria-addon' ),
            'condition'   => [ 'aria_expanded!' => '' ],
            'label_block' => true,
        ]
    );

    $element->add_control(
        'aria_pressed',
        [
            'label'        => __( 'ARIA pressed (ON/OFF)', 'elementor-aria-addon' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'true', 'elementor-aria-addon' ),
            'label_off'    => __( 'false', 'elementor-aria-addon' ),
            'return_value' => 'true',
        ]
    );

    $element->add_control(
        'role_override',
        [
            'label'   => __( 'Forcer role', 'elementor-aria-addon' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => [
                ''       => __( '(aucun)', 'elementor-aria-addon' ),
                'button' => 'button',
            ],
            'default' => '',
        ]
    );

}, 10 );


/* ----------------------------------------------------------
 * 2) Injecter les ARIA dans le HTML rendu (SUR <a> si possible)
 * ---------------------------------------------------------- */
add_filter( 'elementor/widget/render_content', function( $content, $widget ) {

    // On ne touche qu'au widget "icon"
    if ( 'icon' !== $widget->get_name() ) {
        return $content;
    }

    $settings = $widget->get_settings_for_display();

    // Si aucun champ ARIA n'est rempli → on ne modifie rien
    if (
        empty( $settings['aria_label'] )
        && empty( $settings['aria_describedby'] )
        && empty( $settings['aria_expanded'] )
        && empty( $settings['aria_controls'] )
        && empty( $settings['aria_pressed'] )
        && empty( $settings['role_override'] )
    ) {
        return $content;
    }

    // Construction de la chaîne d'attributs à injecter
    $attrs = '';

    if ( ! empty( $settings['aria_label'] ) ) {
        $attrs .= ' aria-label="' . esc_attr( trim( $settings['aria_label'] ) ) . '"';
    }

    if ( ! empty( $settings['aria_describedby'] ) ) {
        // IDs séparés par espaces, on nettoie un peu
        $val = preg_replace( '/[^A-Za-z0-9_\-\s]/', '', (string) $settings['aria_describedby'] );
        $val = trim( preg_replace( '/\s+/', ' ', $val ) );
        if ( $val !== '' ) {
            $attrs .= ' aria-describedby="' . esc_attr( $val ) . '"';
        }
    }

    if ( ! empty( $settings['aria_expanded'] ) ) {
        $attrs .= ' aria-expanded="true"';
    }

    if ( ! empty( $settings['aria_controls'] ) ) {
        $val = preg_replace( '/[^A-Za-z0-9_\-\s]/', '', (string) $settings['aria_controls'] );
        $val = trim( preg_replace( '/\s+/', ' ', $val ) );
        if ( $val !== '' ) {
            $attrs .= ' aria-controls="' . esc_attr( $val ) . '"';
        }
    }

    if ( isset( $settings['aria_pressed'] ) && $settings['aria_pressed'] !== '' ) {
        $attrs .= ' aria-pressed="' . ( $settings['aria_pressed'] === 'true' ? 'true' : 'false' ) . '"';
    }

    if ( ! empty( $settings['role_override'] ) ) {
        $attrs .= ' role="' . esc_attr( $settings['role_override'] ) . '"';
    }

    // Si au final il n'y a rien à ajouter, on sort
    if ( $attrs === '' ) {
        return $content;
    }

    // 1) On essaie d'abord d'injecter dans <a ... class="...elementor-icon...">
    $new_content = preg_replace(
        '/(<a\b[^>]*class="[^"]*elementor-icon[^"]*"[^>]*)(>)/',
        '$1' . $attrs . '$2',
        $content,
        1,
        $count
    );

    if ( $count > 0 ) {
        return $new_content;
    }

    // 2) Fallback : aucune balise <a> → on vise <span ... class="...elementor-icon...">
    $new_content = preg_replace(
        '/(<span\b[^>]*class="[^"]*elementor-icon[^"]*"[^>]*)(>)/',
        '$1' . $attrs . '$2',
        $content,
        1,
        $countSpan
    );

    if ( $countSpan > 0 ) {
        return $new_content;
    }

    // Si on n'a pas trouvé d'élément icon, on ne modifie rien
    return $content;

}, 10, 2 );
