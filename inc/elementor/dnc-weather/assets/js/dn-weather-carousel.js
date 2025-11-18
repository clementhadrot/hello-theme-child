(function ($) {
  const init = ($scope) => {
    const $root = $scope.find('.dn-weather-carousel');
    if (!$root.length) return;

    const $swiperEl = $root.find('.swiper');
    if (!$swiperEl.length) return;

    // Eviter les double-inits (éditeur / Ajax / rerender)
    const prev = $root.data('dn-swiper');
    if (prev && prev.destroy) {
      prev.destroy(true, true);
      $root.removeData('dn-swiper');
    }

    // Utilitaire Elementor => charge Swiper si nécessaire, et gère les versions
    const config = {
      loop: false,
      spaceBetween: 12,
      a11y: true,
      slidesPerView: parseInt($root.data('spv-mobile') || 1, 10),
      breakpoints: {
        768:  { slidesPerView: parseInt($root.data('spv-tablet') || 2, 10) },
        1024: { slidesPerView: parseInt($root.data('spv-desktop') || 3, 10) }
      },
      pagination: {
        el: $root.find('.swiper-pagination')[0],
        clickable: true
      },
      navigation: {
        nextEl: $root.find('.swiper-button-next')[0],
        prevEl: $root.find('.swiper-button-prev')[0]
      }
    };

    // elementorFrontend.utils.swiper peut retourner une instance ou une Promise selon versions
    const result = elementorFrontend.utils.swiper($swiperEl, config);

    if (result && typeof result.then === 'function') {
      result.then((instance) => $root.data('dn-swiper', instance));
    } else if (result) {
      $root.data('dn-swiper', result);
    }
  };

  // Hook Elementor : init à chaque rendu du widget
  $(window).on('elementor/frontend/init', () => {
    elementorFrontend.hooks.addAction(
      'frontend/element_ready/dn-weather-carousel.default',
      init
    );
  });
})(jQuery);
