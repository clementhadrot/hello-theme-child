<?php
add_action('admin_enqueue_scripts', function($hook) {
    // Ne charge PAS le CSS sur la page du gestionnaire d’éléments Elementor
    if (isset($_GET['page']) && $_GET['page'] === 'elementor-element-manager') {
        return;
    }

    $options = get_option('dnc_theme_options');

    $needs_admin_css = !empty($options['disable_commentaires']) || !empty($options['disable_gutenberg']);
    $is_media_editor = $hook === 'media_page_dn-media-editor';

    // Rien à charger si aucune option n'est active et que l'on n'est pas sur la page dédiée.
    if (!$needs_admin_css && !$is_media_editor) {
        return;
    }

    if ($needs_admin_css) {
        wp_enqueue_style(
            'dnc-theme-admin-css',
            get_stylesheet_directory_uri() . '/css/admin/admin-style.css',
            [],
            '1.0'
        );
    }

    if ($is_media_editor) {
        wp_enqueue_script('dn-media-editor-js', get_stylesheet_directory_uri() .'/js/media-editor.js', ['jquery'], false, true);
        wp_localize_script('dn-media-editor-js', 'MediaEditorAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('media_editor_nonce')
        ]);
    }
});
