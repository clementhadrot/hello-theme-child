<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * =====================================================================
 *  Feature flags & defaults (surchargables via filters)
 * =====================================================================
 */
function dn_cc_is_enabled() {
    return apply_filters( 'dn_cc_enabled', true );
}
function dn_cc_icon_url( $context ) {
    return apply_filters( 'dn_copyright_icon_url', get_stylesheet_directory_uri() . '/img/copyright.svg', $context );
}
function dn_cc_icon_alt( $context ) {
    return apply_filters( 'dn_copyright_icon_alt', __( 'Copyright', 'text-domain' ), $context );
}
function dn_cc_icon_pos( $context ) {
    // 'before' | 'after'
    return apply_filters( 'dn_copyright_icon_position', 'after', $context );
}
function dn_cc_default_align( $context ) {
    return apply_filters( 'dn_copyright_default_align', 'center', $context );
}
function dn_cc_should_skip_html( $html, $widget_name ) {
    // Bypass rapide (pas de DOM) si aucun marqueur
    if ( false === stripos( $html, '<img' ) && false === strpos( $html, 'e-gallery-item' ) ) return true;
    // Désactivation par filtre
    if ( ! dn_cc_is_enabled() ) return true;
    // En editor Elementor live preview on garde actif, mais vous pouvez choisir de désactiver:
    $skip_in_admin = apply_filters( 'dn_cc_skip_in_admin', false );
    if ( is_admin() && $skip_in_admin && ! wp_doing_ajax() ) return true;
    return false;
}

/**
 * =====================================================================
 *  Elementor Controls – Image (simple)
 * =====================================================================
 */
/* add_action( 'elementor/element/image/section_image/after_section_end', function( $element ) {
    $element->start_controls_section(
        'custom_copyright_section',
        [
            'label' => __( 'Copyright', 'text-domain' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]
    );
    $element->add_control(
        'custom_image_copyright',
        [
            'label'       => __( 'Copyright Text', 'text-domain' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => '© 2025 John Doe',
            'description' => __( 'Utilisé si l’image ne provient pas de la médiathèque ou si sa légende est vide.', 'text-domain' ),
        ]
    );
    $element->end_controls_section();
}, 10, 1);*/

/**
 * =====================================================================
 *  Elementor Controls – Galleries (free + pro)
 * =====================================================================
 */
add_action( 'elementor/element/image-gallery/section_gallery/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );
add_action( 'elementor/element/image-carousel/section_image_carousel/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );
add_action( 'elementor/element/media-carousel/section_content/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );
add_action( 'elementor/element/gallery/settings/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );
add_action( 'elementor/element/gallery/overlay/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );
add_action( 'elementor/element/gallery/section_content/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );
add_action( 'elementor/element/gallery/section_layout/after_section_end', 'dn_add_gallery_copyright_controls', 10, 2 );

function dn_add_gallery_copyright_controls( $element ) {
    static $added = [];
    $key = spl_object_hash( $element );
    if ( isset( $added[ $key ] ) ) return;
    $added[ $key ] = true;

    $element->start_controls_section(
        'dn_copyright_section',
        [
            'label' => __( 'Copyright', 'text-domain' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]
    );
    $element->add_control(
        'dn_use_attachment_caption',
        [
            'label'        => __( 'Utiliser la légende du média', 'text-domain' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => __( 'Oui', 'text-domain' ),
            'label_off'    => __( 'Non', 'text-domain' ),
            'return_value' => 'yes',
            'default'      => 'yes',
        ]
    );
    $element->add_control(
        'dn_default_copyright',
        [
            'label'       => __( 'Copyright par défaut', 'text-domain' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => '© 2025 John Doe',
            'description' => __( 'Utilisé si la légende du média est vide ou si l’option ci-dessus est désactivée.', 'text-domain' ),
        ]
    );
    $element->add_control(
        'dn_caption_position',
        [
            'label'   => __( 'Position', 'text-domain' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'below',
            'options' => [
                'above' => __( 'Au-dessus de l’image', 'text-domain' ),
                'below' => __( 'Au-dessous de l’image', 'text-domain' ),
            ],
        ]
    );
    $element->add_control(
        'dn_caption_align',
        [
            'label'   => __( 'Alignement', 'text-domain' ),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'default' => 'center',
            'options' => [
                'left'   => [ 'title' => __( 'Gauche', 'text-domain' ),  'icon' => 'eicon-text-align-left' ],
                'center' => [ 'title' => __( 'Centre', 'text-domain' ),  'icon' => 'eicon-text-align-center' ],
                'right'  => [ 'title' => __( 'Droite', 'text-domain' ),  'icon' => 'eicon-text-align-right' ],
            ],
        ]
    );
    $element->end_controls_section();
}

/**
 * =====================================================================
 *  Render router – un seul filtre
 * =====================================================================
 */
add_filter( 'elementor/widget/render_content', function( $content, $widget ) {
    $name     = $widget->get_name();
    $settings = $widget->get_settings_for_display();

    // --- IMAGE STANDARD -------------------------------------------------
    if ( 'image' === $name ) {
        // 1) Récup ID du média si dispo, sinon on tente via le HTML rendu
        $attachment_id = ! empty( $settings['image']['id'] ) ? (int) $settings['image']['id'] : 0;
        if ( ! $attachment_id ) {
            $src = dn_extract_img_src_from_html( $content );
            if ( $src ) $attachment_id = dn_attachment_id_from_maybe_cdn_url( $src );
        }

        // 2) Légende: post_excerpt sinon champ custom (ton contrôle existant)
        if ( $attachment_id ) {
            $caption_text = dn_get_attachment_title_cached( $attachment_id );
            $copyright_text = dn_get_attachment_copyright_cached( $attachment_id );
        }
        
        $custom     = ! empty( $settings['custom_image_copyright'] ) ? (string) $settings['custom_image_copyright'] : '';
        $caption    = ( isset($caption_text) && '' !== trim( $caption_text ) ) ? $caption_text : $custom;

        // 3) Icone via filtres globaux
        $icon_url = apply_filters( 'dn_copyright_icon_url', get_stylesheet_directory_uri() . '/img/copyright.svg', 'image' );
        $icon_alt = apply_filters( 'dn_copyright_icon_alt', __( 'Copyright', 'text-domain' ), 'image' );
        $icon_pos = apply_filters( 'dn_copyright_icon_position', 'after', 'image' ); // 'before'|'after'

        // 4) Injection du <figcaption> à l’intérieur d’une figure si présente, sinon on crée la figure
        $prev = libxml_use_internal_errors( true );
        $dom  = new DOMDocument('1.0', 'UTF-8');
        $wrap = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>'.$content.'</body></html>';
        if ( ! $dom->loadHTML( $wrap, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
            libxml_clear_errors(); libxml_use_internal_errors( $prev ); return $content;
        }
        $xpath = new DOMXPath( $dom );

        // Cherche d’abord une figure existante autour de l’image
        $figure = $xpath->query('//figure')->item(0);
        $img    = $xpath->query('//img')->item(0);

        if ( ! $img instanceof DOMElement ) {
            libxml_clear_errors(); libxml_use_internal_errors( $prev );
            return $content;
        }

        if ( ! $figure instanceof DOMElement ) {
            // crée une figure autour de l’img sans casser le reste
            $figure = $dom->createElement('figure');
            $figure->setAttribute('class', 'dn-figure');
            $img->parentNode->insertBefore( $figure, $img );
            $figure->appendChild( $img );
        } else {
            // ajoute la classe et nettoie d’éventuels captions précédents
            $cls = (string) $figure->getAttribute('class');
            if ( false === strpos( $cls, 'dn-figure' ) ) {
                $figure->setAttribute( 'class', trim( $cls . ' dn-figure' ) );
            }
            foreach ( iterator_to_array( $figure->getElementsByTagName('figcaption') ) as $old ) {
                $figure->removeChild( $old );
            }
        }

        // 5) Construit le figcaption (align par défaut via filtre si tu veux)
        $align = apply_filters( 'dn_copyright_default_align', 'center', 'image' );
        $figcaption   = $dom->createElement( 'figcaption' );
        
        $text = $dom->createTextNode( wp_strip_all_tags( $caption ) );
        $span = $dom->createElement('span');
        if (!empty($text) && $text!=' ') {
            $span->setAttribute( 'class', 'dn-caption-text');
            $span->appendChild( $text );
        }
        if(isset($copyright_text))
        $copytxt = wp_strip_all_tags( (string) $copyright_text );
        else $copytxt ='';
        
        if ( ! empty( $icon_url ) ) {
            $ico = $dom->createElement('img');
            $icondisp = false;
            if(!empty($copytxt)) {
                $ico->setAttribute('src', esc_url( $icon_url ) );
                $ico->setAttribute('alt', esc_attr( $icon_alt ?: 'Copyright' ) );
                $ico->setAttribute('class', 'dn-caption-img' );
                $ico->setAttribute('title', $copytxt );
                $ico->setAttribute('data-dnc-tooltip', $copytxt);
                $ico->setAttribute('data-dnc-placement', 'top');
                $ico->setAttribute('data-dnc-trigger', 'mouseenter focus click touch');
                $ico->setAttribute('data-dnc-theme', 'dark');
                $ico->setAttribute('data-dnc-maxwidth', '260');
                $ico->setAttribute('data-dnc-delay', '50');
                $icondisp = true;
            }
            
            if(trim($span->textContent) !== '' || $span->hasChildNodes()) $figcaption->appendChild( $span );
            if($icondisp) $figcaption->appendChild( $ico );
        } 
        
        if($figcaption->hasChildNodes()){
            $figcaption->setAttribute( 'class', 'dn-copyright dn-copy2 dn-align-' . sanitize_html_class( $align ) );
            $figure->appendChild( $figcaption );
        }


        // save
        $body = $dom->getElementsByTagName('body')->item(0);
        $out  = '';
        foreach ( $body->childNodes as $child ) { $out .= $dom->saveHTML( $child ); }
        libxml_clear_errors(); libxml_use_internal_errors( $prev );
        return $out;
    }

    // --- GALLERIES & CAROUSELS ------------------------------------------
    $use_caption = ( ! empty( $settings['dn_use_attachment_caption'] ) && 'yes' === $settings['dn_use_attachment_caption'] );
    $fallback    = isset( $settings['dn_default_copyright'] ) ? (string) $settings['dn_default_copyright'] : '';
    $position    = isset( $settings['dn_caption_position'] ) ? (string) $settings['dn_caption_position'] : 'below';
    $align       = isset( $settings['dn_caption_align'] ) ? (string) $settings['dn_caption_align'] : 'center';

    $icon_url = apply_filters( 'dn_copyright_icon_url', get_stylesheet_directory_uri() . '/img/copyright.svg', $name );
    $icon_alt = apply_filters( 'dn_copyright_icon_alt', __( 'Copyright', 'text-domain' ), $name );
    $icon_pos = apply_filters( 'dn_copyright_icon_position', 'after', $name ); // before|after

    // IDs pour carousel/galerie (servira pour les légendes)
    $attachment_ids = [];
    if ( in_array( $name, [ 'image-gallery', 'image-carousel', 'media-carousel' ], true ) ) {
       $attachment_ids = dn_extract_gallery_ids_from_settings( $settings );
    }

    if ( 'gallery' === $name ) {
        $new = dn_wrap_pro_gallery_with_figcaption( $content, $use_caption, $fallback, $position, $align, $icon_url, $icon_alt, $icon_pos );
        return $new ?: $content;
    }
    if ( in_array( $name, [ 'image-gallery', 'image-carousel', 'media-carousel' ], true ) ) {
        $new = dn_wrap_imgs_with_figcaption( $content, $use_caption, $fallback, $position, $align, $icon_url, $icon_alt, $icon_pos, $attachment_ids );
        return $new ?: $content;
    }

    return $content;
}, 999, 2 );


/**
 * =====================================================================
 *  PRO GALLERY
 * =====================================================================
 */
function dn_wrap_pro_gallery_with_figcaption(
    $html,
    $use_caption = true,
    $fallback = '',
    $position = 'below',
    $align = 'center',
    $caption_img_url = '',
    $caption_img_alt = 'Copyright',
    $caption_img_position = 'after'
) {
    if ( empty( $html ) || false === strpos( $html, 'e-gallery-item' ) ) return $html;

    $state = dn_dom_load( $html );
    if ( ! $state['dom'] ) return $html;
    [ $dom, $prev ] = [ $state['dom'], $state['prev'] ];

    $xpath = new DOMXPath( $dom );
    $items = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " e-gallery-item ")]');
    if ( ! $items || 0 === $items->length ) {
        dn_dom_restore( $prev );
        return $html;
    }

    /** @var DOMElement $a */
    foreach ( $items as $a ) {
        // idempotence
        $parentFigure = dn_find_ancestor( $a, 'figure' );
        if ( $parentFigure instanceof DOMElement && $parentFigure->hasAttribute('data-dn-processed') ) continue;

        $url = dn_galleryitem_best_url( $a );
        $caption_text = '';
        $copyright_text = '';
        if ( $use_caption && $url ) {
            $id = dn_attachment_id_from_maybe_cdn_url( $url );
            if ( $id ) $caption_text = dn_get_attachment_title_cached( $id );
            if ( $id ) $copyright_text = dn_get_attachment_copyright_cached( $id );
        }
        if ( '' === trim( $caption_text ) ) $caption_text = (string) $fallback;
        //if ( '' === trim( $caption_text ) ) continue;

        // wrap figure
        $figure = ( $parentFigure instanceof DOMElement ) ? $parentFigure : $dom->createElement('figure');
        $figure->setAttribute( 'class', trim( $figure->getAttribute('class') . ' dn-figure' ) );
        $figure->setAttribute( 'data-dn-processed', '1' );

        if ( ! $parentFigure ) {
            $p = $a->parentNode;
            $p->replaceChild( $figure, $a );
            $figure->appendChild( $a );
        } else {
            // purge anciens figcaption
            foreach ( iterator_to_array( $figure->getElementsByTagName('figcaption') ) as $old ) {
                $figure->removeChild( $old );
            }
        }

        $figcaption = dn_dom_build_figcaption( $dom, $caption_text,$copyright_text, $align, $caption_img_url, $caption_img_alt, $caption_img_position );
        
        if(!empty($figcaption) && $figcaption->hasChildNodes()) {
            $figure->appendChild( $figcaption );
        }
    }

    $out = dn_dom_save_body( $dom );
    dn_dom_restore( $prev );
    return $out;
}

/**
 * =====================================================================
 *  Fallback IMG (compat Swiper) – plus sélectif
 * =====================================================================
 */
function dn_wrap_imgs_with_figcaption(
    $html,
    $use_caption = true,
    $fallback = '',
    $position = 'below',
    $align = 'center',
    $caption_img_url = '',
    $caption_img_alt = 'Copyright',
    $caption_img_position = 'after',
    $attachment_ids = []
) {
    if ( empty( $html ) || stripos( $html, '<img' ) === false ) return $html;

    $prev = libxml_use_internal_errors( true );
    $dom  = new DOMDocument('1.0', 'UTF-8');
    $wrap = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>'.$html.'</body></html>';
    if ( ! $dom->loadHTML( $wrap, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
        libxml_clear_errors(); libxml_use_internal_errors( $prev );
        return $html;
    }
    $xpath = new DOMXPath( $dom );

    // Cible prioritaire: toutes les figures de slides (plus robuste que la chaîne de parents)
    $figures = $xpath->query('//figure[contains(concat(" ", normalize-space(@class), " "), " swiper-slide-inner ")]');

    // Fallback si widget sans figure.swiper-slide-inner
    if ( ! $figures || $figures->length === 0 ) {
        $figures = $xpath->query('//img');
    }

    $idx = 0;
    foreach ( $figures as $node ) {
        // Normalise: $figure et $img
        if ( strtolower($node->nodeName) === 'figure' ) {
            $figure = $node;
            $img = $xpath->query('.//img[contains(concat(" ", normalize-space(@class), " "), " swiper-slide-image ")]', $figure)->item(0);
            if ( ! ($img instanceof DOMElement) ) {
                $img = $xpath->query('.//img', $figure)->item(0);
            }
        } else { // node = <img> (fallback)
            $img = $node;
            $figure = dn_find_ancestor( $img, 'figure' );
            if ( ! ($figure instanceof DOMElement) ) {
                $figure = $dom->createElement('figure');
                $figure->setAttribute('class', 'dn-figure');
                $img->parentNode->insertBefore( $figure, $img );
                $figure->appendChild( $img );
            }
        }
        if ( ! ($img instanceof DOMElement) ) { $idx++; continue; }

        // Nettoyage / classe / idempotence
        $cls = (string) $figure->getAttribute('class');
        if ( strpos($cls, 'dn-figure') === false ) {
            $figure->setAttribute('class', trim($cls.' dn-figure') );
        }
        foreach ( iterator_to_array( $figure->getElementsByTagName('figcaption') ) as $old ) {
            $figure->removeChild( $old );
        }
        $figure->setAttribute('data-dn-processed', '1');

        // ---- LÉGENDE & COPYRIGHT (ordre: IDs -> URL -> fallback -> alt)
        $caption_text   = '';
        $copyright_text = '';

        // 1) Mapping par index depuis les IDs du widget (fiable, même avec /elementor/thumbs/)
        if ( $use_caption && !empty($attachment_ids) && isset($attachment_ids[$idx]) ) {
            $aid = (int) $attachment_ids[$idx];
            if ( $aid ) {
                // tu utilises "title" comme caption, et "post_excerpt" comme copyright
                $caption_text   = dn_get_attachment_title_cached( $aid );
                $copyright_text = dn_get_attachment_copyright_cached( $aid );
            }
            //echo 'MAPPING';
        }

        // 2) Fallback via URL (si pas d'IDs récupérés)
        if ( $use_caption && trim($caption_text) === '' && trim($copyright_text) === '' ) {
            $src = dn_first_non_empty([
                $img->getAttribute('src'),
                $img->getAttribute('data-src'),
                $img->getAttribute('data-lazy-src'),
            ]);
            if ( ! $src ) {
                $srcset = dn_first_non_empty([
                    $img->getAttribute('srcset'),
                    $img->getAttribute('data-srcset'),
                ]);
                if ( $srcset ) {
                    $first = preg_split('/\s*,\s*/', $srcset)[0] ?? '';
                    if ( $first ) $src = trim( preg_split('/\s+/', $first)[0] );
                }
            }
            if ( $src ) {
                $aid = dn_attachment_id_from_maybe_cdn_url( $src );
                if ( $aid ) {
                    $caption_text   = dn_get_attachment_title_cached( $aid );
                    $copyright_text = dn_get_attachment_copyright_cached( $aid );
                }
            }
            //echo 'FALLBACK VIA URL';
        }

        // 3) Fallback UI (si tu veux forcer un texte quand rien en base)
        if ( trim($caption_text) === '' && $fallback !== '' ) {
            $caption_text = (string) $fallback;
            //echo 'FALLBACK';
        }

        // 4) Dernier filet: alt (pour ne pas rester muet si au moins un des deux est attendu)
        if ( trim($caption_text) === '' && trim($copyright_text) === '' ) {
            $alt = (string) $img->getAttribute('alt');
            if ( $alt !== '' ) $caption_text = $alt;
            //echo 'ALT';
        }

        // Rien du tout ? on passe
        if ( trim($caption_text) === '' && trim($copyright_text) === '' ) { $idx++; continue; }

        // ---- FIGCAPTION
        $figcaption = $dom->createElement('figcaption');
        $figcaption->setAttribute( 'class', 'dn-copyright dn-align-'.sanitize_html_class($align) );

        // Texte (caption)
        if ( trim($caption_text) !== '' ) {
            $span = $dom->createElement('span');
            $span->setAttribute( 'class', 'dn-caption-text');
            $span->appendChild( $dom->createTextNode( wp_strip_all_tags( $caption_text ) ) );
            $figcaption->appendChild( $span );
        }

        // Icône (copyright)
        if ( trim($copyright_text) !== '' && ! empty( $caption_img_url ) ) {
            $ico = $dom->createElement('img');
            $ico->setAttribute('src', esc_url( $caption_img_url ) );
            $ico->setAttribute('alt', esc_attr( $caption_img_alt ?: 'Copyright' ) );
            $ico->setAttribute('class', 'dn-caption-img' );
            $ico->setAttribute('title', wp_strip_all_tags( $copyright_text ) );
            $ico->setAttribute('data-dnc-tooltip', $copyright_text);
            $ico->setAttribute('data-dnc-placement', 'top');
            $ico->setAttribute('data-dnc-trigger', 'mouseenter focus click touch');
            $ico->setAttribute('data-dnc-theme', 'dark');
            $ico->setAttribute('data-dnc-maxwidth', '260');
            $ico->setAttribute('data-dnc-delay', '50');

            if ( strtolower($caption_img_position) === 'before' && $figcaption->firstChild ) {
                $figcaption->insertBefore( $dom->createTextNode(' '), $figcaption->firstChild );
                $figcaption->insertBefore( $ico, $figcaption->firstChild );
            } else {
                if ( $figcaption->lastChild ) $figcaption->appendChild( $dom->createTextNode(' ') );
                $figcaption->appendChild( $ico );
            }
        }

        // Placement: above/below (mais avant le preloader s'il existe)
        if ( strtolower($position) === 'above' ) {
            $figure->insertBefore( $figcaption, $figure->firstChild ?: null );
        } else {
            $preloader = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " swiper-lazy-preloader ")]', $figure)->item(0);
            if ( $preloader instanceof DOMElement ) {
                $figure->insertBefore( $figcaption, $preloader );
            } else {
                $figure->appendChild( $figcaption );
            }
        }

        $idx++;
    }

    // Save
    $body = $dom->getElementsByTagName('body')->item(0);
    $out  = '';
    foreach ( $body->childNodes as $child ) { $out .= $dom->saveHTML( $child ); }
    libxml_clear_errors(); libxml_use_internal_errors( $prev );
    return $out;
}




/**
 * =====================================================================
 *  Helpers DOM / Media (factorisés + caches)
 * =====================================================================
 */
function dn_dom_load( $html ) {
    $prev = libxml_use_internal_errors( true );
    $dom  = new DOMDocument( '1.0', 'UTF-8' );
    $wrap = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $html . '</body></html>';
    if ( ! $dom->loadHTML( $wrap, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );
        return [ 'dom' => null, 'prev' => $prev ];
    }
    return [ 'dom' => $dom, 'prev' => $prev ];
}
function dn_dom_save_body( DOMDocument $dom ) {
    $body = $dom->getElementsByTagName('body')->item(0);
    $out  = '';
    foreach ( $body->childNodes as $child ) {
        $out .= $dom->saveHTML( $child );
    }
    libxml_clear_errors();
    return $out;
}
function dn_dom_restore( $prev ) {
    libxml_use_internal_errors( $prev );
}

function dn_dom_build_figcaption( DOMDocument $dom, $text,$copyright_text, $align = 'center',       $icon_url = '', $icon_alt = 'Copyright', $icon_pos = 'after' ) {
    $figcaption = $dom->createElement( 'figcaption' );
    
    $txt = $dom->createTextNode( wp_strip_all_tags( (string) $text ) );
    $copytxt = wp_strip_all_tags( (string) $copyright_text );
    
    $span = $dom->createElement('span');
    if (!empty($txt)) {
        $span->setAttribute( 'class', 'dn-caption-text');
        $span->appendChild( $txt );
    }

    if ( ! empty( $icon_url ) && !empty($copytxt) ) {
        $ico = $dom->createElement( 'img' );
        $icondisp = false;

        if(!empty($copytxt)) {
            $ico->setAttribute( 'src', esc_url( $icon_url ) );
            $ico->setAttribute( 'alt', esc_attr( $icon_alt ?: 'Copyright' ) );
            $ico->setAttribute( 'class', 'dn-caption-img dnc-has-tooltip' );
            $ico->setAttribute( 'title', $copytxt );
            $ico->setAttribute( 'data-dnc-tooltip', $copytxt );
            $ico->setAttribute( 'data-dnc-placement', 'top' );
            $ico->setAttribute( 'data-dnc-trigger', 'mouseenter focus click touch' );
            $ico->setAttribute( 'data-dnc-theme', 'dark' );
            $ico->setAttribute( 'data-dnc-maxwidth', '260' );
            $ico->setAttribute( 'data-dnc-delay', '50' );
            $icondisp = true;
        }
        if(trim($span->textContent) !== '' || $span->hasChildNodes()) $figcaption->appendChild( $span );
        if($icondisp) $figcaption->appendChild( $ico );
        
    }
    if($figcaption->hasChildNodes()){
        $figcaption->setAttribute( 'class', 'dn-copyright dn-copy3 dn-align-' . sanitize_html_class( $align ) );
        return $figcaption;
    }
    return '';
}

function dn_extract_gallery_ids_from_settings( array $settings ) : array {
    $ids = [];

    // Helper internes
    $push_ids = function($val) use (&$ids) {
        if (is_array($val)) {
            // tableau d'objets {id,url} ou d'ints
            foreach ($val as $item) {
                if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    $ids[] = (int) $item['id'];
                } elseif (is_numeric($item)) {
                    $ids[] = (int) $item;
                }
            }
        } elseif (is_string($val)) {
            // "1,2,3"
            foreach (preg_split('/\s*,\s*/', $val) as $maybe) {
                if (is_numeric($maybe)) $ids[] = (int) $maybe;
            }
        }
    };

    // 1) Cas les plus courants
    foreach (['gallery','images','ids'] as $key) {
        if (!empty($settings[$key])) $push_ids($settings[$key]);
        if (!empty($ids)) return array_values(array_unique($ids));
    }

    // 2) Carrousels/skins qui utilisent "slides" (Media Carousel & co)
    if (!empty($settings['slides']) && is_array($settings['slides'])) {
        foreach ($settings['slides'] as $slide) {
            // slide['image']['id'] (le plus fréquent)
            if (isset($slide['image']['id']) && is_numeric($slide['image']['id'])) {
                $ids[] = (int) $slide['image']['id'];
                continue;
            }
            // parfois slide['id']
            if (isset($slide['id']) && is_numeric($slide['id'])) {
                $ids[] = (int) $slide['id'];
                continue;
            }
            // ou slide['image'] directement numérique
            if (isset($slide['image']) && is_numeric($slide['image'])) {
                $ids[] = (int) $slide['image'];
                continue;
            }
        }
        if (!empty($ids)) return array_values(array_unique($ids));
    }

    // 3) Autres variantes possibles (rare)
    foreach (['carousel','items','media'] as $key) {
        if (!empty($settings[$key])) $push_ids($settings[$key]);
        if (!empty($ids)) return array_values(array_unique($ids));
    }

    // 4) Dernier filet : certains widgets exposent des sous-structures dans $settings['_elementor_controls']
    if (!empty($settings['_elementor_controls']) && is_array($settings['_elementor_controls'])) {
        foreach ($settings['_elementor_controls'] as $ctrl) {
            if (is_array($ctrl)) {
                foreach (['gallery','images','ids','slides'] as $key) {
                    if (!empty($ctrl[$key])) $push_ids($ctrl[$key]);
                }
            }
        }
        if (!empty($ids)) return array_values(array_unique($ids));
    }

    // 5) Rien trouvé → retourne vide
    return [];
}



function dn_galleryitem_best_url( DOMElement $a ) {
    $href = trim( (string) $a->getAttribute('href') );
    if ( $href ) return $href;
    foreach ( $a->childNodes as $child ) {
        if ( $child instanceof DOMElement && false !== strpos( ' ' . $child->getAttribute('class') . ' ', ' e-gallery-image ' ) ) {
            $thumb = trim( (string) $child->getAttribute('data-thumbnail') );
            if ( $thumb ) return $thumb;
            $bg = dn_extract_url_from_style( (string) $child->getAttribute('style') );
            if ( $bg ) return $bg;
        }
    }
    return '';
}

/** Cache légende par attachment_id */
function dn_get_attachment_copyright_cached( $attachment_id ) {
    static $cache = [];
    $id = (int) $attachment_id;
    if ( isset( $cache[ $id ] ) ) return $cache[ $id ];
    $cap = (string) get_post_field( 'post_excerpt', $id );
    $cache[ $id ] = $cap;
    return $cap;
}

/** Cache ALT par attachment_id */
function dn_get_attachment_alt_cached( $attachment_id ) {
    static $altcache = [];
    $id = (int) $attachment_id;
    if ( isset( $altcache[ $id ] ) ) return $altcache[ $id ];
    $alt = (string) get_post_meta($id,'_wp_attachment_image_alt',true );
    $altcache[ $id ] = $alt;
    return $alt;
}

/** Cache légende par attachment_id */
function dn_get_attachment_title_cached( $attachment_id ) {
    static $titlecache = [];
    $id = (int) $attachment_id;
    if ( isset( $titlecache[ $id ] ) ) return $titlecache[ $id ];
    $title = (string) get_the_title($id);
    $titlecache[ $id ] = $title;
    return $title;
}

/** Cache id par URL (en plus de la normalisation) */
function dn_attachment_id_from_maybe_cdn_url( $url ) {
    static $url2id = [];
    if ( ! $url ) return 0;

    $raw = (string) $url;
    if ( isset( $url2id[ $raw ] ) ) return $url2id[ $raw ];

    $clean = explode( '?', $raw )[0];
    $clean = str_replace( ['%20','%5B','%5D','%28','%29'], [' ','[',']','(',')'], $clean );

    $id = attachment_url_to_postid( $clean );
    if ( ! $id && preg_match( '/-\d+x\d+(\.\w{3,4})$/', $clean ) ) {
        $original = preg_replace( '/-\d+x\d+(\.\w{3,4})$/', '$1', $clean );
        $id = attachment_url_to_postid( $original );
    }
    $url2id[ $raw ] = (int) $id;
    return (int) $id;
}

/** Ancêtre par tag */
function dn_find_ancestor( DOMNode $node, $tag ) {
    $tag = strtolower( $tag );
    for ( $p = $node->parentNode; $p instanceof DOMElement; $p = $p->parentNode ) {
        if ( strtolower( $p->tagName ) === $tag ) return $p;
    }
    return null;
}

/** URL from background-image */
function dn_extract_url_from_style( $style ) {
    if ( ! $style ) return '';
    if ( preg_match( '/background-image\s*:\s*url\((["\']?)(.*?)\1\)/i', $style, $m ) ) {
        return trim( (string) $m[2] );
    }
    return '';
}

/** 1er non vide */
function dn_first_non_empty( array $values ) {
    foreach ( $values as $v ) {
        $v = trim( (string) $v );
        if ( '' !== $v ) return $v;
    }
    return '';
}

/** src/srcset 1er <img> d’un fragment */
function dn_extract_img_src_from_html( $html ) {
    if ( ! $html ) return '';
    if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m ) ) return $m[1];
    if ( preg_match( '/<img[^>]+srcset=["\']([^"\']+)["\']/i', $html, $m ) ) {
        $parts = preg_split( '/\s*,\s*/', $m[1] );
        if ( ! empty( $parts[0] ) ) return trim( preg_split( '/\s+/', $parts[0] )[0] );
    }
    return '';
}

/**
 * =====================================================================
 *  CSS minimal (inline)
 * =====================================================================
 */
add_action( 'wp_enqueue_scripts', function() {
    $css = "
.dn-figure{display:inline-block;margin:0}
.dn-figure .dn-copyright,
.elementor-image-carousel .dn-copyright{font-size:12px;font-family: 'Noto Sans';padding:5px;font-size: 12px;font-style: normal;font-weight: 500;text-align:right;line-height: 12px;display: flex
;    align-items: center;}
.dn-figure>.dn-copyright:first-child{margin:0 0 .35em}
.dn-figure>.dn-copyright span+img{margin-left:3px;}
.dn-figure .dn-copyright.dn-align-left{text-align:left}
.dn-figure .dn-copyright.dn-align-center{text-align:center}
.dn-figure .dn-copyright span{}
.dn-figure .dn-copyright.dn-align-right{text-align:right}
.dn-figure .dn-caption-img,
.elementor-image-carousel .dn-caption-img{display:inline-block;vertical-align:middle;width:10px;height:10px !important;}
.elementor-image-carousel .swiper-slide-inner { display: block; }



@media(max-width:768px){
    .dn-figure>.dn-copyright span+img{margin:0}
    .dn-figure .dn-copyright{overflow:hidden;}
    .dn-figure .dn-copyright span{text-indent:-1000px;opacity:0; transition: linear all 0.65s}
    .dn-figure .dn-copyright:hover span{opacity:1; text-indent:0;margin-right:3px}
}

";
    wp_register_style( 'dn-copyright-style', false );
    wp_enqueue_style( 'dn-copyright-style' );
    wp_add_inline_style( 'dn-copyright-style', $css );
}, 20);
