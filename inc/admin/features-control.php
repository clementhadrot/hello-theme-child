<?php
// Chargement des options en front
add_action('init', 'dnc_apply_custom_features');

function dnc_apply_custom_features() {
    $options = get_option('dnc_theme_options');

    // Désactiver emojis
    if (!empty($options['disable_emojis'])) {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }

    // Nettoyage <head>
    if (!empty($options['clean_head'])) {
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
    }

    // Désactiver XML-RPC
    if (!empty($options['disable_xmlrpc'])) {
        add_filter('xmlrpc_enabled', '__return_false');
    }

    // Désactiver RSS
    if (!empty($options['disable_rss'])) {
        add_action('do_feed', 'dnc_disable_rss', 1);
        add_action('do_feed_rdf', 'dnc_disable_rss', 1);
        add_action('do_feed_rss', 'dnc_disable_rss', 1);
        add_action('do_feed_rss2', 'dnc_disable_rss', 1);
        add_action('do_feed_atom', 'dnc_disable_rss', 1);
    }

    // Masquer barre admin
    if (!empty($options['hide_admin_bar'])) {
        if (!current_user_can('administrator')) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    // Désactiver recherche
    if (!empty($options['disable_search'])) {
        add_action('pre_get_posts', function ($query) {
            if ($query->is_search()) {
                $query->is_search = false;
                $query->is_404 = true;
            }
        });
    }

    // Désactiver oEmbed
    if (!empty($options['disable_oembed'])) {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }
}

function dnc_disable_rss() {
    wp_die(__('RSS feed désactivé.', 'dnc_theme'), '', ['response' => 403]);
}
