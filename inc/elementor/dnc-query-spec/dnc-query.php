<?php
/**
 * Détermine l'ID de la page "courante" pour le Loop Grid,
 * y compris en mode édition Elementor (avec réglages d’aperçu).
 */
function dnc_get_current_or_preview_post_id() {
    // 1) Contexte front classique
    $id = get_queried_object_id();
    if ( $id ) {
        return (int) $id;
    }

    // 2) Fallback sur le global $post si présent
    global $post;
    if ( isset($post->ID) ) {
        return (int) $post->ID;
    }

    // 3) Contexte éditeur Elementor
    if ( class_exists( '\Elementor\Plugin' ) ) {
        $plugin = \Elementor\Plugin::$instance;

        // En mode édition dans l’éditeur ?
        if ( isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode() ) {
            // ID du document en cours d'édition (modèle/page)
            if ( method_exists($plugin->editor, 'get_post_id') ) {
                $editor_doc_id = (int) $plugin->editor->get_post_id();

                // Si l’auteur a configuré “Aperçu avec…”
                $preview_settings = get_post_meta( $editor_doc_id, '_elementor_preview_settings', true );
                if ( is_array($preview_settings) && !empty($preview_settings['post_id']) ) {
                    return (int) $preview_settings['post_id'];
                }

                // Sinon on retourne le document lui-même
                if ( $editor_doc_id ) {
                    return $editor_doc_id;
                }
            }
        }
    }

    return 0;
}

/**
 * Hook Elementor Loop Grid : retourne seulement les enfants de la page détectée,
 * fonctionne en front ET dans l’éditeur.
 *
 * À utiliser en mettant "enfants" comme Nom de requête dans le widget.
 */
add_action( 'elementor/query/enfants', function( $query ) {
    $parent_id = dnc_get_current_or_preview_post_id();

    // Si on a bien une page cible, on filtre sur ses enfants
    if ( $parent_id ) {
        $query->set( 'post_type', 'page' );
        $query->set( 'post_parent', $parent_id );
        $query->set( 'orderby', 'menu_order' );
        $query->set( 'order', 'ASC' );
        $query->set( 'posts_per_page', -1 );
        // Facultatif : ne montrer que les publiées côté front, mais tout dans l’éditeur
        if ( ! is_user_logged_in() || ! is_admin() ) {
            $query->set( 'post_status', 'publish' );
        }
        return;
    }

    // Sécurité : si on ne trouve pas d’ID (cas rare), on renvoie vide
    $query->set( 'post__in', [0] );
} );
