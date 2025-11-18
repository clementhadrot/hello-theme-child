<?php
// Sauvegarde des champs dans l'éditeur de Médias
add_action('wp_ajax_save_image_fields', function () {
    check_ajax_referer('media_editor_nonce');

    $id = intval($_POST['id']);
    $title = sanitize_text_field($_POST['title']);
    $legende = sanitize_text_field($_POST['legende']);
    $desc = sanitize_text_field($_POST['desc']);
    $alt = sanitize_text_field($_POST['alt']);

    wp_update_post([
        'ID' => $id,
        'post_title' => $title,
        'post_excerpt' => $legende,
        'post_content' => $desc
    ]);

    update_post_meta($id, '_wp_attachment_image_alt', $alt);

    wp_send_json_success(['message' => 'Mise à jour réussie']);
});

?>