<?php // Base du thème DN Consultants Wordpress

// Les indispensables
require_once get_stylesheet_directory() . '/inc/admin.php'; 
require_once get_stylesheet_directory() . '/inc/assets.php'; 
require_once get_stylesheet_directory() . '/inc/shortcodes.php'; 
require_once get_stylesheet_directory() . '/inc/elementor.php'; 

add_image_size('carre300', 300, 300, true); // true = crop au centre
