<?php
// Créer la page sous "Apparence"
add_action('admin_menu', function() {
    add_submenu_page(
        'themes.php',
        'Options DNC',
        'Options DNC',
        'manage_options',
        'dnc_theme_options',
        'dnc_theme_afficher_options'
    );
});

// Formulaire HTML
function dnc_theme_afficher_options() {
    ?>
    <div class="wrap">
        <h1>Options du thème enfant</h1>
        <form method="post" action="options.php" class="dnc-theme-options-form">
            <?php
            settings_fields('dnc_theme_options_groupe');
            do_settings_sections('dnc_theme_options');
            submit_button();
            ?>
        </form>
    </div>

    
    <style>
            /* Style général de la page */
            .dnc-theme-options-form {
                background-color: #fff;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                font-family: "Helvetica Neue", Arial, sans-serif;
            }

            /* Titre principal */
            .wrap h1 {
                font-size: 2em;
                margin-bottom: 20px;
                color: #333;
                border-bottom: 2px solid #ccc;
                padding-bottom: 10px;
                background-color: #f4f4f4;
                padding-left: 15px;
                font-weight: bold;
            }

            /* Sections */
            .wrap h2 {
                margin-top: 30px;
                font-size: 1.5em;
                color: #333;
                background-color: #f5f5f5;
                padding: 10px;
                border-radius: 6px;
                border: 1px solid #ddd;
            }

            .wrap h2::before {
                content: "\f141"; /* Icône FontAwesome */
                font-family: FontAwesome;
                padding-right: 8px;
                font-size: 1.3em;
            }

            /* Style des champs */
            .form-table {
                width: 100%;
                margin-bottom: 20px;
            }

            .form-table th {
                width: 375px;
                text-align: right;
                padding-right: 20px;
                font-weight: bold;
                color: #666;
            }

            .form-table td {
                padding-bottom: 12px;
                vertical-align: top;
                color: #444;
            }

            /* Améliorer l'affichage des radios et checkboxes */
            .form-table input[type="checkbox"],
            .form-table input[type="radio"] {
                margin-right: 10px;
                margin-top: 3px;
            }

            .form-table label {
                font-size: 1.1em;
                color: #555;
            }

            /* Style du bouton d'envoi */
            .submit input {
                background-color: #007cba;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 10px 20px;
                cursor: pointer;
                font-size: 1.2em;
                transition: background-color 0.3s ease, transform 0.3s ease;
            }

            .submit input:hover {
                background-color: #005b8f;
                transform: scale(1.05);
            }

            /* Séparateurs entre les sections */
            .dnc-options-group {
                margin-bottom: 25px;
                padding: 20px;
                border-radius: 6px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .dnc-options-group h3 {
                font-size: 1.3em;
                color: #333;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
                margin-bottom: 15px;
            }

            .form-table td input[type="radio"] {
                width: auto;
            }

            /* Améliorer la lisibilité des alertes */
            .notice-warning {
                background-color: #ffeb3b;
                color: #333;
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 6px;
                border: 1px solid #f0c100;
            }

            .notice-warning p {
                font-size: 1.1em;
            }

            /* Ajouter des transitions douces sur hover pour les sections */
            .dnc-options-group:hover {
                background-color: #eef9ff;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            /* Style pour l'icône d'information */
            .dnc-tooltip {
                cursor: pointer;
                font-size: 1.3em;
                color: #007cba;
                margin-left: 8px;
            }

            .dnc-tooltip:hover::after {
                content: attr(data-tooltip);
                position: absolute;
                background-color: #333;
                color: #fff;
                padding: 5px;
                border-radius: 4px;
                font-size: 0.9em;
                top: -25px;
                left: 50%;
                transform: translateX(-50%);
                white-space: nowrap;
                z-index: 9999;
            }

        </style><?php
}

// Enregistrement des options
add_action('admin_init', function() {
    register_setting('dnc_theme_options_groupe', 'dnc_theme_options');

    add_settings_section('section_general', 'Fonctionnalités générales', null, 'dnc_theme_options');

    // Champs booléens
    

    add_settings_field('disable_gutenberg', 'Désactiver Gutenberg', function() {
        $options = get_option('dnc_theme_options');
        $checked = !empty($options['disable_gutenberg']) ? 'checked' : '';
        echo "<input type='checkbox' name='dnc_theme_options[disable_gutenberg]' value='1' $checked /> Revenir à l’éditeur classique";
        echo "<br /><em><i class='fa fa-info-circle'></i>Cela désactive l’éditeur Gutenberg et réactive l’éditeur classique (TinyMCE). Vous devrez peut-être activer ce réglage si vous utilisez des plugins non compatibles avec Gutenberg</em>";
    }, 'dnc_theme_options', 'section_general');

    add_settings_field('disable_blog', 'Désactiver le blog/articles', function() {
        $options = get_option('dnc_theme_options');
        $checked = !empty($options['disable_blog']) ? 'checked' : '';
        echo "<input type='checkbox' name='dnc_theme_options[disable_blog]' value='1' $checked /> Désactiver le blog";
        echo "<br /><em><i class='fa fa-info-circle'></i> Permet de désactiver la partie article de wordpress</em>";
    }, 'dnc_theme_options', 'section_general');

    add_settings_field('disable_commentaires', 'Désactiver les commentaires', function() {
        $options = get_option('dnc_theme_options');
        $checked = !empty($options['disable_commentaires']) ? 'checked' : '';
        echo "<input type='checkbox' name='dnc_theme_options[disable_commentaires]' value='1' $checked /> Supprimer les commentaires";
        echo "<br /><em><i class='fa fa-info-circle'></i> Cette option désactive tous les commentaires sur le site, y compris les pingbacks et trackbacks. Il est aussi possible de les réactiver à tout moment.</em>";
    }, 'dnc_theme_options', 'section_general');

    add_settings_field('wp_memory_limit', 'Limite de mémoire WordPress', function() {
        $options = get_option('dnc_theme_options');
        $val = $options['wp_memory_limit'] ?? 'default';
        $choices = [
            'default' => 'Défaut WordPress',
            '256M'    => '256 Mo',
            '512M'    => '512 Mo',
            '1024M'   => '1024 Mo',
            '2048M'   => '2048 Mo (max)'
        ];
        foreach ($choices as $key => $label) {
            $checked = checked($val, $key, false);
            echo "<label><input type='radio' name='dnc_theme_options[wp_memory_limit]' value='$key' $checked /> $label</label><br>";
        }
        echo "<br /><em><i class='fa fa-info-circle'></i> Augmenter la limite de mémoire permet à WordPress de gérer des tâches plus lourdes. Si vous avez un hébergement mutualisé, cette option peut ne pas être modifiable</em>";
    }, 'dnc_theme_options', 'section_general');
    
    // Ajout Option d'acceptation d'images HEIC/HEIF Iphone
    add_settings_field('formats_acceptes', 'Formats d’images spéciaux à accepter <br /><a href="' . esc_url( admin_url('admin-ajax.php?action=view_image_formats_doc') ) . '" class="button button-secondary" target="_blank">Voir la documentation des formats d\'image</a>', function () {
    $options = get_option('dnc_theme_options');
    $selected = $options['formats_acceptes'] ?? [];

    echo '<label><input type="checkbox" name="dnc_theme_options[formats_acceptes][]" value="heic"' . (in_array('heic', $selected) ? ' checked' : '') . '> HEIC/HEIF (iPhone)</label><br>';
    echo '<label><input type="checkbox" name="dnc_theme_options[formats_acceptes][]" value="tif"' . (in_array('tif', $selected) ? ' checked' : '') . '> TIFF (.tif/.tiff)</label><br>';
    echo '<label><input type="checkbox" name="dnc_theme_options[formats_acceptes][]" value="webp"' . (in_array('webp', $selected) ? ' checked' : '') . '> WebP (.webp)</label><br>';
    echo '<label><input type="checkbox" name="dnc_theme_options[formats_acceptes][]" value="avif"' . (in_array('avif', $selected) ? ' checked' : '') . '> AVIF (.avif)</label><br>';
    echo '<label><input type="checkbox" name="dnc_theme_options[formats_acceptes][]" value="jxl"' . (in_array('jxl', $selected) ? ' checked' : '') . '> JPEG XL (.jxl)</label><br>';

}, 'dnc_theme_options', 'section_general');
    
    
    register_setting('dnc_theme_options', 'formats_acceptes', [
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            return array_values(array_intersect($input, ['heic', 'tif', 'webp', 'avif', 'jxl']));
        },
        'default' => []
    ]);


    // Section des fonctions pratiques
    add_settings_section('dnc_section_features', 'Fonctions pratiques', '__return_null', 'dnc_theme_options');

    $fields = [
        'disable_emojis'    => 'Désactiver les emojis WordPress',
        'clean_head'        => 'Nettoyer les balises <head>',
        'disable_xmlrpc'    => 'Désactiver XML-RPC',
        'disable_rss'       => 'Désactiver les flux RSS',
        'hide_admin_bar'    => 'Masquer la barre admin pour les non-admins',
        'disable_search'    => 'Désactiver la recherche WordPress',
        'disable_oembed'    => 'Désactiver oEmbed (YouTube, etc.)',
    ];

    foreach ($fields as $key => $label) {
        add_settings_field(
            $key,
            $label,
            'dnc_render_checkbox_field',
            'dnc_theme_options',
            'dnc_section_features',
            ['label_for' => $key]
        );
    }
});

// Fonction générique de rendu des cases à cocher
function dnc_render_checkbox_field($args) {
    $options = get_option('dnc_theme_options');
    $id = $args['label_for'];
    $checked = !empty($options[$id]) ? 'checked' : '';
    echo "<input type='checkbox' id='$id' name='dnc_theme_options[$id]' value='1' $checked />";
}

// Permet de charger la documentation dans le back office
add_action('wp_ajax_view_image_formats_doc', function () {
    $doc_url = get_stylesheet_directory_uri() . '/docs/image-formats.md'; // Adapté si ton fichier est dans /docs
    wp_redirect($doc_url);
    exit;
});
