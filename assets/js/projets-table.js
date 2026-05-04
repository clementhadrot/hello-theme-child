jQuery(document).ready(function ($) {
	if ($('#projets-table').length) {
		$('#projets-table').DataTable({
			language: {
				url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/fr-FR.json',
			},
			pageLength: 25,
			responsive: true,
		});
	}
});
