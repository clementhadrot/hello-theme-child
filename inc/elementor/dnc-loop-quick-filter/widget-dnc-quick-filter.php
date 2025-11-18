<?php
/**
 * Elementor Widget: DNC – Filtres rapides de taxonomies (AJAX)
 * - Chips par taxonomie, cumul inter-taxonomies (AND)
 * - Reset global + reset par taxonomie, URLs partageables
 * - Compteur global (mode UI-only conseillé) et compteurs par terme
 * - Compteurs par terme : statiques (fixes) ou contextuels (varient)
 * - Accessibilité RGAA: roles, aria-label, aria-pressed, aria-controls, live regions, navigation clavier
 * Compatibilité PHP 7.4 → 8.x
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class DNC_QTF_Elementor_Widget extends Widget_Base {

	/* --------- Cache --------- */
	private $cache_enabled = true;   // activé par défaut
	private $cache_ttl     = 120;    // filtrable via dnc_qtf_term_count_ttl
	private $cache_salt    = 'dnc_qtf_v2';

	/* --------- Meta --------- */
	public function get_name()       { return 'dnc_qtf_widget'; }
	public function get_title()      { return __( 'Filtres rapides de taxonomies (AJAX)', 'dnc-quick-tax-filters' ); }
	public function get_icon()       { return 'eicon-filter'; }
	public function get_categories() { return [ 'general' ]; }

	/* --------- Controls UI --------- */
	protected function register_controls() {

		$this->start_controls_section( 'section_settings', [
			'label' => __( 'Réglages', 'dnc-quick-tax-filters' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'dnc_filter_group_id', [
			'label'       => __( 'Groupe de filtres (DNC)', 'dnc-quick-tax-filters' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'ex: quick_filters', 'dnc-quick-tax-filters' ),
			'description' => __( 'Doit être identique à celui défini sur la Loop Grid/Carousel à contrôler.', 'dnc-quick-tax-filters' ),
		] );

		// Reset global
		$this->add_control( 'show_global_reset', [
			'label'        => __( 'Afficher « Réinitialiser tout »', 'dnc-quick-tax-filters' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Oui', 'dnc-quick-tax-filters' ),
			'label_off'    => __( 'Non', 'dnc-quick-tax-filters' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'global_reset_label', [
			'label'       => __( 'Libellé du bouton global', 'dnc-quick-tax-filters' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Réinitialiser tout', 'dnc-quick-tax-filters' ),
			'condition'   => [ 'show_global_reset' => 'yes' ],
		] );

		// “Tout” par taxonomie
		$this->add_control( 'show_clear', [
			'label'        => __( 'Afficher « Tout » par taxonomie', 'dnc-quick-tax-filters' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Oui', 'dnc-quick-tax-filters' ),
			'label_off'    => __( 'Non', 'dnc-quick-tax-filters' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'clear_label', [
			'label'     => __( 'Libellé « Tout »', 'dnc-quick-tax-filters' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Tout', 'dnc-quick-tax-filters' ),
			'condition' => [ 'show_clear' => 'yes' ],
		] );

		// Taxonomies publiques
		$tax_objects = get_taxonomies( [ 'public' => true ], 'objects' );
		$tax_options = [];
		foreach ( $tax_objects as $tax ) {
			$tax_options[ $tax->name ] = $tax->label . ' (' . $tax->name . ')';
		}

		$rep = new Repeater();
		$rep->add_control( 'taxonomy', [
			'label'   => __( 'Taxonomie', 'dnc-quick-tax-filters' ),
			'type'    => Controls_Manager::SELECT,
			'options' => $tax_options,
		] );
		$rep->add_control( 'label', [
			'label'   => __( 'Titre affiché avant la taxonomie', 'dnc-quick-tax-filters' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Filtrer par', 'dnc-quick-tax-filters' ),
		] );
		$rep->add_control( 'multi_terms', [
			'label'        => __( 'Autoriser multi-sélection', 'dnc-quick-tax-filters' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Oui', 'dnc-quick-tax-filters' ),
			'label_off'    => __( 'Non', 'dnc-quick-tax-filters' ),
			'return_value' => 'yes',
			'default'      => '',
		] );
		$rep->add_control( 'hide_empty', [
			'label'        => __( 'Masquer termes vides', 'dnc-quick-tax-filters' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Oui', 'dnc-quick-tax-filters' ),
			'label_off'    => __( 'Non', 'dnc-quick-tax-filters' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'tax_blocks', [
			'label'       => __( 'Groupes de taxonomies', 'dnc-quick-tax-filters' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => '{{{ label }}} — {{{ taxonomy }}}',
		] );

		// Compteur global (UI-only conseillé)
	 $this->add_control( 'show_results_count', [
			'label'        => __( 'Afficher le compteur global', 'dnc-quick-tax-filters' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Oui', 'dnc-quick-tax-filters' ),
			'label_off'    => __( 'Non', 'dnc-quick-tax-filters' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'results_count_label', [
			'label'       => __( 'Libellé du compteur', 'dnc-quick-tax-filters' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Résultats', 'dnc-quick-tax-filters' ),
			'condition'   => [ 'show_results_count' => 'yes' ],
		] );

		$this->add_control( 'results_count_position', [
			'label'     => __( 'Position du compteur', 'dnc-quick-tax-filters' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'above',
			'options'   => [
				'above' => __( 'Au-dessus des filtres', 'dnc-quick-tax-filters' ),
				'below' => __( 'Au-dessous des filtres', 'dnc-quick-tax-filters' ),
			],
			'condition' => [ 'show_results_count' => 'yes' ],
		] );

		$this->add_control( 'results_count_source', [
			'label'     => __( 'Source du compteur global', 'dnc-quick-tax-filters' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'selected_term',
			'options'   => [
				'none'          => __( 'Désactivé', 'dnc-quick-tax-filters' ),
				'wp_query'      => __( 'Calcul WP_Query (serveur)', 'dnc-quick-tax-filters' ),
				'selected_term' => __( 'Depuis la pastille active', 'dnc-quick-tax-filters' ),
			],
			'condition' => [ 'show_results_count' => 'yes' ],
		] );

		// Post types (pour WP_Query ou pour compteurs statiques)
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$pt_options = [];
		foreach ( $post_types as $pt ) {
			$pt_options[ $pt->name ] = $pt->labels->singular_name . ' (' . $pt->name . ')';
		}
		$this->add_control( 'results_count_post_type', [
			'label'       => __( 'Post type(s) à compter', 'dnc-quick-tax-filters' ),
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => $pt_options,
			'default'     => [],
			'description' => __( 'Laisse vide pour « tous liés à la taxo ».', 'dnc-quick-tax-filters' ),
		] );

		// Compteurs par terme
		$this->add_control( 'show_term_counts', [
			'label'        => __( 'Afficher les compteurs par terme', 'dnc-quick-tax-filters' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => __( 'Oui', 'dnc-quick-tax-filters' ),
			'label_off'    => __( 'Non', 'dnc-quick-tax-filters' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'term_counts_behavior', [
			'label'     => __( 'Comportement des compteurs par terme', 'dnc-quick-tax-filters' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'static',
			'options'   => [
				'static'     => __( 'Statique (ne varie pas)', 'dnc-quick-tax-filters' ),
				'contextual' => __( 'Contextuel (varie avec les filtres)', 'dnc-quick-tax-filters' ),
			],
			'condition' => [ 'show_term_counts' => 'yes' ],
		] );

		$this->add_control( 'term_count_format', [
			'label'       => __( 'Format du compteur par terme', 'dnc-quick-tax-filters' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => ' (%d)',
			'description' => __( 'Utilise %d comme placeholder du nombre.', 'dnc-quick-tax-filters' ),
			'condition'   => [ 'show_term_counts' => 'yes' ],
		] );

		$this->end_controls_section();
	}

	/* --------- Render --------- */
	protected function render() {
		$s     = $this->get_settings_for_display();
		$group = trim( (string) ( $s['dnc_filter_group_id'] ?? '' ) );
		if ( empty( $s['tax_blocks'] ) || ! $group ) return;

		$this->cache_enabled = true;
		$this->cache_ttl     = (int) apply_filters( 'dnc_qtf_term_count_ttl', $this->cache_ttl );

		$loop_id = 'dnc-loop-' . $group;

        // Header: reset global + compteur global (UI-only conseillé)
		echo '<div class="dnc-qtf__header" role="region" aria-label="' . esc_attr__( 'Actions de filtrage', 'dnc-quick-tax-filters' ) . '">';

		if ( ! empty( $s['show_global_reset'] ) && 'yes' === $s['show_global_reset'] ) {
			$reset_all_url   = $this->remove_group_params_from_url( $group );
			$reset_all_label = ! empty( $s['global_reset_label'] ) ? $s['global_reset_label'] : __( 'Réinitialiser tout', 'dnc-quick-tax-filters' );
			printf(
				'<a class="dnc-qtf__reset-all" href="%1$s" data-dnc-reset="all" data-group="%2$s" role="button" tabindex="0" aria-label="%3$s">%3$s</a>',
				esc_url( $reset_all_url ),
				esc_attr( $group ),
				esc_html( $reset_all_label )
			);
		}

		if ( ! empty( $s['show_results_count'] ) && 'yes' === $s['show_results_count'] ) {
			if ( ( $s['results_count_position'] ?? 'above' ) === 'above' ) {
				$this->render_results_count_ui_only( $s );
			}
		}
		echo '</div>'; // header
        
		echo '<div class="dnc-qtf" data-dnc-filters-group="' . esc_attr( $group ) . '"';
		echo ' role="group" aria-roledescription="filtres" aria-label="' . esc_attr__( 'Filtres rapides', 'dnc-quick-tax-filters' ) . '"';
		echo ' aria-controls="' . esc_attr( $loop_id ) . '">';

		

		$term_counts_behavior = $s['term_counts_behavior'] ?? 'static';

		// Blocs de taxonomies
		foreach ( $s['tax_blocks'] as $block ) {
			$tax = $block['taxonomy'] ?? '';
			if ( ! $tax || ! taxonomy_exists( $tax ) ) continue;

			$label       = $block['label'] ?? '';
			$hide_empty  = ! empty( $block['hide_empty'] );
			$allow_multi = ! empty( $block['multi_terms'] );

			$terms = get_terms( [
				'taxonomy'   => $tax,
				'hide_empty' => $hide_empty,
			] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) continue;

			$param_key = $group . '__' . $tax;
			$selected  = isset( $_GET[ $param_key ] ) ? (array) $_GET[ $param_key ] : [];
			$selected  = array_map( 'intval', $selected );

			$show_term_counts  = ! empty( $s['show_term_counts'] ) && 'yes' === $s['show_term_counts'];
			$term_count_format = ( isset( $s['term_count_format'] ) && '' !== $s['term_count_format'] ) ? $s['term_count_format'] : ' (%d)';
			$post_types_for_ct = isset( $s['results_count_post_type'] ) ? (array) $s['results_count_post_type'] : [];

			$static_counts = [];
			if ( $show_term_counts && 'static' === $term_counts_behavior ) {
				$static_counts = $this->dnc_get_static_term_counts( $tax, $post_types_for_ct );
			}

			echo '<div class="dnc-qtf__block" role="group" aria-roledescription="taxonomie">';
				if ( '' !== $label ) {
					echo '<div class="dnc-qtf__label">' . esc_html( $label ) . '</div>';
				}

				echo '<div class="dnc-qtf__terms" role="list">';
				// “Tout” (reset de la taxonomie courante)
				if ( ! empty( $s['show_clear'] ) && 'yes' === $s['show_clear'] ) {
					$url_clear_tax   = $this->remove_tax_params_from_url( $group, $tax );
					$is_active_clear = empty( $selected );
					$lab_clear       = $s['clear_label'] ?? __( 'Tout', 'dnc-quick-tax-filters' );

					printf(
						'<a class="dnc-qtf__chip %1$s" href="%2$s" data-dnc-chip data-action="clear-tax" data-tax="%3$s" data-multi="%4$s" data-group="%5$s" data-term="" aria-pressed="%6$s" role="button" tabindex="0" role="listitem" aria-label="%7$s">%7$s</a>',
						$is_active_clear ? 'dnc-qtf__chip--active' : '',
						esc_url( $url_clear_tax ),
						esc_attr( $tax ),
						$allow_multi ? '1' : '0',
						esc_attr( $group ),
						$is_active_clear ? 'true' : 'false',
						esc_html( $lab_clear )
					);
				}

				foreach ( $terms as $term ) {
					$term_id   = (int) $term->term_id;
					$is_active = in_array( $term_id, $selected, true );

					$target_url = $this->build_grouped_url_preserve_all(
						$group, $tax, $term_id, $selected, $allow_multi, $is_active
					);

					$term_suffix = '';
					if ( $show_term_counts ) {
						if ( 'static' === $term_counts_behavior ) {
							$count_for_term = isset( $static_counts[ $term_id ] ) ? (int) $static_counts[ $term_id ] : 0;
						} else {
							$count_for_term = $this->cached_count_for_term(
								$group, $tax, $term_id, $allow_multi, $selected, $post_types_for_ct
							);
						}
						$term_suffix = sprintf( $term_count_format, (int) $count_for_term );
					}

					printf(
						'<a class="dnc-qtf__chip %1$s" href="%2$s" data-dnc-chip data-tax="%3$s" data-multi="%4$s" data-group="%5$s" data-term="%6$d" aria-pressed="%7$s" role="button" tabindex="0" role="listitem" aria-label="%8$s">%8$s%9$s</a>',
						$is_active ? 'dnc-qtf__chip--active' : '',
						esc_url( $target_url ),
						esc_attr( $tax ),
						$allow_multi ? '1' : '0',
						esc_attr( $group ),
						$term_id,
						$is_active ? 'true' : 'false',
						esc_html( $term->name ),
						$show_term_counts ? '<span class="dnc-qtf__term-count" aria-hidden="true">' . esc_html( $term_suffix ) . '</span>' : ''
					);
				}
				echo '</div>'; // terms
			echo '</div>'; // block
		}

		// Compteur global en dessous
		if ( ! empty( $s['show_results_count'] ) && 'yes' === $s['show_results_count'] ) {
			if ( ( $s['results_count_position'] ?? 'above' ) === 'below' ) {
				$this->render_results_count_ui_only( $s );
			}
		}

		echo '</div>'; // .dnc-qtf
	}

	/* --------- Output helpers --------- */

	private function render_results_count_ui_only( $settings ) {
		$label = ! empty( $settings['results_count_label'] ) ? $settings['results_count_label'] : __( 'Résultats', 'dnc-quick-tax-filters' );
		$mode  = $settings['results_count_source'] ?? 'selected_term'; // UI-only

		if ( 'none' === $mode ) return;

		$data_mode = ( 'selected_term' === $mode ) ? 'selected-term' : 'wp-query';

		// aria-live pour annoncer la variation du nombre
		echo '<div class="dnc-qtf__count" data-mode="' . esc_attr( $data_mode ) . '" aria-live="polite" role="status">'
		   . esc_html( $label ) . ' : '
		   . '<span class="dnc-qtf__count-value">—</span>'
		   . '</div>';
	}

	/* --------- Cache helpers --------- */

	private function make_cache_key( $prefix, $data ) {
		$ver  = function_exists( 'dnc_qtf_get_cache_version' ) ? (int) dnc_qtf_get_cache_version() : 1;
		$hash = md5( $this->cache_salt . '|v' . $ver . '|' . maybe_serialize( $data ) );
		return sanitize_key( $prefix . '_' . $hash );
	}

	private function cached_results_count( $tax_query, $post_types ) {
		if ( ! $this->cache_enabled ) {
			return $this->dnc_get_results_count( $tax_query, $post_types );
		}
		$key = $this->make_cache_key( 'dnc_qtf_total', [
			'tq'   => $tax_query,
			'pt'   => array_values( (array) $post_types ),
			'lang' => get_locale(),
		] );
		$val = get_transient( $key );
		if ( false !== $val ) return (int) $val;
		$val = (int) $this->dnc_get_results_count( $tax_query, $post_types );
		set_transient( $key, $val, $this->cache_ttl );
		return $val;
	}

	private function cached_count_for_term( $group, $taxonomy, $term_id, $allow_multi, $selected, $post_types ) {
		if ( ! $this->cache_enabled ) {
			return $this->dnc_get_count_for_term( $group, $taxonomy, $term_id, $allow_multi, $selected, $post_types );
		}
		$final_tq = $this->compute_final_tax_query_for_term( $group, $taxonomy, (int) $term_id, (bool) $allow_multi, (array) $selected );
		$key = $this->make_cache_key( 'dnc_qtf_term', [
			'tax'  => (string) $taxonomy,
			'tid'  => (int) $term_id,
			'tq'   => $final_tq,
			'pt'   => array_values( (array) $post_types ),
			'lang' => get_locale(),
		] );
		$val = get_transient( $key );
		if ( false !== $val ) return (int) $val;
		$val = (int) $this->dnc_get_results_count( $final_tq, $post_types );
		set_transient( $key, $val, $this->cache_ttl );
		return $val;
	}

	/* --------- URL helpers (compat 7.4) --------- */

	private function starts_with( $haystack, $needle ) {
		if ( '' === $needle ) return true;
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

	private function collect_group_keys( $group ) {
		$keys = [];
		foreach ( array_keys( $_GET ) as $k ) {
			if ( $this->starts_with( $k, $group . '__' ) ) $keys[] = $k;
		}
		return $keys;
	}

	private function collect_tax_keys( $group, $taxonomy ) {
		$prefix = $group . '__' . $taxonomy;
		$keys   = [];
		foreach ( array_keys( $_GET ) as $k ) {
			if ( $this->starts_with( $k, $prefix ) ) $keys[] = $k;
		}
		return $keys;
	}

	private function remove_group_params_from_url( $group ) {
		$keys = $this->collect_group_keys( $group );
		return remove_query_arg( $keys );
	}

	private function remove_tax_params_from_url( $group, $taxonomy ) {
		$keys = $this->collect_tax_keys( $group, $taxonomy );
		return remove_query_arg( $keys );
	}

	private function build_grouped_url_preserve_all( $group, $tax, $term_id, $selected, $allow_multi, $is_active ) {
		$param_key = $group . '__' . $tax;

		// Base = URL courante SANS les clés de CE groupe (pour éviter les restes)
		$base = $this->remove_group_params_from_url( $group );

		// Reconstitue toutes les clés du groupe existant (sauf la courante, que l’on recalcule)
		$q = [];
		foreach ( get_taxonomies( [ 'public' => true ], 'names' ) as $t ) {
			$k = $group . '__' . $t;
			if ( isset( $_GET[ $k ] ) && $k !== $param_key ) $q[ $k ] = $_GET[ $k ];
		}

		$selected = array_map( 'intval', (array) $selected );
		$term_id  = (int) $term_id;

		if ( $is_active ) {
			if ( $allow_multi ) {
				$new = array_values( array_diff( $selected, [ $term_id ] ) );
				if ( ! empty( $new ) ) $q[ $param_key ] = $new;
			} // sinon, on retire toute la taxo -> rien à ajouter
		} else {
			if ( $allow_multi ) {
				$cur = isset( $q[ $param_key ] ) ? (array) $q[ $param_key ] : [];
				$cur = array_map( 'intval', $cur );
				$new = array_values( array_unique( array_merge( $cur, [ $term_id ] ) ) );
				$q[ $param_key ] = $new;
			} else {
				$q[ $param_key ] = $term_id;
			}
		}

		return add_query_arg( $q, $base );
	}

	/* --------- Query helpers --------- */

	private function dnc_build_tax_query_from_group( $group ) {
		$tax_query = [];
		$tax_names = get_taxonomies( [ 'public' => true ], 'names' );
		foreach ( $tax_names as $tax ) {
			$key = $group . '__' . $tax;
			if ( isset( $_GET[ $key ] ) ) {
				$ids = array_filter( array_map( 'intval', (array) $_GET[ $key ] ) );
				if ( $ids ) {
					$tax_query[] = [
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => $ids,
						'operator' => 'IN',
					];
				}
			}
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query );
		}
		return $tax_query;
	}

	private function dnc_get_results_count( $tax_query, $post_types ) {
		$effective_pt = empty( $post_types ) ? [] : array_values( (array) $post_types );

		$args = [
			'post_type'              => empty( $effective_pt ) ? 'any' : $effective_pt,
			'posts_per_page'         => 1,
			'no_found_rows'          => false,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
		];
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$q = new \WP_Query( $args );
		$n = (int) $q->found_posts;
		wp_reset_postdata();
		return $n;
	}

	private function compute_final_tax_query_for_term( $group, $taxonomy, $term_id, $allow_multi, $selected ) {
		$term_id  = (int) $term_id;
		$selected = array_map( 'intval', (array) $selected );

		$base_tq = $this->dnc_build_tax_query_from_group( $group );
		$other   = [];
		$cur     = null;

		foreach ( $base_tq as $entry ) {
			if ( is_array( $entry ) && isset( $entry['taxonomy'] ) && $entry['taxonomy'] === $taxonomy ) {
				$cur = $entry;
			} elseif ( is_array( $entry ) ) {
				$other[] = $entry;
			}
		}

		$is_active = in_array( $term_id, $selected, true );
		if ( $allow_multi ) {
			$new_terms = $is_active
				? array_values( array_diff( $selected, [ $term_id ] ) )
				: array_values( array_unique( array_merge( $selected, [ $term_id ] ) ) );
		} else {
			$new_terms = $is_active ? [] : [ $term_id ];
		}

		$final = $other;
		if ( ! empty( $new_terms ) ) {
			$final[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => array_map( 'intval', $new_terms ),
				'operator' => ( is_array( $cur ) && isset( $cur['operator'] ) ) ? $cur['operator'] : 'IN',
			];
		}
		if ( count( $final ) > 1 ) {
			$final = array_merge( [ 'relation' => 'AND' ], $final );
		}
		return $final;
	}

	private function dnc_get_count_for_term( $group, $taxonomy, $term_id, $allow_multi, $selected, $post_types ) {
		$final_tq = $this->compute_final_tax_query_for_term( $group, $taxonomy, (int) $term_id, (bool) $allow_multi, (array) $selected );
		return $this->dnc_get_results_count( $final_tq, $post_types );
	}

	/**
	 * Compteurs statiques par taxonomie (indépendants des filtres).
	 * @return array<int,int> term_id => count
	 */
	private function dnc_get_static_term_counts( $taxonomy, $post_types ) {
		global $wpdb;
		if ( ! taxonomy_exists( $taxonomy ) ) return [];

		$ttl = (int) apply_filters( 'dnc_qtf_static_counts_ttl', 600 );
		$key = $this->make_cache_key( 'dnc_qtf_static_' . $taxonomy, [
			'pt'   => array_values( (array) $post_types ),
			'lang' => get_locale(),
		] );
		$cached = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) return $cached;

		$pt_sql = '';
		if ( ! empty( $post_types ) ) {
			$pt_in  = array_map( 'esc_sql', array_values( $post_types ) );
			$pt_sql = " AND p.post_type IN ('" . implode( "','", $pt_in ) . "')";
		}

		$sql = "
			SELECT tt.term_id AS term_id, COUNT(DISTINCT p.ID) AS qty
			FROM {$wpdb->term_taxonomy} tt
			INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			WHERE tt.taxonomy = %s
			  AND p.post_status = 'publish'
			  {$pt_sql}
			GROUP BY tt.term_id
		";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $taxonomy ), ARRAY_A ); // phpcs:ignore

		$out = [];
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$out[ (int) $r['term_id'] ] = (int) $r['qty'];
			}
		}
		set_transient( $key, $out, $ttl );
		return $out;
	}
}
