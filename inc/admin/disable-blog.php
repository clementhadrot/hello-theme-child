<?php

add_action('init', function() {
    $options = get_option('dnc_theme_options');
    if (empty($options['disable_blog'])) return;

    // Masquer les articles sur le front
    add_filter('pre_get_posts', function($query) {
        if (!is_admin() && $query->is_main_query()) {
            $query->set('post_type', 'page');
        }
    });

    // Bloquer l'accès à l'éditeur d'article pour les non-admin
    if (!current_user_can('administrator')) {
        add_action('admin_init', function() {
            global $pagenow;
            if (in_array($pagenow, ['edit.php', 'post-new.php', 'edit-tags.php', 'term.php']) && $_GET['post_type'] === 'post') {
                wp_redirect(admin_url());
                exit;
            }
        });
    }
}, 10);

// Masquer le menu Articles pour les non-admin
add_action('admin_menu', function() {
    if (!current_user_can('administrator')) {
        remove_menu_page('edit.php');
        remove_menu_page('edit-tags.php?taxonomy=category');
        remove_menu_page('edit-tags.php?taxonomy=post_tag');
    }
}, 100);

// Rediriger les pages liées au blog
add_action('template_redirect', function() {
    if (
        is_home() || is_category() || is_tag() || is_author() || is_date() ||
        is_archive() || is_single()
    ) {
        wp_redirect(home_url(), 301);
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
    wp_redirect(home_url(), 301);
    exit;
}

// Supprimer les widgets liés aux articles
add_action('widgets_init', function() {
    unregister_widget('WP_Widget_Recent_Posts');
    unregister_widget('WP_Widget_Recent_Comments');
}, 100);
