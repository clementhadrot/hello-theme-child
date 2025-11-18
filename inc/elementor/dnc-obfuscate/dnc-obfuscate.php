<?php
/**
 * Plugin léger : Obfuscation de liens pour Elementor (chargé depuis le thème)
 * Dossier : /wp-content/themes/votre-theme/dnc-elementor-obfuscate-links/
 */

if (!defined('ABSPATH')) { exit; }

final class DNC_Elementor_Obfuscate_Links {
    const VERSION = '1.0.0';
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_assets']); // styles dans l’éditeur (JS inactif pour ne pas gêner)

        // Ajout des contrôles de façon universelle et safe
        add_action('elementor/element/common/_section_style/after_section_end', [$this, 'register_controls'], 10, 2);

        // Ajout des attributs data-* juste avant le rendu (tous types)
        add_action('elementor/frontend/widget/before_render',  [$this, 'apply_attributes']);
        add_action('elementor/frontend/section/before_render', [$this, 'apply_attributes']);
        add_action('elementor/frontend/column/before_render',  [$this, 'apply_attributes']);

        // On change la balise a en span 
        add_filter('elementor/widget/render_content', [$this, 'filter_render_content'], 10, 2);
    }

    public function enqueue_assets() {
        $base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-obfuscate';

        wp_enqueue_style(
            'dnc-obuf-css',
            $base . '/assets/obfuscate.css',
            [],
            self::VERSION
        );

        // JS uniquement côté front public (pas dans l’éditeur pour ne pas perturber le clic sur les widgets)
        if (!is_admin() && !isset($_GET['elementor-preview'])) {
            wp_enqueue_script(
                'dnc-obuf-js',
                $base . '/assets/obfuscate.js',
                [],
                self::VERSION,
                true
            );
        }
    }

    public function register_controls( $element, $args ) {
        
        if (! $element instanceof \Elementor\Controls_Stack) {
            return;
        }

        $element->start_controls_section(
            'dnc_obuf_section',
            [
                'label' => __('Obfusquer les liens', 'dnc-obuf'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->add_control(
            'dnc_obuf_enable',
            [
                'label'        => __('Activer', 'dnc-obuf'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
                'description'  => __('Encode les href et ne révèle l’URL qu’au clic (JS).', 'dnc-obuf'),
            ]
        );

        $element->add_control(
            'dnc_obuf_scope',
            [
                'label'     => __('Cible', 'dnc-obuf'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => [
                    'first'    => __('Premier lien du widget', 'dnc-obuf'),
                    'all'      => __('Tous les liens du widget', 'dnc-obuf'),
                    'selector' => __('Sélecteur CSS', 'dnc-obuf'),
                ],
                'default'   => 'first',
                'condition' => ['dnc_obuf_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_obuf_selector',
            [
                'label'       => __('Sélecteur CSS', 'dnc-obuf'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('ex: a.button, .mon-bloc a', 'dnc-obuf'),
                'condition'   => [
                    'dnc_obuf_enable' => 'yes',
                    'dnc_obuf_scope'  => 'selector',
                ],
            ]
        );

        $element->add_control(
            'dnc_obuf_mode',
            [
                'label'     => __('Mode', 'dnc-obuf'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => [
                    'auto'        => __('Auto (tous liens)', 'dnc-obuf'),
                    'mailto'      => __('Seulement mailto:', 'dnc-obuf'),
                    'urls'        => __('Seulement URL (hors mailto)', 'dnc-obuf'),
                ],
                'default'   => 'auto',
                'condition' => ['dnc_obuf_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_obuf_target',
            [
                'label'     => __('Ouverture', 'dnc-obuf'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'options'   => [
                    'same'  => __('Même onglet', 'dnc-obuf'),
                    'blank' => __('Nouvel onglet', 'dnc-obuf'),
                ],
                'default'   => 'same',
                'condition' => ['dnc_obuf_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_obuf_rel',
            [
                'label'       => __('rel à appliquer', 'dnc-obuf'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => 'nofollow noopener noreferrer',
                'placeholder' => 'nofollow noopener noreferrer',
                'condition'   => ['dnc_obuf_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'dnc_obuf_skip_logged',
            [
                'label'        => __('Ne pas obfusquer pour les connectés', 'dnc-obuf'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => ['dnc_obuf_enable' => 'yes'],
            ]
        );

        $element->end_controls_section();
    }

    public function apply_attributes( $element ) {
        $settings = $element->get_settings_for_display();

        if ( empty($settings['dnc_obuf_enable']) || 'yes' !== $settings['dnc_obuf_enable'] ) {
            return;
        }

        // Option: ne pas obfusquer pour les utilisateurs connectés (édition, QA, etc.)
        if ( !empty($settings['dnc_obuf_skip_logged']) && 'yes' === $settings['dnc_obuf_skip_logged'] && is_user_logged_in() ) {
            return;
        }

        $scope    = !empty($settings['dnc_obuf_scope']) ? $settings['dnc_obuf_scope'] : 'first';
        $selector = !empty($settings['dnc_obuf_selector']) ? $settings['dnc_obuf_selector'] : '';
        $mode     = !empty($settings['dnc_obuf_mode']) ? $settings['dnc_obuf_mode'] : 'auto';
        $target   = (!empty($settings['dnc_obuf_target']) && in_array($settings['dnc_obuf_target'], ['same','blank'], true)) ? $settings['dnc_obuf_target'] : 'same';
        $rel      = isset($settings['dnc_obuf_rel']) ? sanitize_text_field($settings['dnc_obuf_rel']) : 'nofollow noopener noreferrer';

        // Ajout d’attributs data-* sur le wrapper du widget
        $element->add_render_attribute('_wrapper', 'class', 'dnc-obuf-scope');
        $element->add_render_attribute('_wrapper', 'data-dnc-obuf', '1');
        $element->add_render_attribute('_wrapper', 'data-dnc-obuf-scope', esc_attr($scope));
        if ($selector) {
            $element->add_render_attribute('_wrapper', 'data-dnc-obuf-selector', esc_attr($selector));
        }
        $element->add_render_attribute('_wrapper', 'data-dnc-obuf-mode', esc_attr($mode));
        $element->add_render_attribute('_wrapper', 'data-dnc-obuf-target', esc_attr($target));
        $element->add_render_attribute('_wrapper', 'data-dnc-obuf-rel', esc_attr($rel));
    }


    public function filter_render_content( $content, $widget ) {
    // Lire les réglages du widget
    if ( ! method_exists($widget, 'get_settings_for_display') ) return $content;
    $settings = $widget->get_settings_for_display();

    // Actif ?
    if ( empty($settings['dnc_obuf_enable']) || 'yes' !== $settings['dnc_obuf_enable'] ) return $content;

    // Skip pour connectés ?
    if ( !empty($settings['dnc_obuf_skip_logged']) && 'yes' === $settings['dnc_obuf_skip_logged'] && is_user_logged_in() ) {
        return $content;
    }

    // Mode de rendu
    $render = 'span';//!empty($settings['dnc_obuf_render']) ? $settings['dnc_obuf_render'] : 'span';
    if ( $render !== 'span' ) return $content; // On ne modifie que dans ce mode

    // Paramètres d’obfuscation
    $mode   = !empty($settings['dnc_obuf_mode']) ? $settings['dnc_obuf_mode'] : 'auto'; // auto|mailto|urls
    $target = (!empty($settings['dnc_obuf_target']) && in_array($settings['dnc_obuf_target'], ['same','blank'], true)) ? $settings['dnc_obuf_target'] : 'same';
    $rel    = isset($settings['dnc_obuf_rel']) ? sanitize_text_field($settings['dnc_obuf_rel']) : 'nofollow noopener noreferrer';
    $scope  = !empty($settings['dnc_obuf_scope']) ? $settings['dnc_obuf_scope'] : 'first'; // first|all|selector
    $selector = !empty($settings['dnc_obuf_selector']) ? $settings['dnc_obuf_selector'] : '';

    // NOTE : pour la simplicité, on gère ici first|all. Le mode "selector" reste géré côté JS.
    if ($scope === 'selector') return $content;

    // Parse HTML partiel de widget
    if ( trim($content) === '' ) return $content;
    if ( !class_exists('DOMDocument') ) return $content;

    $orig = $content;
    $html = '<div id="dnc-root">'.$content.'</div>';

    $libxml_prev = libxml_use_internal_errors(true);
    $doc = new \DOMDocument();
    // Force UTF-8
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xp = new \DOMXPath($doc);
    $root = $xp->query('//*[@id="dnc-root"]')->item(0);
    if (!$root) { libxml_clear_errors(); libxml_use_internal_errors($libxml_prev); return $orig; }

    // Collecter les <a>
    $aNodes = $root->getElementsByTagName('a');
    $links = [];
    foreach ($aNodes as $a) { $links[] = $a; }
    if (!$links) { libxml_clear_errors(); libxml_use_internal_errors($libxml_prev); return $orig; }

    // Fonction: doit-on obfusquer ce href ?
    $should = function($href) use ($mode) {
        if (!$href) return false;
        $isMail = preg_match('/^mailto:/i', $href) === 1;
        if ($mode === 'mailto') return $isMail;
        if ($mode === 'urls')   return !$isMail;
        return true;
    };

    $count = 0;
    foreach ($links as $a) {
        $href = $a->getAttribute('href');
        if (!$should($href)) continue;

        $encoded = base64_encode($href ?: '');
        // Créer un <span role="link" tabindex="0">
        $span = $doc->createElement('span');
        $span->setAttribute('role', 'link');
        $span->setAttribute('tabindex', '0');
        $span->setAttribute('class', trim($a->getAttribute('class') . ' dnc-obfuscated'));
        // Copier quelques attributs utiles (sans href/target/rel)
        foreach (['id','style','title','aria-label','data-tooltip','data-title'] as $attr) {
            if ($a->hasAttribute($attr)) $span->setAttribute($attr, $a->getAttribute($attr));
        }
        // Données pour le JS
        $span->setAttribute('data-dnc-obf', $encoded);
        $span->setAttribute('data-dnc-obuf-target', $target);
        $span->setAttribute('data-dnc-obuf-rel', $rel);
        $span->setAttribute('data-dnc-obf-gen', '1');

        // Déplacer tout le contenu de <a> dans <span>
        while ($a->firstChild) { $span->appendChild($a->firstChild); }

        // Remplacer dans le DOM
        $a->parentNode->replaceChild($span, $a);

        $count++;
        if ($scope === 'first' && $count >= 1) break;
    }

    // Extraire HTML sans le wrapper
    $new = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $new .= $doc->saveHTML($child);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($libxml_prev);

    return $new ?: $orig;
}


}

add_action('after_setup_theme', function() {
    // Charge uniquement si Elementor actif
    if ( did_action('elementor/loaded') ) {
        DNC_Elementor_Obfuscate_Links::instance();
    }
});
