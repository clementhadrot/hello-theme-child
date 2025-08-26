<?php
// Autoriser les uploads HEIC/HEIF si l’option est activée
add_filter('upload_mimes', function ($mimes) {
    $options = get_option('dnc_theme_options');
    $formats = $options['formats_acceptes'] ?? [];

    if (in_array('heic', $formats)) {
        $mimes['heic'] = 'image/heic';
        $mimes['heif'] = 'image/heif';
    }

    if (in_array('tif', $formats)) {
        $mimes['tif'] = 'image/tiff';
        $mimes['tiff'] = 'image/tiff';
    }
    
    if (in_array('webp', $formats)) {
        $mimes['webp'] = 'image/webp';
    }
    
    if (in_array('avif', $formats)) {
    $mimes['avif'] = 'image/avif';
    }

    if (in_array('jxl', $formats)) {
        $mimes['jxl'] = 'image/jxl';
    }

    return $mimes;
});



// Convertir HEIC/HEIF ou TIF/TIFF en JPG à l’upload si activé
add_filter('wp_handle_upload_prefilter', function ($file) {
    $options = get_option('dnc_theme_options');
    $formats = $options['formats_acceptes'] ?? [];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $should_convert = (
        (in_array('heic', $formats) && in_array($ext, ['heic', 'heif'])) ||
        (in_array('tif', $formats) && in_array($ext, ['tif', 'tiff']))
    );

    if (!$should_convert || !class_exists('Imagick')) {
        return $file;
    }

    try {
        $image = new Imagick();
        $image->readImage($file['tmp_name']);
        $image->setImageFormat('jpeg');

        $new_path = preg_replace('/\.(heic|heif|tif|tiff)$/i', '.jpg', $file['tmp_name']);
        $image->writeImage($new_path);

        $file['tmp_name'] = $new_path;
        $file['name'] = preg_replace('/\.(heic|heif|tif|tiff)$/i', '.jpg', $file['name']);
        $file['type'] = 'image/jpeg';

        $image->clear();
        $image->destroy();
    } catch (Exception $e) {
        error_log('[dnc_theme] Erreur conversion image : ' . $e->getMessage());
    }

    return $file;
});

add_action('admin_notices', function () {
    $options = get_option('dnc_theme_options');
    $formats = $options['formats_acceptes'] ?? [];

    if (empty($formats)) return;

    if (!class_exists('Imagick')) {
        echo '<div class="notice notice-error"><p><strong>[dnc_theme]</strong> La bibliothèque <code>Imagick</code> est requise pour convertir les formats HEIC et TIFF. Elle n’est pas installée.</p></div>';
        return;
    }

    $imagick = new Imagick();
    $supported = array_map('strtolower', $imagick->queryFormats());

    if (in_array('heic', $formats) && !in_array('heic', $supported) && !in_array('heif', $supported)) {
        echo '<div class="notice notice-warning"><p><strong>[dnc_theme]</strong> Imagick est installé, mais ne prend pas en charge les images <code>HEIC</code>/<code>HEIF</code>. Activez <code>libheif</code> sur le serveur.</p></div>';
    }

    if (in_array('tif', $formats) && !in_array('tiff', $supported) && !in_array('tif', $supported)) {
        echo '<div class="notice notice-warning"><p><strong>[dnc_theme]</strong> Imagick est installé, mais ne prend pas en charge les images <code>TIFF</code>. Vérifiez la compilation de votre serveur.</p></div>';
    }
    
    if (in_array('webp', $formats) || in_array('avif', $formats) || in_array('jxl', $formats)) {
    echo '<div class="notice notice-info"><p><strong>[dnc_theme]</strong> Les formats <code>WebP</code>, <code>AVIF</code> et <code>JPEG XL</code> sont acceptés tels quels. Assurez-vous que votre serveur et votre navigateur les supportent pour un affichage optimal.</p></div>';
}

});
