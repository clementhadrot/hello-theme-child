<?php
/**
 * Plugin Name: DNC – Filtres rapides AJAX (taxonomies) pour Elementor Loop/Grid/Carousel
 * Description: Filtres “chips” AJAX par taxonomies (term_id), multi-groupes, compteurs statiques optionnels, reset global/taxo, et accessibilité renforcée (RGAA).
 * Version: 1.2.0
 * Author: DN Consultants
 * Text Domain: dnc-quick-tax-filters
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class DNC_QTF_Plugin {
	const TD      = 'dnc-quick-tax-filters';
	const VERSION = '1.2.0';

	public function __construct() {
		// Widget Elementor (UI des filtres)
		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );

		// Assets front
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Ajoute un champ “Groupe de filtres (DNC)” sur Posts / Loop Grid / Loop Carousel
		add_action( 'elementor/element/posts/section_query/after_section_end',         [ $this, 'add_group_control' ], 10, 2 );
		add_action( 'elementor/element/loop-grid/section_query/after_section_end',     [ $this, 'add_group_control' ], 10, 2 );
		add_action( 'elementor/element/loop-carousel/section_query/after_section_end', [ $this, 'add_group_control' ], 10, 2 );

		// Marqueur data + id sur le wrapper des boucles, pour couplage JS + ARIA
		add_action( 'elementor/frontend/widget/before_render', [ $this, 'inject_group_attr_on_loop' ] );

		// Attache dynamiquement nos hooks de requête pour CHAQUE groupe détecté dans l’URL
		add_action( 'init', [ $this, 'attach_dynamic_query_hooks' ], 1 );
	}

	/* ---------------------------------------------------------------------
	 * Bootstrap
	 * ------------------------------------------------------------------ */

	public function register_widget( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) return;
		require_once __DIR__ . '/widget-dnc-quick-filter.php';
		$widgets_manager->register( new \DNC_QTF_Elementor_Widget() );
	}

	public function enqueue_assets() {
		$base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-loop-quick-filter';

		wp_enqueue_style(
			'dnc-qtf-styles',
			$base . '/assets/dnc-quick-filter.css',
			[],
			self::VERSION
		);

		wp_enqueue_script(
			'dnc-qtf-js',
			$base . '/assets/dnc-quick-filter.js',
			[],
			self::VERSION,
			true
		);
	}

	/* ---------------------------------------------------------------------
	 * Réglage “Groupe de filtres (DNC)” dans les widgets de boucle
	 * ------------------------------------------------------------------ */

	public function add_group_control( $element, $args ) {
		/** @var \Elementor\Widget_Base $element */
		$element->start_controls_section( 'dnc_qtf_section', [
			'label' => __( 'Filtres DNC', self::TD ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$element->add_control( 'dnc_filter_group_id', [
			'label'       => __( 'Groupe de filtres (DNC)', self::TD ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'ex: quick_filters', self::TD ),
			'description' => __( 'Identifiant reliant cette boucle aux filtres DNC. Permet plusieurs groupes par page.', self::TD ),
		] );

		$element->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Couplage front : data-dnc-group + id contrôlable
	 * ------------------------------------------------------------------ */

	public function inject_group_attr_on_loop( $widget ) {
		$name = $widget->get_name();
		if ( ! in_array( $name, [ 'posts', 'loop-grid', 'loop-carousel' ], true ) ) return;

		$settings = $widget->get_settings_for_display();
		$group    = isset( $settings['dnc_filter_group_id'] ) ? sanitize_key( $settings['dnc_filter_group_id'] ) : '';
		if ( ! $group ) return;

		// data-dnc-group pour JS / multi-instance
		$widget->add_render_attribute( '_wrapper', 'data-dnc-group', esc_attr( $group ) );

		// id ARIA target (si absent) => dnc-loop-{group}
		$existing_id = $widget->get_render_attribute_string( '_wrapper' );
		$loop_id     = 'dnc-loop-' . $group;
		$widget->add_render_attribute( '_wrapper', 'id', esc_attr( $loop_id ) );

		// Aligne query_id natif sur notre group (pour “elementor/query/{group}”)
		if ( method_exists( $widget, 'set_settings' ) ) {
			$widget->set_settings( 'query_id', $group );
			$this->dnc_log( '[DNC] inject_group_attr_on_loop: set query_id=' . $group . ' for ' . $name );
		}
	}

	/* ---------------------------------------------------------------------
	 * Hook de requête sur-mesure par groupe: elementor/query/{group}
	 * ------------------------------------------------------------------ */

	public function attach_dynamic_query_hooks() {
		$groups = [];

		// Détecte {group}__{taxonomy} dans l’URL
		foreach ( $_GET as $k => $v ) {
			if ( preg_match( '/^([a-z0-9_-]+)__[a-z0-9_-]+$/i', $k, $m ) ) {
				$groups[ $m[1] ] = true;
			}
		}

		// Force ton group par défaut (utile quand la page charge "sans filtre")
		$groups['quick_filters'] = true;

		foreach ( array_keys( $groups ) as $group ) {
			add_action( "elementor/query/{$group}", function( $query ) use ( $group ) {
				$this->dnc_log( "[DNC] elementor/query/{$group}: fired. GET=" . json_encode( $_GET ) );

				$tax_query = $this->dnc_build_tax_query_from_group( $group );

				$this->dnc_log( "[DNC] elementor/query/{$group}: built tax_query=" . json_encode( $tax_query ) );

				if ( $tax_query ) {
					$existing = $query->get( 'tax_query' );

					if ( is_array( $existing ) && ! empty( $existing ) ) {
						$relation = isset( $existing['relation'] ) ? $existing['relation'] : 'AND';
						$merged   = array_merge( [ 'relation' => $relation ], $existing, $tax_query );
						$query->set( 'tax_query', $merged );
					} else {
						$query->set( 'tax_query', $tax_query );
					}

					$this->dnc_log( "[DNC] elementor/query/{$group}: applied tax_query=" . json_encode( $query->get( 'tax_query' ) ) );
				} else {
					$this->dnc_log( "[DNC] elementor/query/{$group}: no tax filters in GET" );
				}
			}, 10, 1 );
		}
	}

	private function dnc_build_tax_query_from_group( $group ) {
		$out       = [];
		$tax_names = get_taxonomies( [ 'public' => true ], 'names' );

		foreach ( $tax_names as $tax ) {
			$key = $group . '__' . $tax;
			if ( isset( $_GET[ $key ] ) ) {
				$ids = array_filter( array_map( 'intval', (array) $_GET[ $key ] ) );
				if ( $ids ) {
					$out[] = [
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $ids,
						'operator' => 'IN',
					];
				}
			}
		}
		if ( count( $out ) > 1 ) {
			$out = array_merge( [ 'relation' => 'AND' ], $out );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Logging conditionnel
	 * ------------------------------------------------------------------ */

	private function dnc_log( $msg ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $msg );
		}
	}
}

new DNC_QTF_Plugin();
