<?php 

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function dnc_enqueue_scripts() {
	//wp_enqueue_style('hello-elementor-child-style',get_stylesheet_directory_uri() . '/style.css',['hello-elementor-theme-style',],'1.0.0');
	wp_enqueue_style('dnc-custom',get_stylesheet_directory_uri() . '/css/custom.css',array(),'1.0.0');
	wp_enqueue_script('dnc-script',get_stylesheet_directory_uri() . '/js/script.js',array(),'1.0.0');
}
add_action( 'wp_enqueue_scripts', 'dnc_enqueue_scripts', 20 );

?>