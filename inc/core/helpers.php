<?php
/**
 * Small helper functions used across the child theme.
 */

function dnc_is_weather_enabled() {
    $options = get_option('dnc_theme_options');
    return !empty($options['enable_weather']);
}

function dnc_weather_is_disabled() {
    return !dnc_is_weather_enabled() || empty(get_option('dn_weatherstack_api_key'));
}
