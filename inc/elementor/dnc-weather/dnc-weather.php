<?php
/**
 * DN – Elementor Weather Carousel (Weatherstack)
 * Intégration pour **thème enfant** (pas de plugin).
 *
*/

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function(){
    $handle = 'dn-weather';
    $default_rel = '/inc/elementor/dnc-weather/assets/css/dnc-weather.css';
    $path   = get_stylesheet_directory() . $default_rel;
    $uri    = get_stylesheet_directory_uri() . $default_rel;

    if ( file_exists( $path ) ) {
        wp_register_style( $handle, $uri, [], filemtime( $path ) );
    } else {
        // Fallback: styles inline minimalistes si le fichier n'existe pas
        $css = '.dn-wc-card{background:#fff;border-radius:1rem;padding:1rem;box-shadow:0 4px 20px rgba(0,0,0,.06);text-align:center}'
             . '.dn-wc-date{font-weight:600;margin-bottom:.25rem}'
             . '.dn-wc-place{font-size:.85rem;color:#666;margin-bottom:.4rem}'
             . '.dn-wc-icon img{width:56px;height:56px;object-fit:contain;margin:.25rem auto}'
             . '.dn-wc-temps{font-size:1.1rem;font-weight:700;margin:.25rem 0}'
             . '.dn-wc-desc{font-size:.9rem;color:#444}';
        wp_register_style( $handle, false );
        wp_add_inline_style( $handle, $css );
    }
}, 5 );


add_action( 'elementor/widgets/register', function( $widgets_manager ){
    require_once __DIR__ . '/class-dn-widget-weather.php';
    $widgets_manager->register( new \DN_Widget_Weather() );
} );


add_action( 'admin_notices', function(){
    if ( current_user_can( 'manage_options' ) && empty( get_option( 'dn_weatherstack_api_key' ) ) ) {
        echo '<div class="notice notice-warning"><p><strong>DN – Weather Carousel</strong> : définissez la clé Weatherstack dans Apparence → Personnaliser → Météo (Weatherstack).</p></div>';
    }
} );
