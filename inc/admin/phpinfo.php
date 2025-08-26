<?php 
// Ajouter un menu dans l'admin WordPress pour afficher phpinfo pour un utilisateur spécifique
function ajouter_menu_phpinfo() {
    // ID de l'utilisateur autorisé à voir ce menu
    $user_id_autorise = 1; // Remplace '1' par l'ID de l'utilisateur spécifique

    // Vérifie si l'utilisateur actuel a l'ID spécifié
    if (get_current_user_id() == $user_id_autorise) {
        add_menu_page(
            'PHP Info',              // Titre de la page
            'PHP Info',              // Nom dans le menu
            'manage_options',        // Capacité nécessaire pour voir ce menu
            'phpinfo',               // Slug de la page (unique pour l'URL)
            'afficher_phpinfo',      // Fonction de callback qui affiche le phpinfo()
            'dashicons-info',        // Icône du menu (ici une icône d'info)
            100                       // Position dans le menu
        );
    }
}

add_action('admin_menu', 'ajouter_menu_phpinfo');

// Fonction pour afficher phpinfo
function afficher_phpinfo() {
    if (!current_user_can('manage_options') || get_current_user_id() !== 1) {
        wp_die('Accès refusé.');
    }

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Informations PHP</h1>';
    echo '<p>Ci-dessous les informations PHP du serveur :</p>';

    // Capture du phpinfo
    ob_start();
    phpinfo();
    $phpinfo = ob_get_clean();

    // Extraction du contenu entre <body>...</body>
    if (preg_match('%<body[^>]*>(.*?)</body>%is', $phpinfo, $regs)) {
        $phpinfo = $regs[1];
    }

    // Nettoyage des styles inline et simplification
    $phpinfo = preg_replace('%<style[^>]*>.*?</style>%is', '', $phpinfo); // Supprimer les styles
    $phpinfo = preg_replace('%<table%', '<table class="widefat striped"', $phpinfo); // Ajouter classes WordPress
    $phpinfo = preg_replace('%<h2%', '<h2 class="wp-heading-inline"', $phpinfo); // Adapter les titres

    // Affichage
    echo '<div class="phpinfo-content">';
    echo $phpinfo;
    echo '</div>';
    echo '</div>';
}


// Ajouter une classe CSS personnalisée dans l'admin pour l'utilisateur autorisé
function ajouter_classe_utilisateur_phpinfo($classes) {
    // ID de l'utilisateur autorisé
    $user_id_autorise = 1; // Remplace '1' par l'ID de l'utilisateur spécifique

    // Vérifie si l'utilisateur actuel a l'ID spécifié
    if (get_current_user_id() == $user_id_autorise) {
        $classes .= ' utilisateur-autorise-phpinfo';
    }

    return $classes;
}

add_filter('admin_body_class', 'ajouter_classe_utilisateur_phpinfo');

function style_phpinfo_admin() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_phpinfo') {
        echo '<style>
            .phpinfo-content h1, .phpinfo-content h2{text-align:center;}
            .phpinfo-content table { width: 900px; margin: 20px auto; }
            .phpinfo-content td, .phpinfo-content th { padding: 6px 10px; }
        </style>';
    }
}
add_action('admin_head', 'style_phpinfo_admin');
