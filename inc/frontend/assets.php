<?php
/**
 * Load child theme CSS and JS assets.
 */

function dnc_enqueue_scripts() {
    wp_enqueue_style(
        'dnc-custom',
        get_stylesheet_directory_uri() . '/css/custom.css',
        [],
        '1.0.3'
    );

    wp_enqueue_script(
        'dnc-script',
        get_stylesheet_directory_uri() . '/js/script.js',
        [],
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'dnc_enqueue_scripts', 20);
