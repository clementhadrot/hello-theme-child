
jQuery(document).ready(function ($) {
    // Sauvegarde automatique
    $('#media-editor-table').on('change', 'input', function () {
        const $row = $(this).closest('tr');
        saveRow($row);
    });

    // Bouton "Générer depuis le titre"
    $('#media-editor-table').on('click', '.generate-alt', function () {
        const $row = $(this).closest('tr');
        const title = $row.find('.title-field').val().trim();
        if (title) {
            $row.find('.alt-field').val(title).trigger('change');
        }
    });

    // Bouton "Générer tous les ALT vides"
    $('#generate-all-alt').on('click', function () {
        $('#media-editor-table tbody tr').each(function () {
            const $row = $(this);
            const alt = $row.find('.alt-field').val().trim();
            const title = $row.find('.title-field').val().trim();
            if (!alt && title) {
                $row.find('.alt-field').val(title).trigger('change');
            }
        });
    });

    // Filtrage dynamique
    $('#image-search').on('input', function () {
        const search = $(this).val().toLowerCase();
        $('#media-editor-table tbody tr').each(function () {
            const title = $(this).data('title');
            $(this).toggle(title.includes(search));
        });
    });

    function saveRow($row) {
    const id = $row.data('id');
    const title = $row.find('.title-field').val();
    const legende = $row.find('.legende-field').val();
    const alt = $row.find('.alt-field').val();
    const desc = $row.find('.desc-field').val();
    const $actionCell = $row.find('td').last();

    $.post(MediaEditorAjax.ajax_url, {
        action: 'save_image_fields',
        id: id,
        title: title,
        legende: legende,
        desc: desc,
        alt: alt,
        _ajax_nonce: MediaEditorAjax.nonce
    }, function (response) {
        if (response.success) {
            $actionCell.find('.save-status').remove();
            $actionCell.append('<span class="save-status" style="color:green; margin-left:8px;">✔ Enregistré</span>');
            setTimeout(() => {
                $actionCell.find('.save-status').fadeOut(300, function() { $(this).remove(); });
            }, 1500);
        } else {
            $actionCell.find('.save-status').remove();
            $actionCell.append('<span class="save-status" style="color:red; margin-left:8px;">❌ Erreur</span>');
            setTimeout(() => {
                $actionCell.find('.save-status').fadeOut(300, function() { $(this).remove(); });
            }, 2500);
        }
    });
}

});
