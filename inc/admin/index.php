<?php
/**
 * Aggregate admin-only features and tools.
 */

$admin_dir = DNC_INC_DIR . '/admin/';

require_once $admin_dir . 'login-page.php';
require_once $admin_dir . 'options-page.php';

// Media helpers
require_once $admin_dir . 'admin-media-ajax.php';
require_once $admin_dir . 'media-editor.php';
require_once DNC_INC_DIR . '/cli/media-dnc-cli.php';

$options = get_option('dnc_theme_options');

if (get_current_user_id() === 1) {
    require_once $admin_dir . 'phpinfo.php';
}

if (!empty($options['disable_blog'])) {
    require_once $admin_dir . 'disable-blog.php';
}

if (!empty($options['formats_acceptes'])) {
    require_once $admin_dir . 'images-optimizer.php';
}

require_once $admin_dir . 'disable-commentaires.php';
require_once $admin_dir . 'disable-gutenberg.php';
require_once $admin_dir . 'memory-limit.php';
require_once $admin_dir . 'admin-style.php';

if (defined('ELEMENTOR_VERSION')) {
    require_once $admin_dir . 'elementor-optimisations.php';
}
