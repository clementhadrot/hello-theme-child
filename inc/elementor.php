<?php 

add_action( 'elementor/element/image/section_image/after_section_end', function( $element, $args ) {
    $element->start_controls_section(
        'custom_copyright_section',
        [
            'label' => __( 'Copyright', 'text-domain' ),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]
    );

    $element->add_control(
        'custom_image_copyright',
        [
            'label' => __( 'Copyright Text', 'text-domain' ),
            'type' => \Elementor\Controls_Manager::TEXT,
            'placeholder' => '© 2025 John Doe',
        ]
    );

    $element->end_controls_section();
}, 10, 2 );


// Transforme le rendu du widget image en <figure><img><figcaption></figure>
add_filter( 'elementor/widget/render_content', function( $content, $widget ) {
    if ( 'image' !== $widget->get_name() ) {
        return $content;
    }

    $settings = $widget->get_settings();
    $copyright = !empty( $settings['custom_image_copyright'] ) ? $settings['custom_image_copyright'] : '';

    // Nettoie le contenu existant (éventuelles balises figure)
    $content = preg_replace('#^<figure[^>]*>|</figure>$#', '', $content);

    // Ajoute le figcaption si présent
    $figcaption = $copyright ? '<figcaption>' . esc_html( $copyright ) . '</figcaption>' : '';

    // Enveloppe dans <figure>
    $content = '<figure>' . $content . $figcaption . '</figure>';

    return $content;
}, 10, 2 );

/**
 * Register AJAX endpoint for getting attachment caption from image URL
 */
add_action( 'wp_ajax_get_caption_from_url', 'ajax_get_caption_from_url' );
add_action( 'wp_ajax_nopriv_get_caption_from_url', 'ajax_get_caption_from_url' );

function ajax_get_caption_from_url() {
    if ( empty($_POST['image_url']) ) {
        wp_send_json_error( 'Missing URL' );
    }

    $image_url = esc_url_raw( $_POST['image_url'] );

    // Attempt to get attachment ID from URL
    $attachment_id = attachment_url_to_postid( $image_url );

    if ( ! $attachment_id ) {
        wp_send_json_success( '' );
    }

    // Get caption
    $caption = get_post_field( 'post_excerpt', $attachment_id );

    if ( empty( $caption ) ) {
        wp_send_json_success( '' );
    }

    wp_send_json_success( esc_html( $caption ) );
}

