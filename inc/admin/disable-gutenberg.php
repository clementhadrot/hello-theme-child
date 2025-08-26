<?php
add_action('init', function() {
    $options = get_option('dnc_theme_options');
    if (empty($options['disable_gutenberg'])) return;

    add_filter('use_block_editor_for_post_type', '__return_false', 10);

    add_action('wp_enqueue_scripts', function() {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
    }, 100);
});
