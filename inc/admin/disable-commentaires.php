<?php
add_action('init', function() {
    $options = get_option('dnc_theme_options');
    if (empty($options['disable_commentaires'])) return;

    add_filter('comments_open', '__return_false', 20, 2);
    add_filter('pings_open', '__return_false', 20, 2);
    add_filter('comments_array', '__return_empty_array', 10, 2);

    add_action('admin_menu', function() {
        remove_menu_page('edit-comments.php');
    });

    add_action('wp_dashboard_setup', function() {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    });
});
