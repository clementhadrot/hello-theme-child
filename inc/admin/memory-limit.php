<?php
add_action('init', function() {
    $options = get_option('dnc_theme_options');
     $memory = $options['wp_memory_limit'] ?? null;
    if ($memory && $memory !== 'default') {
        @ini_set('memory_limit', $memory);
        if (!defined('WP_MEMORY_LIMIT')) {
            define('WP_MEMORY_LIMIT', $memory);
        }
        if (!defined('WP_MAX_MEMORY_LIMIT')) {
            define('WP_MAX_MEMORY_LIMIT', $memory);
        }
    }
});

// Affiche une alerte si la mémoire effective ne correspond pas à celle demandée
add_action('admin_notices', function() {
    $options = get_option('dnc_theme_options');
    $expected = $options['wp_memory_limit'] ?? null;
    if (!$expected || $expected === 'default') {
        return;
    }

    $effective = ini_get('memory_limit');

    // Compare la valeur attendue avec celle réellement appliquée
    if ($expected !== $effective) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Attention :</strong> vous avez défini la mémoire WordPress à <code>' . esc_html($expected) . '</code>, mais la mémoire effective est <code>' . esc_html($effective) . '</code>. Cela peut indiquer une limitation imposée par votre hébergeur.</p>';
        echo '</div>';
    }
});
