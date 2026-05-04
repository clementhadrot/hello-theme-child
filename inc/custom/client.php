<?php
/**
 * Shortcode [projets_table] — tableau filtrable des projets via DataTables
 */

function projets_table_shortcode() {
	$query = new WP_Query( [
		'post_type'      => 'projet',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'numero',
		'order'          => 'ASC',
	] );

	if ( ! $query->have_posts() ) {
		return '<p>Aucun projet trouvé.</p>';
	}

	ob_start();
	?>
	<table id="projets-table" class="display" style="width:100%">
		<thead>
			<tr>
				<th>N°</th>
				<th>Nom du projet</th>
				<th>Catégorie</th>
				<th>Localisation</th>
				<th>Surface</th>
				<th>Budget</th>
				<th>Statut</th>
			</tr>
		</thead>
		<tbody>
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();

			$numero  = get_field( 'numero' );
			$surface = get_field( 'surface' );
			$budget  = get_field( 'budget' );
			$statut  = get_field( 'statut' );
			$commune = get_field( 'commune' );

			$categories = get_the_terms( get_the_ID(), 'categorie_de_projet' );
			$cat_name   = '';
			if ( $categories && ! is_wp_error( $categories ) ) {
				$cat_name = implode( ', ', wp_list_pluck( $categories, 'name' ) );
			}
			?>
			<tr>
				<td><?php echo esc_html( $numero ); ?></td>
				<td><?php the_title(); ?></td>
				<td><?php echo esc_html( $cat_name ); ?></td>
				<td><?php echo esc_html( $commune ); ?></td>
				<td><?php echo esc_html( $surface ); ?></td>
				<td><?php echo esc_html( $budget ); ?></td>
				<td><?php echo esc_html( $statut ); ?></td>
			</tr>
		<?php
		endwhile;
		wp_reset_postdata();
		?>
		</tbody>
	</table>
	<?php

	return ob_get_clean();
}
add_shortcode( 'projets_table', 'projets_table_shortcode' );
