<?php
// Vérifie qu'Elementor est actif
if (!defined('ELEMENTOR_VERSION')) {
    return;
}

// Ajoute une section dans la page d'options DNC
add_action('admin_init', function() {
    register_setting('dnc_theme_options_groupe', 'dnc_theme_options');

    add_settings_section(
        'dnc_section_elementor',
        'Optimisations Elementor',
        '__return_null',
        'dnc_theme_options'
    );

    $fields = [
        'elementor_disable_fa'    => 'Désactiver Font Awesome',
        'elementor_disable_gfonts' => 'Désactiver Google Fonts',
        'elementor_disable_editor' => 'Supprimer les scripts de l’éditeur côté front',
        'elementor_perf_exp'       => 'Activer l’expérimentation performance Elementor',
        //'elementor_image_optim'    => 'Optimiser les images Elementor',
        'elementor_optimize_images'=> 'Simplification et optimisation des les tailles d\'images gérer par Elementor'
    ];

    foreach ($fields as $key => $label) {
        add_settings_field(
            $key,
            $label,
            'dnc_render_elementor_checkbox',
            'dnc_theme_options',
            'dnc_section_elementor',
            ['label_for' => $key]
        );
    }

    function dnc_render_elementor_checkbox($args) {
        $options = get_option('dnc_theme_options');
        $id = $args['label_for'];
        $checked = !empty($options[$id]) ? 'checked' : '';
        echo "<input type='checkbox' id='$id' name='dnc_theme_options[$id]' value='1' $checked />";
    }
});

// Applique les optimisations
add_action('init', function() {
    $options = get_option('dnc_theme_options');

    // Active l'expérimentation de performance si l'option est activée
    if (!empty($options['elementor_perf_exp'])) {
        update_option('elementor_page_speed_experiment', 'active');
    }

    // Désactive Google Fonts si l'option est activée
    if (!empty($options['elementor_disable_gfonts'])) {
        add_filter('elementor/frontend/print_google_fonts', '__return_false');
    }

    // Active l'optimisation des images si l'option est activée
    /*if (!empty($options['elementor_image_optim'])) {
        add_filter('elementor/frontend/print_images', '__return_true'); // Permet de forcer l'optimisation des images
    }*/

    // Applique les optimisations d'images si l'option est activée
    if (!empty($options['elementor_optimize_images'])) {
        // Supprimer les tailles d'images spécifiques à Elementor
        add_filter('intermediate_image_sizes', function($sizes) {
            return array_filter($sizes, function($size) {
                return strpos($size, 'elementor') === false;
            });
        });

        add_action('after_setup_theme', function() {
            remove_image_size('elementor_thumbnail');
            remove_image_size('elementor_medium');
            remove_image_size('elementor_large');
            remove_image_size('elementor_extra_large');
        });

        // Supprimer les tailles d'image énormes de WordPress
        add_filter('intermediate_image_sizes_advanced', function($sizes) {
            unset($sizes['1536x1536'], $sizes['2048x2048']);
            return $sizes;
        });

        // Désactiver la limite de taille d'image de grande taille (éviter la compression automatique)
        add_filter('big_image_size_threshold', '__return_false');

        // Désactiver la compression des images à l'importation (Elementor Pro)
        add_filter('elementor_pro/utils/files/upload_image_compression_quality', function() {
            return 100; // aucune compression
        });
    }
});

// Supprime Font Awesome si l'option est activée
add_action('elementor/frontend/after_register_styles', function() {
    $options = get_option('dnc_theme_options');
    if (!empty($options['elementor_disable_fa'])) {
        wp_deregister_style('elementor-icons');
        wp_deregister_style('elementor-icons-fa-brands');
        wp_deregister_style('elementor-icons-fa-solid');
    }
});

// Supprime les scripts d'édition pour les visiteurs non connectés
add_action('wp_enqueue_scripts', function() {
    if (!is_user_logged_in()) {
        $options = get_option('dnc_theme_options');
        if (!empty($options['elementor_disable_editor'])) {
            wp_dequeue_script('elementor-editor');
        }
    }
}, 20);
