// /js/dnc-swiper-mobile-loop-fix.js
(function ($) {
  const MOBILE_MAX = 768;

  function disableLoopIfMobileOnInstance(container) {
    const inst = container.swiper || $(container).data('swiper');
    if (!inst) return false;

    if (window.innerWidth <= MOBILE_MAX && inst.params.loop) {
      inst.params.loop = false;
      if (typeof inst.loopDestroy === 'function') { try { inst.loopDestroy(); } catch (e) {} }
      if (typeof inst.update === 'function') inst.update();
    }
    return true;
  }

  function forceNoLoopInDataAttrs(container) {
    // Pour les conteneurs pas encore initialisés (Elementor récent)
    const attr = container.getAttribute('data-swiper-options') || container.getAttribute('data-swiper');
    if (!attr) return false;
    try {
      const opts = JSON.parse(attr);
      if (window.innerWidth <= MOBILE_MAX) {
        opts.loop = false;
        container.setAttribute('data-swiper-options', JSON.stringify(opts));
        if (container.hasAttribute('data-swiper')) {
          container.setAttribute('data-swiper', JSON.stringify(opts));
        }
      }
      return true;
    } catch(e){ return false; }
  }

  function run(scope) {
    const $scope = scope instanceof $ ? scope : $(scope);
    const selectors = '.e-widget-swiper, .e-swiper-container, .swiper, .swiper-container';
    $scope.find(selectors).each(function () {
      if (!disableLoopIfMobileOnInstance(this)) {
        forceNoLoopInDataAttrs(this); // avant init
      }
    });
  }

  function observeNewNodes() {
    const selectors = ['.e-widget-swiper', '.e-swiper-container', '.swiper', '.swiper-container'];
    const mo = new MutationObserver(muts => {
      muts.forEach(m => m.addedNodes && m.addedNodes.forEach(node => {
        if (!(node instanceof HTMLElement)) return;
        if (selectors.some(sel => node.matches?.(sel) || node.querySelector?.(sel))) run(node);
      }));
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  }

  $(window).on('elementor/frontend/init', function () {
    // Après init des widgets (post-init)
    elementorFrontend.hooks.addAction('frontend/element_ready/global', run);
    elementorFrontend.hooks.addAction('frontend/element_ready/image-carousel.default', run);
    elementorFrontend.hooks.addAction('frontend/element_ready/gallery.default', run);
    elementorFrontend.hooks.addAction('frontend/element_ready/loop-carousel.default', run);

    // Premier passage + observation DOM
    run(document);
    observeNewNodes();

    // Recalcule au resize/orientation (mobile)
    let t; $(window).on('resize orientationchange', () => { clearTimeout(t); t = setTimeout(() => run(document), 120); });
  });
})(jQuery);
