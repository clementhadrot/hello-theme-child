<?php

add_action('init', function() {
    $options = get_option('dnc_theme_options');
    if (empty($options['disable_blog'])) return;

    add_action('admin_init', function() {
        global $pagenow;

        $post_type = $_GET['post_type'] ?? '';
        $post_id   = isset($_GET['post']) ? absint($_GET['post']) : 0;
        $post_type_from_id = $post_id ? get_post_type($post_id) : '';

        $is_blocked_post_page = (
            $pagenow === 'post.php' &&
            ($post_type === 'post' || $post_type_from_id === 'post')
        );

        $is_blocked_new_post = (
            $pagenow === 'post-new.php' &&
            ($post_type === '' || $post_type === 'post')
        );

        if (
            $is_blocked_post_page ||
            $is_blocked_new_post ||
            ($pagenow === 'edit.php' && ($post_type === '' || $post_type === 'post')) ||
            ($pagenow === 'edit-tags.php' && $post_type === 'post') ||
            ($pagenow === 'term.php' && $post_type === 'post')
        ) {
            wp_safe_redirect(admin_url());
            exit;
        }
    });

    add_filter('pre_get_posts', function($query) {
        if (!is_admin() && $query->is_main_query() && is_home() && is_front_page()) {
            $query->set('post_type', 'page');
        }
    });
}, 10);

// Masquer le menu Articles pour tous les utilisateurs
add_action('admin_menu', function() {
    remove_menu_page('edit.php');
    remove_menu_page('edit-tags.php?taxonomy=category');
    remove_menu_page('edit-tags.php?taxonomy=post_tag');
}, 100);

// Rediriger uniquement les pages blog/articles
add_action('template_redirect', function() {
    if (
        (is_home() && !is_front_page()) ||
        is_singular('post') ||
        is_post_type_archive('post') ||
        is_category() ||
        is_tag() ||
        is_author() ||
        is_date()
    ) {
        wp_safe_redirect(home_url(), 301);
        exit;
    }
});

// Supprimer les flux RSS
add_action('do_feed',     'dnc_disable_blog_feeds', 1);
add_action('do_feed_rdf', 'dnc_disable_blog_feeds', 1);
add_action('do_feed_rss', 'dnc_disable_blog_feeds', 1);
add_action('do_feed_rss2','dnc_disable_blog_feeds', 1);
add_action('do_feed_atom','dnc_disable_blog_feeds', 1);

function dnc_disable_blog_feeds() {
    wp_safe_redirect(home_url(), 301);
    exit;
}

// Supprimer les widgets liés aux articles
add_action('widgets_init', function() {
    unregister_widget('WP_Widget_Recent_Posts');
    unregister_widget('WP_Widget_Recent_Comments');
}, 100);
