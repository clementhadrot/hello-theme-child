<?php
/**
 * Register Elementor widgets and utilities for the child theme.
 */

$elementor_base = DNC_INC_DIR . '/elementor/';

require_once $elementor_base . 'dnc-obfuscate/dnc-obfuscate.php';
require_once $elementor_base . 'dnc-tooltips/dnc-tooltips.php';
require_once $elementor_base . 'dnc-widget-image/dnc-widget-image.php';

if (!dnc_weather_is_disabled()) {
    require_once $elementor_base . 'dnc-weather/dnc-weather.php';
}

require_once $elementor_base . 'dnc-sitemap/dnc-sitemap.php';
require_once $elementor_base . 'dnc-accessibility/dnc-aria-addon.php';
