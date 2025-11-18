jQuery(document).ready(function($) {

    $('[class*="elementor"]').each(function() {
        var el = this;
        var $el = $(this);

        // Read computed background-image
        var bg = window.getComputedStyle(el).backgroundImage;

        if (!bg || bg === 'none' || !bg.includes('url(')) return;

        // Extract URL
        var match = bg.match(/url\(["']?(.*?)["']?\)/);
        if (!match || !match[1]) return;

        var imageUrl = match[1];

        // Avoid duplicate span
        if ($el.find('.background-copyright').length) return;

        // AJAX call
        $.post(BackgroundCopyright.ajax_url, {
            action: 'get_caption_from_url',
            image_url: imageUrl
        }, function(response) {
            if (response.success && response.data) {
                var span = $('<span class="background-copyright"></span>').text(response.data);
                $el.css('position', 'relative').append(span);
            }
        });
    });
});
