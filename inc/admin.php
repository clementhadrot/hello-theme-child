<?php 

require_once get_stylesheet_directory() . '/inc/admin/login-page.php';
require_once get_stylesheet_directory() . '/inc/admin/options-page.php';

// Médias
require_once get_stylesheet_directory() . '/inc/admin/admin-ajax.php';
require_once get_stylesheet_directory() . '/inc/admin/media-editor.php';

if (get_current_user_id() == 1) {
    require_once get_stylesheet_directory() . '/inc/admin/phpinfo.php';
}


$options = get_option('dnc_theme_options');
if (!empty($options['disable_blog'])) {
    require_once get_stylesheet_directory() . '/inc/admin/disable-blog.php';
}

if (!empty($options['formats_acceptes'])) {
    require_once get_stylesheet_directory() . '/inc/admin/images-optimizer.php';
}

require_once get_stylesheet_directory() . '/inc/admin/disable-commentaires.php';
require_once get_stylesheet_directory() . '/inc/admin/disable-gutenberg.php';
require_once get_stylesheet_directory() . '/inc/admin/memory-limit.php';
require_once get_stylesheet_directory() . '/inc/admin/admin-style.php';



if (defined('ELEMENTOR_VERSION')) {
    require_once get_stylesheet_directory() . '/inc/admin/elementor-optimisations.php';
}

?>