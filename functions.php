<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		'1.0.0'
	);

	wp_enqueue_style(
		'datatables',
		'https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css',
		[],
		'2.0.8'
	);

	wp_enqueue_script(
		'datatables',
		'https://cdn.datatables.net/2.0.8/js/dataTables.min.js',
		[ 'jquery' ],
		'2.0.8',
		true
	);

	wp_enqueue_script(
		'projets-table',
		get_stylesheet_directory_uri() . '/assets/js/projets-table.js',
		[ 'datatables' ],
		'1.0.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

require_once get_stylesheet_directory() . '/inc/custom/client.php';
