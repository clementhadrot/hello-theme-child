<?php 

$baseelementor = '/inc/elementor/';

//require get_stylesheet_directory() . $baseelementor .'dnc-style-container/dnc-style-container.php';
require get_stylesheet_directory() . $baseelementor .'dnc-obfuscate/dnc-obfuscate.php';
require get_stylesheet_directory() . $baseelementor .'dnc-tooltips/dnc-tooltips.php';
require get_stylesheet_directory() . $baseelementor .'dnc-widget-image/dnc-widget-image.php';
//require get_stylesheet_directory() . $baseelementor .'dnc-container-collapse/dnc-container-collapse.php';
//require get_stylesheet_directory() . $baseelementor .'dnc-loop-tax-filter/dnc-loop-tax-filter.php';
//require get_stylesheet_directory() . $baseelementor .'dnc-loop-quick-filter/dnc-loop-quick-filter.php';

if ( !dnc_weather_is_disabled() ) {
	require get_stylesheet_directory() . $baseelementor .'dnc-weather/dnc-weather.php';
}

require get_stylesheet_directory() . $baseelementor .'dnc-sitemap/dnc-sitemap.php';

// Accessibilité
require get_stylesheet_directory() . $baseelementor .'dnc-accessibility/dnc-aria-buttons.php';


// Spécial Client
//require get_stylesheet_directory() . $baseelementor .'dnc-query-spec/dnc-query.php';

