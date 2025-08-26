<?php
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');

    // Ne charge PAS le CSS sur la page du gestionnaire d’éléments Elementor
    if (isset($_GET['page']) && $_GET['page'] === 'elementor-element-manager') {
        return;
    }

    $options = get_option('dnc_theme_options');
    if (!empty($options['disable_commentaires']) || !empty($options['disable_gutenberg'])) {
        wp_enqueue_style(
            'dnc-theme-admin-css',
            get_stylesheet_directory_uri() . '/css/admin/admin-style.css',
            [],
            '1.0'
        );
    }
});
