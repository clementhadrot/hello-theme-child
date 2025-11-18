<?php // Base du thème DN Consultants Wordpress

// Fonction d'itinalisation 
function dnc_is_weather_enabled() {
    $options = get_option('dnc_theme_options');
    return !empty($options['enable_weather']);
}
function dnc_weather_is_disabled() {
    return !dnc_is_weather_enabled() || empty(get_option('dn_weatherstack_api_key'));
}

// Les indispensables
require_once get_stylesheet_directory() . '/inc/admin.php'; 
require_once get_stylesheet_directory() . '/inc/assets.php'; 
require_once get_stylesheet_directory() . '/inc/shortcodes.php'; 
require_once get_stylesheet_directory() . '/inc/elementor.php'; 
require_once get_stylesheet_directory() . '/inc/specifique-client.php';
//require_once get_stylesheet_directory() . '/inc/orderby-posts.php';

add_image_size('carre300', 300, 300, true); // true = crop au centre


