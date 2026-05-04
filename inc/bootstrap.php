<?php
/**
 * Centralise the child theme includes to keep functions.php lean.
 */

require_once DNC_INC_DIR . '/core/helpers.php';
require_once DNC_INC_DIR . '/core/setup.php';
require_once DNC_INC_DIR . '/frontend/assets.php';
require_once DNC_INC_DIR . '/frontend/shortcodes.php';
require_once DNC_INC_DIR . '/custom/client.php';

if (is_admin()) {
    require_once DNC_INC_DIR . '/admin/index.php';
}

$options = get_option('dnc_theme_options');
if (!empty($options['disable_blog'])) {
    require_once DNC_INC_DIR . '/admin/disable-blog.php';
}

if (defined('ELEMENTOR_VERSION')) {
    require_once DNC_INC_DIR . '/elementor/loader.php';
}
