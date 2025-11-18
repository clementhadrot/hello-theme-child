<?php
// -- Classe du widget (dans un sous-fichier pour lisibilité) --
// Créez le dossier: `inc/elementor-weather-carousel/` et placez-y ce fichier nommé `class-dn-widget-weather-carousel.php` :

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class DN_Widget_Weather extends Widget_Base {
    public function get_name() { return 'dn-weather-carousel'; }
    public function get_title() { return __( 'Météo – Carousel (Weatherstack)', 'dn' ); }
    public function get_icon() { return 'eicon-slider-vertical'; }
    public function get_categories() { return [ 'general' ]; }

    public function get_style_depends() { return [ 'e-swiper', 'swiper', 'dn-weather-carousel' ]; }
    public function get_script_depends() { return [ 'swiper', 'dn-weather-carousel' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'section_content', [ 'label' => __( 'Contenu', 'dn' ) ] );
        $this->add_control( 'location', [
            'label' => __( 'Localisation (ville, pays)', 'dn' ),
            'type' => Controls_Manager::TEXT,
            'placeholder' => 'Strasbourg, FR',
            'default' => 'Strasbourg, FR',
        ] );
        $this->add_control( 'days', [
            'label' => __( 'Nombre de jours (1–14)', 'dn' ),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 14,
            'default' => 4,
        ] );
        $this->add_control( 'units', [
            'label' => __( 'Unités', 'dn' ),
            'type' => Controls_Manager::SELECT,
            'options' => [ 'm' => 'Metric (°C)', 'f' => 'Imperial (°F)' ],
            'default' => 'm',
        ] );
        $this->add_control( 'lang', [
            'label' => __( 'Langue API', 'dn' ),
            'type' => Controls_Manager::TEXT,
            'placeholder' => 'fr',
            'default' => 'fr',
        ] );
        $this->add_control( 'show_nav', [
            'label' => __( 'Afficher navigation', 'dn' ),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Oui', 'dn' ),
            'label_off' => __( 'Non', 'dn' ),
            'default' => 'yes',
        ] );
        $this->end_controls_section();

        $this->start_controls_section( 'section_layout', [ 'label' => __( 'Mise en page', 'dn' ) ] );
        $this->add_control( 'slides_per_view_desktop', [
            'label' => __( 'Slides / Desktop', 'dn' ),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 6,
            'default' => 3,
        ] );
        $this->add_control( 'slides_per_view_tablet', [
            'label' => __( 'Slides / Tablet', 'dn' ),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 6,
            'default' => 2,
        ] );
        $this->add_control( 'slides_per_view_mobile', [
            'label' => __( 'Slides / Mobile', 'dn' ),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 6,
            'default' => 1,
        ] );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $api_key = get_option( 'dn_weatherstack_api_key' );
        if ( empty( $api_key ) ) {
            echo '<div class="elementor-alert elementor-alert-warning">Clé API Weatherstack manquante. Renseignez l’option <code>dn_weatherstack_api_key</code> dans le Customizer.</div>';
            return;
        }
        $days  = max( 1, min( 14, intval( $settings['days'] ) ) );
        $units = in_array( $settings['units'], [ 'm','f' ], true ) ? $settings['units'] : 'm';
        $loc   = trim( $settings['location'] );
        $lang  = sanitize_text_field( $settings['lang'] );

        $data = $this->fetch_weatherstack_forecast( $api_key, $loc, $days, $units, $lang );
        if ( is_wp_error( $data ) ) {
            echo '<div class="elementor-alert elementor-alert-danger">Erreur API Weatherstack: ' . esc_html( $data->get_error_message() ) . '</div>';
            return;
        }
        $slides = $this->build_slides_from_response( $data, $units );
        if ( empty( $slides ) ) {
            echo '<div class="elementor-alert elementor-alert-info">Aucune donnée de prévision disponible.</div>';
            return;
        }
        $widget_id = 'dn-wc-' . $this->get_id();
        $nav = ! empty( $settings['show_nav'] ) && 'yes' === $settings['show_nav'];
        ?>
        <div class="dn-weather-carousel elementor-swiper" 
            id="<?php echo esc_attr( $widget_id ); ?>"
            data-spv-mobile="<?php echo (int) $settings['slides_per_view_mobile']; ?>"
             data-spv-tablet="<?php echo (int) $settings['slides_per_view_tablet']; ?>"
             data-spv-desktop="<?php echo (int) $settings['slides_per_view_desktop']; ?>"
     >
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ( $slides as $slide_html ) : ?>
                        <div class="swiper-slide"><?php echo $slide_html;?></div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <?php if ( $nav ) : ?>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                <?php endif; ?>
            </div>
        </div>
        <script>
        (function($){
            jQuery(window).on('elementor/frontend/init', function(){
                var initHandler = function($scope){
                    var $root = $scope.find('#<?php echo esc_js( $widget_id ); ?>');
                    if(!$root.length || typeof Swiper === 'undefined') return;
                    var $swiperEl = $root.find('.swiper');
                    var swiper = new Swiper($swiperEl[0], {
                        loop: false,
                        spaceBetween: 12,
                        pagination: { el: $root.find('.swiper-pagination')[0], clickable: true },
                        navigation: { nextEl: $root.find('.swiper-button-next')[0], prevEl: $root.find('.swiper-button-prev')[0] },
                        a11y: true,
                        slidesPerView: <?php echo (int) $settings['slides_per_view_mobile']; ?>,
                        breakpoints: {
                            768:  { slidesPerView: <?php echo (int) $settings['slides_per_view_tablet']; ?> },
                            1024: { slidesPerView: <?php echo (int) $settings['slides_per_view_desktop']; ?> }
                        }
                    });
                };
                elementorFrontend.hooks.addAction('frontend/element_ready/<?php echo esc_js( $this->get_name() ); ?>.default', initHandler);
            });
        })(jQuery);
        </script>
        <?php
    }

    private function fetch_weatherstack_forecast( $api_key, $query, $days, $units, $lang ) {
        $transient_key = 'dn_ws_' . md5( strtolower( $query ) . '|' . $days . '|' . $units . '|' . $lang );
        if ( $cached = get_transient( $transient_key ) ) return $cached;
        $url = add_query_arg( [
            'access_key'    => $api_key,
            'query'         => $query,
            'forecast_days' => $days,
            'units'         => $units,
            'language'      => $lang,
            'hourly'        => 1,
        ], 'https://api.weatherstack.com/forecast' );
        //echo $url; die();
        $response = wp_remote_get( $url, [ 'timeout' => 12 ] );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 200 !== $code || ( isset( $body['success'] ) && false === $body['success'] ) ) {
            $err = isset( $body['error']['info'] ) ? $body['error']['info'] : 'Réponse invalide.';
            return new WP_Error( 'weatherstack_error', $err );
        }
        // TTL configurable via option dnc_weather_cache_ttl (1h, 2h, 6h, 24h)
        $ttl = absint( get_option( 'dnc_weather_cache_ttl', HOUR_IN_SECONDS ) );
        $allowed = [ HOUR_IN_SECONDS, 2 * HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, DAY_IN_SECONDS ];
        if ( ! in_array( $ttl, $allowed, true ) ) { $ttl = HOUR_IN_SECONDS; }
        set_transient( $transient_key, $body, $ttl );
        return $body;
    }

    private function build_slides_from_response( array $data, $units ) {
        $slides = [];

        $today = new DateTime('today');
        $tomorrow = (clone $today)->modify('+1 day');;

        $location_label = isset($data['location']['name']) ? $data['location']['name'] : '';
        $unit_symbol = ($units === 'f') ? '°F' : '°C';
        $forecast = isset( $data['forecast'] ) && is_array( $data['forecast'] ) ? $data['forecast'] : [];
        $today_desc = isset($data['current']['weather_descriptions'][0]) ? $data['current']['weather_descriptions'][0] : '';
        $today_icon_code = isset($data['current']['weather_code']) ? $data['current']['weather_code'] : '';

        foreach ( $forecast as $date => $day ) {
            $label = date_i18n( 'D d M', strtotime( $date ) );
            $classtoday = '';
            if(date("Ymd",strtotime( $date )) == date('Ymd') ){
                $label = 'Aujourd\'hui';
                $classtoday = 'dn-wc-today';
            }

            if(date("Ymd",strtotime( $date )) == $tomorrow->format("Ymd") )
                $label = 'Demain';

            //print_r($day); die();
            $iconCode  = isset($day['hourly'][4]['weather_code']) ? $day['hourly'][4]['weather_code'] : $today_icon_code;
            $desc  = isset($day['weather_descriptions'][0]) ? $day['weather_descriptions'][0] : $today_desc;
            $max   = isset($day['maxtemp']) ? $day['maxtemp'] : ( $day['temperature'] ?? '' );
            $min   = isset($day['mintemp']) ? $day['mintemp'] : '';
            $avg   = isset($day['avgtemp']) ? $day['avgtemp'] : '';
            $temps = $avg !== '' ? sprintf('%s%s', esc_html($avg), $unit_symbol) : '';
            if ($min !== '' && $max !== '') {
                $temps = sprintf('%s%s / %s%s', esc_html($min), $unit_symbol, esc_html($max), $unit_symbol);
            } 

            $icon = getDncIcons($iconCode);
            //echo $iconCode; die();
            //. '<div class="dn-wc-place">%s</div>'
            //. '<div class="dn-wc-desc">%s</div>'
            //esc_html( $location_label ),
            //  esc_html( $desc )
            $slides[] = sprintf(
                '<div class="dn-wc-card %s">'
              . '<div class="dn-wc-date">%s</div>'
              . '<div class="dn-wc-icon">%s</div>'
              . '<div class="dn-wc-temps">%s</div>'    
              . '</div>',
                esc_html ($classtoday),
                esc_html( $label ),
                $icon ? '<img src="' . esc_url( $icon ) . '" alt="'.esc_html($desc).'" loading="lazy" />' : '',
                esc_html( $temps ),
              
            );
        }
        return $slides;
    }
}

function getDncIcons($code){
    if(empty($code)) return '';

    $baseMedia = get_stylesheet_directory_uri() . '/inc/elementor/dnc-weather/medias/meteo_picto_';
    switch($code){
        case '179' :
        case '182' :
        case '185' :
        case '227' :
        case '230' :
        case '311' :
        case '314' :
        case '317' :
        case '320' :
        case '323' :
        case '326' :
        case '329' :
        case '332' :
        case '335' :
        case '338' :
        case '350' :
        case '362' :
        case '365' :
        case '368' :
        case '371' :
        case '395' :
        case '392' :
            $icon = $baseMedia.'neige.svg'; 
            break;

        case '176' :
        case '299' :
        case '302' :
        case '305' :
        case '308' :
        case '353' :
        case '356' :
        case '359' : // Grosse pluie
        case '374' : // grele
        case '377' : // grele
        case '386' :
        case '389' :
            $icon = $baseMedia.'pluie.svg'; 
            break;

        case '263' :
        case '266' :
        case '281' :
        case '284' :
        case '293' :
        case '296' :
            $icon = $baseMedia.'bruine.svg'; 
            break;

        case '143' :
        case '248' :
        case '260' :
            $icon = $baseMedia.'brouillard.svg'; 
            break;

        case '200' :
            $icon = $baseMedia.'orage_sec.svg'; 
            break;

        case '116' :
        case '119' :
        case '122' :
            $icon = $baseMedia.'nuageux.svg';
            break;

        case '113' :
            $icon = $baseMedia.'soleil.svg';
            break;
    
    }
    return $icon;
}

add_action( 'wp_enqueue_scripts', function () {
    $base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-weather'; // ou plugin_dir_url(__FILE__)
    wp_register_style( 'dn-weather-carousel', $base . '/assets/css/dnc-weather.css', [], '1.0' );
}, 5);

// Elementor charge déjà Swiper. On enregistre juste notre script qui en dépend.
add_action( 'elementor/frontend/after_register_scripts', function () {
    $base = get_stylesheet_directory_uri() . '/inc/elementor/dnc-weather'; // ou plugin_dir_url(__FILE__)
    wp_register_script(
        'dn-weather-carousel',
        $base . '/assets/js/dn-weather-carousel.js',
        [ 'elementor-frontend', 'swiper' ], // <- important
        '1.0',
        true
    );
}, 5);