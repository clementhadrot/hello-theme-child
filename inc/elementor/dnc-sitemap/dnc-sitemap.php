<?php
/**
 * Plugin Name: DN Simple Sitemap — Elementor Only (RGAA + Perf) [Flex]
 * Description: Widget Elementor unique pour afficher Pages (hiérarchie), Articles (par types) ou Taxonomies, avec accordéon, colonnes responsives (Flex), CSS chargé uniquement si le widget est présent, cache et "Load more" pour gros volumes.
 * Author: DN Consultants
 * Version: 2.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enregistre les assets (CSS/JS) sans les charger globalement.
 * Ils seront chargés uniquement si le widget est utilisé via get_style_depends/get_script_depends.
 */
add_action( 'init', function() {
    $base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-sitemap/';
	$css_path = $base . 'assets/css/dnc-sitemap.css';
    wp_register_style( 'dn-sitemap-widget', $css_path, [], '2.1.0' );

	// JS pour Load More (AJAX) — ajoute dans la colonne la plus courte
	$js = '(function(){
	function onClick(e){
		var b=e.currentTarget;
		var c=b.closest("[data-dn-sitemap]");
		if(!c) return;
		e.preventDefault();
		if(b.getAttribute("aria-busy")=="true") return;
		b.setAttribute("aria-busy","true");

		var payload={
			action:"dn_sitemap_load",
			nonce:c.dataset.nonce,
			mode:c.dataset.mode,
			query:JSON.parse(c.dataset.query||"{}"),
			offset:parseInt(c.dataset.offset||"0",10)||0,
			paged:parseInt(c.dataset.paged||"1",10)||1,
			per_page:parseInt(c.dataset.perPage||"20",10)||20
		};

		var xhr=new XMLHttpRequest();
		xhr.open("POST",(window.ajaxurl||document.body.getAttribute("data-ajaxurl")||"/wp-admin/admin-ajax.php"));
		xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded; charset=UTF-8");
		xhr.onload=function(){
			b.setAttribute("aria-busy","false");
			if(xhr.status>=200&&xhr.status<300){
				try{
					var res=JSON.parse(xhr.responseText);
					if(res.success){
						var cols=c.querySelectorAll(".dn-col");
						if(cols.length){
							// Colonne la plus courte (par nb d’enfants)
							var target=[].reduce.call(cols,function(minEl,el){
								return (el.children.length<minEl.children.length)?el:minEl;
							}, cols[0]);
							var tmp=document.createElement("div");
							tmp.innerHTML="<ul>"+res.data.html+"</ul>";
							tmp.querySelectorAll("li").forEach(function(li){ target.appendChild(li); });
						}else{
							// Fallback: premier <ul>
							var ul=c.querySelector("ul");
							if(ul) ul.insertAdjacentHTML("beforeend", res.data.html);
						}
						c.dataset.offset=res.data.next_offset;
						c.dataset.paged=res.data.next_paged;
						if(!res.data.has_more){ b.remove(); }
					}
				}catch(err){ console.error(err); }
			}
		};
		xhr.send("action="+encodeURIComponent(payload.action)
			+"&nonce="+encodeURIComponent(payload.nonce)
			+"&mode="+encodeURIComponent(payload.mode)
			+"&offset="+payload.offset
			+"&paged="+payload.paged
			+"&per_page="+payload.per_page
			+"&query="+encodeURIComponent(JSON.stringify(payload.query)));
	}
	document.addEventListener("click",function(e){
		var t=e.target;
		if(t && t.matches(".dn-load-more")) onClick(e);
	});
	})();';
	wp_register_script( 'dn-sitemap-widget', false, [], '2.1.0', true );
	wp_add_inline_script( 'dn-sitemap-widget', $js );
} );

/**
 * AJAX Load More — renvoie des <li> pour Posts/Taxonomies
 */
add_action( 'wp_ajax_dn_sitemap_load', 'dn_sitemap_ajax_load' );
add_action( 'wp_ajax_nopriv_dn_sitemap_load', 'dn_sitemap_ajax_load' );
function dn_sitemap_ajax_load() {
	check_ajax_referer( 'dn-sitemap', 'nonce' );
	$mode     = sanitize_text_field( $_POST['mode'] ?? '' );
	$per_page = max( 1, intval( $_POST['per_page'] ?? 20 ) );
	$offset   = max( 0, intval( $_POST['offset'] ?? 0 ) );
	$paged    = max( 1, intval( $_POST['paged'] ?? 1 ) );
	$query    = json_decode( wp_unslash( $_POST['query'] ?? '{}' ), true );
	if ( ! is_array( $query ) ) $query = [];

	$html = '';
	$has_more = false;

	if ( 'posts' === $mode ) {
		$args = wp_parse_args( $query, [
			'post_type'              => [ 'post' ],
			'post_status'            => 'publish',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'posts_per_page'         => $per_page,
			'offset'                 => $offset,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );
		$q = new WP_Query( $args );
		while ( $q->have_posts() ) { $q->the_post();
			$html .= '<li><a href="' . esc_url( get_permalink() ) . '" aria-label="' . esc_attr( sprintf( __( 'Lire : %s', 'dn' ), get_the_title() ) ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		$has_more = ( $q->found_posts > $offset + $q->post_count );
		wp_reset_postdata();

	} elseif ( 'tax_terms' === $mode ) {
		$taxonomies = array_filter( (array) ( $query['taxonomies'] ?? [ 'category' ] ) );
		$hide_empty = ! empty( $query['hide_empty'] );
		$terms = get_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => $hide_empty, 'number' => $per_page, 'offset' => $offset ] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$link = get_term_link( $t ); if ( is_wp_error( $link ) ) continue;
				$html .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $t->name ) . '</a></li>';
			}
			$counts = wp_count_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => $hide_empty ] );
			$counts = is_wp_error( $counts ) ? 0 : intval( $counts );
			$has_more = ( $counts > $offset + count( (array) $terms ) );
		}
	}

	wp_send_json_success( [
		'html'       => $html,
		'next_offset'=> $offset + $per_page,
		'next_paged' => $paged + 1,
		'has_more'   => $has_more,
	] );
}

/**
 * Widget Elementor — DN – Sitemap
 */
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
    class DN_Content_List_Widget extends \Elementor\Widget_Base {
        public function get_name() { return 'dn_content_list'; }
        public function get_title() { return 'DN – Sitemap'; }
        public function get_icon() { return 'eicon-post-list'; }
        public function get_categories() { return [ 'general' ]; }
        public function get_style_depends() { return [ 'dn-sitemap-widget' ]; }
        public function get_script_depends() { return [ 'dn-sitemap-widget' ]; }

        public function register_controls() {
            $public_post_types = array_diff( get_post_types( [ 'public' => true ], 'names' ), [ 'attachment' ] );
            $public_taxes      = get_taxonomies( [ 'public' => true ], 'names' );

            $this->start_controls_section( 'section_content', [ 'label' => 'Contenu' ] );
            $this->add_control( 'mode', [
                'label'   => 'Mode',
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'pages'     => 'Pages (hiérarchie)',
                    'posts'     => 'Articles (par types)',
                    'tax_terms' => 'Termes de taxonomies',
                ],
                'default' => 'pages',
            ] );

            // Accordéon
            $this->add_control( 'accordion', [
                'label' => 'Activer l\'accordéon',
                'type'  => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Oui',
                'label_off'=> 'Non',
                'return_value' => 'yes',
                'default' => '',
            ] );
            $this->add_control( 'open', [
                'label' => 'Ouverture par défaut',
                'type'  => \Elementor\Controls_Manager::SELECT,
                'options' => [ 'none' => 'Aucune', 'all' => 'Tout ouvert' ],
                'default' => 'none',
                'condition' => [ 'accordion' => 'yes' ],
            ] );
            $this->add_control( 'summary_label', [
                'label' => 'Libellé du résumé',
                'type'  => \Elementor\Controls_Manager::TEXT,
                'default' => 'Contenus',
                'condition' => [ 'accordion' => 'yes' ],
            ] );

            // Articles
            $this->add_control( 'post_types', [
                'label'   => 'Types d\'articles',
                'type'    => \Elementor\Controls_Manager::SELECT2,
                'options' => array_combine( $public_post_types, $public_post_types ),
                'multiple'=> true,
                'condition' => [ 'mode' => 'posts' ],
            ] );
            $this->add_control( 'posts_per_page', [ 'label' => 'Nombre d\'éléments (par lot)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 50, 'condition' => [ 'mode' => 'posts' ] ] );
            $this->add_control( 'orderby', [ 'label' => 'Trier par', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => [ 'date'=>'Date','title'=>'Titre','menu_order'=>'Ordre (si supporté)' ], 'default' => 'date', 'condition' => [ 'mode' => 'posts' ] ] );
            $this->add_control( 'order', [ 'label' => 'Ordre', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => [ 'DESC' => 'DESC', 'ASC' => 'ASC' ], 'default' => 'DESC', 'condition' => [ 'mode' => 'posts' ] ] );

            // Taxonomies
            $this->add_control( 'taxonomies', [ 'label' => 'Taxonomies', 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => array_combine( $public_taxes, $public_taxes ), 'multiple'=> true, 'condition' => [ 'mode' => 'tax_terms' ] ] );
            $this->add_control( 'hide_empty', [ 'label' => 'Masquer les termes vides', 'type' => \Elementor\Controls_Manager::SWITCHER, 'condition' => [ 'mode' => 'tax_terms' ], 'label_on'  => 'Oui', 'label_off' => 'Non', 'return_value' => 'yes', 'default' => 'yes' ] );

            // Colonnes communes
            $this->add_control( 'columns', [ 'label' => 'Colonnes (≥1024px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 1 ] );
            $this->add_control( 'columns_md', [ 'label' => 'Colonnes (≥768px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'step' => 1, 'default' => '' ] );
            $this->add_control( 'columns_sm', [ 'label' => 'Colonnes (≥480px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'step' => 1, 'default' => '' ] );
            $this->add_control( 'col_gap', [ 'label' => 'Espacement entre colonnes', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '1rem' ] );

            // Perf
            $this->add_control( 'enable_load_more', [ 'label' => 'Activer "Load more" (AJAX)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'label_on' => 'Oui', 'label_off' => 'Non', 'return_value' => 'yes', 'default' => 'yes', 'condition' => [ 'mode!' => 'pages' ] ] );
            $this->add_control( 'cache_ttl', [ 'label' => 'Cache (minutes)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 60 ] );

            $this->end_controls_section();
        }

        private function dn_cache_key( $settings ) {
            $base = [
                'mode' => $settings['mode'] ?? 'pages',
                'post_types' => $settings['post_types'] ?? [],
                'orderby' => $settings['orderby'] ?? 'date',
                'order' => $settings['order'] ?? 'DESC',
                'taxonomies' => $settings['taxonomies'] ?? [],
                'hide_empty' => $settings['hide_empty'] ?? '',
                'columns' => $settings['columns'] ?? 1,
                'columns_md' => $settings['columns_md'] ?? '',
                'columns_sm' => $settings['columns_sm'] ?? '',
                'col_gap' => $settings['col_gap'] ?? '1rem',
                'accordion' => $settings['accordion'] ?? '',
                'open' => $settings['open'] ?? 'none',
            ];
            $lastmod = get_lastpostmodified( 'gmt' );
            return 'dn_sitemap_' . md5( wp_json_encode( $base ) . '|' . $lastmod );
        }

        /** Répartit un tableau de <li> HTML en N colonnes (équilibrage simple). */
        private function chunk_items_into_columns( array $items, int $cols ): array {
            $cols = max(1, $cols);
            $out = array_fill(0, $cols, []);
            $i = 0;
            foreach ( $items as $li_html ) {
                $out[$i % $cols][] = $li_html;
                $i++;
            }
            return $out;
        }

        public function render() {
            $s = $this->get_settings_for_display();
            $mode      = $s['mode'] ?? 'pages';
            $accordion = ( $s['accordion'] ?? '' ) === 'yes';
            $open_all  = ( $s['open'] ?? 'none' ) === 'all';
            $cols_lg   = max( 1, intval( $s['columns'] ?? 1 ) );
            $cols_md   = intval( $s['columns_md'] ?? 0 );
            $cols_sm   = intval( $s['columns_sm'] ?? 0 );
            $gap       = esc_attr( $s['col_gap'] ?? '1rem' );
            $style  = '--dn-cols-lg:'.$cols_lg.';--dn-gap:'.$gap.';';
            $style .= $cols_md ? '--dn-cols-md:'.$cols_md.';' : '';
            $style .= $cols_sm ? '--dn-cols-sm:'.$cols_sm.';' : '';

            $cache_ttl = max( 0, intval( $s['cache_ttl'] ?? 60 ) ) * MINUTE_IN_SECONDS;
            $key = $this->dn_cache_key( $s );
            $cached = $cache_ttl ? get_transient( $key ) : false;
            if ( $cached ) { echo $cached; return; }

            ob_start();

            if ( 'pages' === $mode ) {
                // NOTE: on laisse un seul <ul> pour préserver la hiérarchie.
                $ul = wp_list_pages( [ 'sort_column' => 'menu_order,post_title', 'depth' => 0, 'title_li' => '', 'echo' => false ] );
                if ( ! $ul ) { echo '<div class="dn-cl-wrap">' . esc_html__( 'Aucune page', 'dn' ) . '</div>'; }
                $title = 'Pages';
                if ( $accordion ) {
                    echo '<details' . ( $open_all ? ' open' : '' ) . ' class="dn-el-acc">';
                    echo '<summary><span aria-hidden="true">▸</span> ' . esc_html( $title ) . '</summary>';
                    echo '<div class="dn-cols" style="' . esc_attr( $style ) . '">';
                    echo '<ul class="dn-cl-list dn-sitemap-pages-list" role="list">' . $ul . '</ul>';
                    echo '</div></details>';
                } else {
                    echo '<div class="dn-cols" style="' . esc_attr( $style ) . '"><ul class="dn-cl-list dn-sitemap-pages-list" role="list">' . $ul . '</ul></div>';
                }

            } elseif ( 'posts' === $mode ) {
                $post_types = array_filter( (array) ( $s['post_types'] ?? [] ) );
                if ( empty( $post_types ) ) { $post_types = [ 'post' ]; }
                $per_page = max( 1, intval( $s['posts_per_page'] ?? 50 ) );

                $args = [
                    'post_type'              => $post_types,
                    'post_status'            => 'publish',
                    'posts_per_page'         => $per_page,
                    'orderby'                => sanitize_key( $s['orderby'] ?? 'date' ),
                    'order'                  => in_array( $s['order'] ?? 'DESC', [ 'ASC', 'DESC' ], true ) ? $s['order'] : 'DESC',
                    'no_found_rows'          => ( ( $s['enable_load_more'] ?? '' ) !== 'yes' ),
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ];
                $q = new \WP_Query( $args );

                $label = ! empty( $s['summary_label'] ) ? $s['summary_label'] : 'Contenus';
                $nonce = wp_create_nonce( 'dn-sitemap' );
                $dataset = ' data-dn-sitemap data-nonce="'. esc_attr( $nonce ) .'" data-mode="posts" data-query=' . "'" . esc_attr( wp_json_encode( $args ) ) . "'" . ' data-per-page="'. intval( $per_page ) .'" data-offset="'. intval( $q->post_count ) .'" data-paged="1"';

                // Construire les <li>
                $items = [];
                while ( $q->have_posts() ) { $q->the_post();
                    $items[] = sprintf(
                        '<li><a href="%s" aria-label="%s">%s</a></li>',
                        esc_url( get_permalink() ),
                        esc_attr( sprintf( __( 'Lire : %s', 'dn' ), get_the_title() ) ),
                        esc_html( get_the_title() )
                    );
                }
                $found = intval( $q->found_posts );
                $count = intval( $q->post_count );
                // Répartition en colonnes flex
                $columns_arrays = $this->chunk_items_into_columns( $items, $cols_lg );

                if ( $accordion ) { echo '<details' . ( $open_all ? ' open' : '' ) . ' class="dn-el-acc">'; echo '<summary><span aria-hidden="true">▸</span> ' . esc_html( $label ) . '</summary>'; }
                echo '<div class="dn-cols" style="' . esc_attr( $style ) . '"><div class="dn-cl-wrap"'. $dataset . '>';
                foreach ( $columns_arrays as $col_items ) {
                    echo '<ul class="dn-cl-list dn-col" role="list">' . implode('', $col_items ) . '</ul>';
                }
                if ( ( $s['enable_load_more'] ?? '' ) === 'yes' && ( $found > $count ) ) {
                    echo '<button type="button" class="dn-load-more" aria-busy="false">Charger plus</button>';
                }
                echo '</div></div>';
                if ( $accordion ) { echo '</details>'; }
                wp_reset_postdata();

            } else { // tax_terms
                $taxonomies = array_filter( (array) ( $s['taxonomies'] ?? [] ) );
                if ( empty( $taxonomies ) ) { $taxonomies = [ 'category' ]; }
                $hide_empty = ( $s['hide_empty'] ?? '' ) === 'yes';
                $per_page = max( 1, intval( $s['posts_per_page'] ?? 50 ) );

                $terms = get_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => $hide_empty, 'number' => $per_page, 'offset' => 0 ] );
                $nonce = wp_create_nonce( 'dn-sitemap' );
                $query = [ 'taxonomies' => $taxonomies, 'hide_empty' => $hide_empty ];
                $dataset = ' data-dn-sitemap data-nonce="'. esc_attr( $nonce ) .'" data-mode="tax_terms" data-query=' . "'" . esc_attr( wp_json_encode( $query ) ) . "'" . ' data-per-page="'. intval( $per_page ) .'" data-offset="'. intval( count( (array) $terms ) ) .'" data-paged="1"';

                $items = [];
                if ( ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $t ) {
                        $link = get_term_link( $t ); if ( is_wp_error( $link ) ) continue;
                        $items[] = '<li><a href="' . esc_url( $link ) . '">' . esc_html( $t->name ) . '</a></li>';
                    }
                }
                $columns_arrays = $this->chunk_items_into_columns( $items, $cols_lg );

                $counts = wp_count_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => $hide_empty ] );
                $counts = is_wp_error( $counts ) ? 0 : intval( $counts );

                $title = 'Taxonomies';
                if ( $accordion ) { echo '<details' . ( $open_all ? ' open' : '' ) . ' class="dn-el-acc">'; echo '<summary><span aria-hidden="true">▸</span> ' . esc_html( $title ) . '</summary>'; }
                echo '<div class="dn-cols" style="' . esc_attr( $style ) . '"><div class="dn-cl-wrap"'. $dataset . '>';
                foreach ( $columns_arrays as $col_items ) {
                    echo '<ul class="dn-cl-list dn-col" role="list">' . implode('', $col_items ) . '</ul>';
                }
                if ( ( $s['enable_load_more'] ?? '' ) === 'yes' && ( $counts > count( (array) $terms ) ) ) {
                    echo '<button type="button" class="dn-load-more" aria-busy="false">Charger plus</button>';
                }
                echo '</div></div>';
                if ( $accordion ) { echo '</details>'; }
            }

            $out = ob_get_clean();
            if ( $cache_ttl ) { set_transient( $key, $out, $cache_ttl ); }
            echo $out;
        }
    }

    $widgets_manager->register( new DN_Content_List_Widget() );
} );
