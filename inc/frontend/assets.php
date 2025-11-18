<?php
/**
 * Load child theme CSS and JS assets.
 */

function dnc_get_asset_version(string $relative_path, string $fallback = '1.0.0'): string {
    $asset_path = trailingslashit(get_stylesheet_directory()) . ltrim($relative_path, '/');

    if (file_exists($asset_path)) {
        return (string) filemtime($asset_path);
    }

    return $fallback;
}

function dnc_enqueue_scripts() {
    wp_enqueue_style(
        'dnc-custom',
        get_stylesheet_directory_uri() . '/css/custom.css',
        [],
        dnc_get_asset_version('css/custom.css', '1.0.3')
    );

    wp_enqueue_script(
        'dnc-script',
        get_stylesheet_directory_uri() . '/js/script.js',
        [],
        dnc_get_asset_version('js/script.js', '1.0.0'),
        true
    );
}
add_action('wp_enqueue_scripts', 'dnc_enqueue_scripts', 20);
