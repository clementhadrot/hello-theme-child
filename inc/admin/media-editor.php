<?php
add_action('admin_menu', function () {
    add_media_page('Éditeur DNC', 'Éditeur DNC', 'upload_files', 'dn-media-editor', 'render_alt_editor_page');
});

function render_alt_editor_page() {
    ?>
    <div class="wrap">
        <h1>Éditeur de texte ALT / Copyright des images</h1>

        <button id="generate-all-alt" class="button button-secondary" style="margin-right:10px;">🔄 Générer tous les ALT vides</button>
        <input type="text" id="image-search" placeholder="🔍 Rechercher par titre..." style="width: 300px; margin-bottom: 15px;">

        <table class="wp-list-table widefat fixed striped" id="media-editor-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Titre</th>
                    <th>Légende</th>
                    <th>Texte ALT</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $images = get_posts([
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'numberposts'    => -1
            ]);

            $sorted = [];
            foreach ($images as $img) {
                //print_r($img); die();
                $sorted[] = [
                    'ID' => $img->ID,
                    'title' => $img->post_title,
                    'legende' => $img->post_excerpt,
                    'alt' => get_post_meta($img->ID, '_wp_attachment_image_alt', true)
                ];
            }
            usort($sorted, function ($a, $b) {
                return (empty($a['alt']) ? -1 : 1) - (empty($b['alt']) ? -1 : 1);
            });

            foreach ($sorted as $image) {
                $alt = $image['alt'];
                $title = $image['title'];
                $legende = $image['legende'];
                $id = $image['ID'];
                echo '<tr data-id="' . esc_attr($id) . '" data-title="' . esc_attr(strtolower($title)) . '">';
                echo '<td>' . wp_get_attachment_image($id, [300, 300]) . '</td>';
                echo '<td><input type="text" class="title-field" value="' . esc_attr($title) . '"></td>';
                echo '<td><input type="text" class="legende-field" value="' . esc_attr($legende) . '"></td>';
                echo '<td><input type="text" class="alt-field" value="' . esc_attr($alt) . '"></td>';
                echo '<td><button class="generate-alt button">Générer depuis le titre</button></td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
        
    </div>
    <style>
        #media-editor-table input {
            width: 100%;
            box-sizing: border-box;
        }
    </style>
    <?php
}
?>