<?php
/**
 * Plugin Name: Elementor ARIA Addon
 * Description: Ajoute des attributs ARIA aux widgets natifs d’Elementor (Button, Icon, Icon Box, Image).
 * Author: DN Consultants
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;

// Vérifier Elementor
add_action('after_setup_theme', function () {
    if ( ! did_action('elementor/loaded') ) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Elementor ARIA Addon :</strong> Elementor n\'est pas actif.</p></div>';
        });
        return;
    }

    // Charger les fichiers widget ARIA
    $path = get_stylesheet_directory() . '/inc/elementor/dnc-accessibility/widgets/';

    require_once $path . 'aria-button.php';
    require_once $path . 'aria-icon.php';
    require_once $path . 'aria-iconbox.php';
    require_once $path . 'aria-image.php';
});
