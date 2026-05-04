<?php
/**
 * ======================================================================
 *  DNC OPTIONS FRAMEWORK — PREMIUM UI EDITION
 *  (Message 1/3)
 * ======================================================================
 *  Fournit :
 *  - Cards Material Design
 *  - Accordéons animés
 *  - Onglets modernes
 *  - Switch iOS
 *  - Inputs premium (radio, checkbox, text, password)
 *  - Mode sombre intégré
 *  - Enqueue automatique du CSS/JS
 * ======================================================================
 */


/* =========================================================================
   ENQUEUE CSS & JS SUR LA PAGE Options DNC
   ===================================================================== */
add_action('admin_enqueue_scripts', function ($hook) {

    // Must match submenu slug : dnc_theme_options
    if ($hook !== 'appearance_page_dnc_theme_options') {
        return;
    }

    // Load main CSS (dans ton thème enfant)
    wp_enqueue_style(
        'dnc-options-css',
        get_stylesheet_directory_uri() . '/css/admin/dnc-options.css',
        [],
        filemtime(get_stylesheet_directory() . '/css/admin/dnc-options.css')
    );

    // Load JS
    wp_enqueue_script(
        'dnc-options-js',
        get_stylesheet_directory_uri() . '/js/admin/dnc-options.js',
        ['jquery'],
        filemtime(get_stylesheet_directory() . '/js/admin/dnc-options.js'),
        true
    );

    // Load Font Awesome
    wp_enqueue_style(
        'fa-admin',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        [],
        null
    );
});




/* =========================================================================
   UI HELPERS / STRUCTURE
   ===================================================================== */

/* -----------------------------------------
   Ouvre une card Material
----------------------------------------- */
function dnc_ui_open_card($title = '', $icon = 'fa-circle') {
    echo "<div class='dnc-card'>";
    if ($title) {
        echo "<h3 class='dnc-card-title'><i class='fa $icon'></i> $title</h3>";
    }
}

/* -----------------------------------------
   Ferme une card
----------------------------------------- */
function dnc_ui_close_card() {
    echo "</div>";
}




/* =========================================================================
   COMPOSANTS DE FORMULAIRE
   ===================================================================== */


/* -----------------------------------------
   SWITCH iOS
----------------------------------------- */
function dnc_ui_switch($id, $label, $desc = '') {
    $o = get_option('dnc_theme_options');
    $checked = !empty($o[$id]) ? 'checked' : '';

    echo "
    <div class='dnc-field-group dnc-has-switch'>
        <div class='dnc-field-top'>
            <span class='dnc-field-label'>$label</span>
            <label class='dnc-switch'>
                <input type='checkbox' name='dnc_theme_options[$id]' value='1' $checked>
                <span class='dnc-slider'></span>
            </label>
        </div>";

    if ($desc) {
        echo "<p class='dnc-field-desc'><i class='fa fa-info-circle'></i> $desc</p>";
    }

    echo "</div>";
}



/* -----------------------------------------
   RADIO GROUP MODERNE
----------------------------------------- */
function dnc_ui_radio($name, $choices, $value, $label, $desc = '') {

    echo "<div class='dnc-field-group'>
            <span class='dnc-field-label'>$label</span>
            <div class='dnc-input-row'>";

    foreach ($choices as $val => $text) {
        echo "<label class='dnc-radio'>
                <input type='radio' name='dnc_theme_options[$name]' value='$val' "
                . checked($value,$val,false) . ">
                <span>$text</span>
              </label>";
    }

    echo "</div>";

    if ($desc) {
        echo "<p class='dnc-field-desc'><i class='fa fa-info-circle'></i> $desc</p>";
    }

    echo "</div>";
}



/* -----------------------------------------
   CHECKBOX GROUP
----------------------------------------- */
function dnc_ui_checklist($name, $choices, $selected, $label, $desc = '') {

    echo "<div class='dnc-field-group'>
            <span class='dnc-field-label'>$label</span>
            <div class='dnc-input-row'>";

    foreach ($choices as $val => $text) {
        $isChecked = in_array($val,$selected) ? 'checked' : '';
        echo "<label class='dnc-check'>
                <input type='checkbox' name='dnc_theme_options[$name][]' value='$val' $isChecked>
                <span>$text</span>
              </label>";
    }

    echo "</div>";

    if ($desc) {
        echo "<p class='dnc-field-desc'><i class='fa fa-info-circle'></i> $desc</p>";
    }

    echo "</div>";
}



/* -----------------------------------------
   INPUT TEXT
----------------------------------------- */
function dnc_ui_text($name, $value, $label, $desc = '') {

    echo "<div class='dnc-field-group'>
            <span class='dnc-field-label'>$label</span>
            <input class='dnc-input' type='text' name='dnc_theme_options[$name]' value='".esc_attr($value)."' />";

    if ($desc) {
        echo "<p class='dnc-field-desc'><i class='fa fa-info-circle'></i> $desc</p>";
    }

    echo "</div>";
}



/* -----------------------------------------
   PASSWORD
----------------------------------------- */
function dnc_ui_password($name, $value, $label, $desc = '') {

    echo "<div class='dnc-field-group'>
            <span class='dnc-field-label'>$label</span>
            <input class='dnc-input' type='password' name='dnc_theme_options[$name]' value='".esc_attr($value)."' autocomplete='off' />";

    if ($desc) {
        echo "<p class='dnc-field-desc'><i class='fa fa-info-circle'></i> $desc</p>";
    }

    echo "</div>";
}

/* ======================================================================
 *  MESSAGE 2/3 — INTERFACE MODERNE (TABS + ACCORDIONS + CARDS)
 * ====================================================================== */

/* -------------------------------------------------------------
   PAGE ADMIN : Options DNC
------------------------------------------------------------- */
add_action('admin_menu', function () {
    add_submenu_page(
        'themes.php',
        'Options DNC',
        'Options DNC',
        'manage_options',
        'dnc_theme_options',
        'dnc_render_options_page'
    );
});



/* =========================================================================
   RENDER PAGE COMPLETE
   ===================================================================== */
function dnc_render_options_page() {

    $options = get_option('dnc_theme_options');

    ?>
    <div class="wrap dnc-wrap">

        <h1 class="dnc-title"><i class="fa fa-sliders"></i> Options DNC</h1>

        

        <!-- ===========================================
             ONGLETES
        ============================================-->
        <div class="dnc-tabs">

           <button class="dnc-tab active" data-tab="general">
                <i class="fa fa-gear"></i> Général
            </button>

            <button class="dnc-tab" data-tab="features">
                <i class="fa fa-wand-magic-sparkles"></i> Fonctions pratiques
            </button>

            <button class="dnc-tab" data-tab="weather">
                <i class="fa fa-cloud-sun"></i> Météo
            </button>

            <button class="dnc-tab" data-tab="advanced">
                <i class="fa fa-code"></i> Avancé
            </button>

            <button class="dnc-tab" data-tab="import-export">
                <i class="fa fa-file-export"></i> Import / Export
            </button>
        </div>


        <!-- ===========================================
             FORMULAIRE GLOBAL
        ============================================-->
        <form method="post" action="options.php" class="dnc-options-form">
            <?php settings_fields('dnc_theme_options_group'); ?>

            <!-- =======================================================
                 ONGLET : GENERAL
            ======================================================== -->
            <section class="dnc-tab-content active " id="dnc-tab-general">

                <?php
                dnc_ui_open_card("Réglages généraux", "fa-gear");

                dnc_ui_switch(
                    'disable_gutenberg',
                    'Désactiver Gutenberg',
                    "Revient à l’éditeur classique (TinyMCE)."
                );

                dnc_ui_switch(
                    'disable_blog',
                    'Désactiver les articles',
                    "Masque complètement la fonctionnalité blog."
                );

                dnc_ui_switch(
                    'disable_commentaires',
                    'Désactiver les commentaires',
                    "Supprime les commentaires, pingbacks et trackbacks."
                );

                // Choix mémoires
                $mem = $options['wp_memory_limit'] ?? 'default';
                dnc_ui_radio(
                    'wp_memory_limit',
                    [
                        'default' => 'Défaut WordPress',
                        '256M' => '256 Mo',
                        '512M' => '512 Mo',
                        '1024M' => '1 Go',
                        '2048M' => '2 Go',
                    ],
                    $mem,
                    "Limite de mémoire WordPress",
                    "Augmente la capacité d’exécution (hébergement permitting)."
                );

                dnc_ui_close_card();
                ?>
            </section>



            <!-- =======================================================
                 ONGLET : FONCTIONS PRATIQUES
            ======================================================== -->
            <section class="dnc-tab-content" id="dnc-tab-features">

                <?php
                dnc_ui_open_card("Fonctions pratiques", "fa-wand-magic-sparkles");

                $boolean_fields = [
                    'disable_emojis'    => ['Désactiver les emojis WP', "Décharge le frontend en supprimant les scripts inutiles."],
                    'clean_head'        => ['Nettoyer les balises <head>', "Supprime les meta inutiles (RSD, WLW, feeds…)."],
                    'disable_xmlrpc'    => ['Désactiver XML-RPC', "Renforce la sécurité en désactivant l'accès distant XML-RPC."],
                    'disable_rss'       => ['Désactiver les flux RSS', "Désactive les flux RSS, Atom et RDF."],
                    'hide_admin_bar'    => ['Masquer la barre admin', "Cache la barre admin pour les non-administrateurs."],
                    'disable_search'    => ['Désactiver la recherche WP', "Transforme toute recherche en 404."],
                    'disable_oembed'    => ['Désactiver oEmbed', "Désactive l’auto-embeding YouTube, Twitter, etc."],
                ];

                foreach ($boolean_fields as $key => [$label, $desc]) {
                    dnc_ui_switch($key, $label, $desc);
                }

                dnc_ui_close_card();
                ?>

            </section>



            <!-- =======================================================
                 ONGLET : METEO (VERSION SANS ACCORDEONS)
            ======================================================== -->
            <section class="dnc-tab-content" id="dnc-tab-weather">

                <?php
                /* ------------------------------------------------------------------
                   CARD 1 : ACTIVATION GLOBALE
                ------------------------------------------------------------------ */
                dnc_ui_open_card("Activation météo", "fa-cloud-sun");

                dnc_ui_switch(
                    'enable_weather',
                    'Activer la météo',
                    "Désactive totalement la météo : API, cache, widgets Elementor, requêtes, hooks..."
                );

                dnc_ui_close_card();



                /* ------------------------------------------------------------------
                   CARD 2 : API WEATHERSTACK
                ------------------------------------------------------------------ */
                dnc_ui_open_card("Clé API Weatherstack", "fa-key");

                $api = get_option('dn_weatherstack_api_key', '');

                dnc_ui_password(
                    'dn_weatherstack_api_key',
                    $api,
                    "Clé API Weatherstack",
                    "Utilisée par les widgets Elementor météo (carrousel)."
                );

                dnc_ui_close_card();



                /* ------------------------------------------------------------------
                   CARD 3 : CACHE METEO
                ------------------------------------------------------------------ */
                dnc_ui_open_card("Durée du cache météo", "fa-hourglass-half");

                $ttl = get_option('dnc_weather_cache_ttl', 3600);

                dnc_ui_radio(
                    'dnc_weather_cache_ttl',
                    [
                        3600  => "1 heure",
                        7200  => "2 heures",
                        21600 => "6 heures",
                        86400 => "24 heures",
                    ],
                    $ttl,
                    "Durée du cache",
                    "Réduit les appels API et accélère les widgets météo."
                );

                // 🔄 Bouton vider le cache
                echo "<p><a class='button button-secondary' 
                         href='" . admin_url("admin-post.php?action=dnc_flush_weather_cache") . "'>
                         <i class='fa fa-trash'></i> Vider le cache météo
                     </a></p>";

                dnc_ui_close_card();
                ?>

            </section>







            <!-- =======================================================
                 ONGLET : AVANCÉ
            ======================================================== -->
            <section class="dnc-tab-content" id="dnc-tab-advanced">

                <?php
                dnc_ui_open_card("Options avancées", "fa-code");

                dnc_ui_switch(
                    'disable_heartbeat',
                    'Désactiver Heartbeat API',
                    "Réduit la charge du serveur (édition d’articles, autosave...)."
                );

                dnc_ui_switch(
                    'disable_block_library_css',
                    'Retirer CSS Gutenberg du frontend',
                    "Optimisation pour sites entièrement Elementor."
                );

                dnc_ui_switch(
                    'disable_lazyload_wp',
                    'Désactiver lazyload natif WP',
                    "Utile si un plugin tiers gère déjà cette fonctionnalité."
                );

                dnc_ui_close_card();
                ?>

            </section>





            <!-- =======================================================
                 ONGLET : IMPORT / EXPORT
            ======================================================== -->
            <section class="dnc-tab-content" id="dnc-tab-import-export">

                <?php dnc_ui_open_card("Export des paramètres", "fa-download"); ?>

                <p>Exporter vos réglages au format JSON.</p>

                <a href="<?php echo admin_url('admin-post.php?action=dnc_export_options'); ?>"
                   class="button button-primary">
                    <i class="fa fa-file-export"></i> Exporter
                </a>

                <?php dnc_ui_close_card(); ?>


                <?php dnc_ui_open_card("Importer des paramètres", "fa-upload"); ?>

                <p>Importer un fichier JSON exporté précédemment.</p>

                <input type="file" name="dnc_import_file">
                <button class="button button-secondary" name="dnc_import_trigger" value="1">
                    <i class="fa fa-upload"></i> Importer
                </button>

                <?php dnc_ui_close_card(); ?>

            </section>




            <!-- =======================================================
                 SUBMIT BUTTON
            ======================================================== -->
            <p class="dnc-submit-wrapper">
                <button class="button button-primary dnc-save-btn">
                    <i class="fa fa-check"></i> Sauvegarder les options
                </button>
            </p>

        </form>

    </div>
<?php
}

/* ======================================================================
 *  MESSAGE 3/3 — LOGIQUE D'OPTIONS & OPTIMISATIONS SYSTEME
 * ====================================================================== */


/* ----------------------------------------------------------------------
   REGISTER SETTING : dnc_theme_options
---------------------------------------------------------------------- */
add_action('admin_init', function () {

    register_setting(
        'dnc_theme_options_group',
        'dnc_theme_options',
        [
            'sanitize_callback' => 'dnc_sanitize_options'
        ]
    );
});



/* ----------------------------------------------------------------------
   SANITIZE TOUTES LES OPTIONS DNC
---------------------------------------------------------------------- */
function dnc_sanitize_options($input) {

    $output = [];

    // Liste des champs booléens
    $boolean_fields = [
        'disable_gutenberg',
        'disable_blog',
        'disable_commentaires',
        'disable_emojis',
        'clean_head',
        'disable_xmlrpc',
        'disable_rss',
        'hide_admin_bar',
        'disable_search',
        'disable_oembed',
        'enable_weather',
        'disable_heartbeat',
        'disable_block_library_css',
        'disable_lazyload_wp',
    ];

    foreach ($boolean_fields as $key) {
        $output[$key] = !empty($input[$key]) ? 1 : 0;
    }

    // Radio memory limit
    $allowed_memory = ['default','256M','512M','1024M','2048M'];
    $output['wp_memory_limit'] = in_array($input['wp_memory_limit'] ?? 'default', $allowed_memory)
        ? $input['wp_memory_limit']
        : 'default';

    // Formats images checklist
    $allowed_formats = ['heic','tif','webp','avif','jxl'];
    $output['formats_acceptes'] = array_values(array_intersect(
        $input['formats_acceptes'] ?? [],
        $allowed_formats
    ));

    return $output;
}



/* ----------------------------------------------------------------------
   OPTION WEATHER : API KEY
---------------------------------------------------------------------- */
add_action('admin_init', function () {

    register_setting(
        'dnc_theme_options_group',
        'dn_weatherstack_api_key',
        ['sanitize_callback' => 'sanitize_text_field']
    );

    register_setting(
        'dnc_theme_options_group',
        'dnc_weather_cache_ttl',
        [
            'sanitize_callback' => function($v) {
                $v = absint($v);
                return in_array($v, [3600,7200,21600,86400]) ? $v : 3600;
            }
        ]
    );
});




/* ======================================================================
 *  OPTIMISATIONS SYSTEME (FRONTEND / BACKEND)
 * ====================================================================== */


/* ----------------------------------------------------------------------
   Désactiver Gutenberg
---------------------------------------------------------------------- */
add_filter('use_block_editor_for_post', function($bool) {
    $opts = get_option('dnc_theme_options');
    return !empty($opts['disable_gutenberg']) ? false : $bool;
});


/* ----------------------------------------------------------------------
   Désactiver les articles
---------------------------------------------------------------------- */
add_action('init', function () {
    $opts = get_option('dnc_theme_options');

    if (!empty($opts['disable_blog'])) {

        // Remove post type UI
        remove_menu_page('edit.php');

        // Disable post type support
        unregister_post_type('post');
    }
}, 20);


/* ----------------------------------------------------------------------
   Disable comments everywhere
---------------------------------------------------------------------- */
add_action('admin_init', function () {
    $o = get_option('dnc_theme_options');
    if (empty($o['disable_commentaires'])) return;

    // Close comments
    add_filter('comments_open', '__return_false', 20);
    add_filter('pings_open', '__return_false', 20);

    // Hide menu item
    remove_menu_page('edit-comments.php');
});


/* ----------------------------------------------------------------------
   Nettoyer <head>
---------------------------------------------------------------------- */
add_action('init', function () {
    $o = get_option('dnc_theme_options');
    if (empty($o['clean_head'])) return;

    remove_action('wp_head','rsd_link');
    remove_action('wp_head','wlwmanifest_link');
    remove_action('wp_head','wp_generator');
    remove_action('wp_head','wp_shortlink_wp_head');
    remove_action('wp_head','rest_output_link_wp_head');
    remove_action('wp_head','wp_oembed_add_discovery_links');
});


/* ----------------------------------------------------------------------
   Désactiver emojis
---------------------------------------------------------------------- */
add_action('init', function () {
    $o = get_option('dnc_theme_options');
    if (empty($o['disable_emojis'])) return;

    remove_action('wp_head','print_emoji_detection_script',7);
    remove_action('wp_print_styles','print_emoji_styles');
});


/* ----------------------------------------------------------------------
   Désactiver XML-RPC
---------------------------------------------------------------------- */
add_filter('xmlrpc_enabled', function () {
    $o = get_option('dnc_theme_options');
    return !empty($o['disable_xmlrpc']) ? false : true;
});


/* ----------------------------------------------------------------------
   Désactiver RSS
---------------------------------------------------------------------- */
add_action('init', function () {
    $o = get_option('dnc_theme_options');
    if (empty($o['disable_rss'])) return;

    foreach (['do_feed','do_feed_rdf','do_feed_rss','do_feed_rss2','do_feed_atom'] as $feed) {
        add_action($feed, function() {
            wp_die('RSS désactivé');
        });
    }
});


/* ----------------------------------------------------------------------
   Masquer barre admin pour non admins
---------------------------------------------------------------------- */
add_filter('show_admin_bar', function($show) {
    $o = get_option('dnc_theme_options');
    if (!empty($o['hide_admin_bar']) && !current_user_can('administrator')) {
        return false;
    }
    return $show;
});


/* ----------------------------------------------------------------------
   Désactiver la recherche WordPress
---------------------------------------------------------------------- */
add_action('parse_query', function($query) {
    $o = get_option('dnc_theme_options');
    if (!empty($o['disable_search']) && $query->is_search) {
        $query->is_search = false;
        $query->query_vars['s'] = false;
        $query->set_404();
    }
});


/* ----------------------------------------------------------------------
   Désactiver oEmbed
---------------------------------------------------------------------- */
add_action('init', function () {
    $o = get_option('dnc_theme_options');
    if (empty($o['disable_oembed'])) return;

    remove_action('wp_head','wp_oembed_add_host_js');
});


/* ----------------------------------------------------------------------
   Heartbeat OFF
---------------------------------------------------------------------- */
add_filter('heartbeat_settings', function($settings) {
    $o = get_option('dnc_theme_options');
    if (!empty($o['disable_heartbeat'])) {
        $settings['interval'] = 9999;
    }
    return $settings;
});


/* ----------------------------------------------------------------------
   Retirer CSS Gutenberg du frontend
---------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function() {
    $o = get_option('dnc_theme_options');
    if (!empty($o['disable_block_library_css'])) {
        wp_dequeue_style('wp-block-library');
    }
}, 100);


/* ----------------------------------------------------------------------
   Lazyload WP désactivé
---------------------------------------------------------------------- */
add_filter('wp_lazy_loading_enabled', function($bool) {
    $o = get_option('dnc_theme_options');
    return !empty($o['disable_lazyload_wp']) ? false : $bool;
});




/* ======================================================================
 *  METEO — PURGE CACHE + DISABLE COMPLET
 * ====================================================================== */


/* ---------------------------------------------
   Purge cache météo
--------------------------------------------- */
add_action('admin_post_dnc_flush_weather_cache', function () {

    if (!current_user_can('manage_options')) wp_die("Non autorisé");

    global $wpdb;

    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_dn_ws_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_dn_ws_%'");

    wp_safe_redirect(add_query_arg('dnc_flushed',1,wp_get_referer()));
    exit;
});



/* ======================================================================
 *  IMPORT / EXPORT JSON
 * ====================================================================== */


/* ---------------------------------------------
   EXPORT
--------------------------------------------- */
add_action('admin_post_dnc_export_options', function () {

    if (!current_user_can('manage_options')) {
        wp_die("Non autorisé");
    }

    $data = [
        'dnc_theme_options'      => get_option('dnc_theme_options'),
        'dn_weatherstack_api_key'=> get_option('dn_weatherstack_api_key'),
        'dnc_weather_cache_ttl'  => get_option('dnc_weather_cache_ttl')
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="dnc-options-export.json"');

    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
});



/* ---------------------------------------------
   IMPORT
--------------------------------------------- */
add_action('admin_init', function () {

    if (!isset($_POST['dnc_import_trigger'])) return;

    if (!current_user_can('manage_options')) {
        wp_die("Non autorisé");
    }

    if (!empty($_FILES['dnc_import_file']['tmp_name'])) {

        $json = file_get_contents($_FILES['dnc_import_file']['tmp_name']);
        $data = json_decode($json, true);

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                update_option($k, $v);
            }
        }
    }

    wp_safe_redirect(admin_url('themes.php?page=dnc_theme_options&imported=1'));
    exit;
});
