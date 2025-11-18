(function($){
  function getUrlWithParam(url, key, value){
    const u = new URL(url, window.location.origin);
    if (value === '' || value === null) {
      u.searchParams.delete(key);
    } else {
      u.searchParams.set(key, value);
    }
    return u.toString();
  }

  async function fetchWidgetHtml(fullUrl, widgetId){
    const res = await fetch(fullUrl, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP '+res.status);
    const html = await res.text();
    // Parse and find the same widget by [data-id="..."]
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    // Elementor met l’ID sur l’élément wrapper .elementor-element[data-id]
    const sel = '.elementor-element[data-id="'+widgetId+'"]';
    const newWidget = doc.querySelector(sel);
    if (!newWidget) throw new Error('Widget introuvable dans la réponse');
    return newWidget.innerHTML; // on remplace uniquement l’intérieur
  }

  function reinitElementorWidget($widgetScope){
    // Elementor doit réinitialiser le widget pour carrousel (Swiper, etc.)
    // 1) trigger le ready des handlers
    if (window.elementorFrontend && elementorFrontend.elementsHandler) {
      try{
        elementorFrontend.elementsHandler.runReadyTrigger($widgetScope);
      }catch(e){}
    }
    // 2) certains widgets carrousel écoutent ce hook
    if (window.elementorFrontend && elementorFrontend.hooks) {
      try{
        elementorFrontend.hooks.doAction('frontend/element_ready/global', $widgetScope);
        elementorFrontend.hooks.doAction('frontend/element_ready/loop-carousel.default', $widgetScope);
        elementorFrontend.hooks.doAction('frontend/element_ready/loop-grid.default', $widgetScope);
      }catch(e){}
    }
  }

  function updateActiveLink($bar, termSlug){
    $bar.find('.dnc-tax-filter__link').removeClass('is-active');
    const sel = termSlug ? '.dnc-tax-filter__link[data-term="'+termSlug+'"]' : '.dnc-tax-filter__link[data-term=""]';
    $bar.find(sel).addClass('is-active');
  }

  $(document).on('click', '.dnc-tax-filter .dnc-tax-filter__link', async function(e){
    // Evite le rechargement
    e.preventDefault();

    const $link   = $(this);
    const $bar    = $link.closest('.dnc-tax-filter');
    const term    = $link.attr('data-term') || '';
    const param   = $bar.data('param');
    const widgetId= $bar.data('widget-id');

    // Le widget wrapper actuel
    const $widget = $('.elementor-element[data-id="'+widgetId+'"]').first();
    if (!$widget.length) return;

    // URL cible (on conserve les autres query params)
    const targetUrl = getUrlWithParam(window.location.href, String(param), term);

    // Etat visuel (loading)
    const $inner = $widget.children().first();
    $widget.addClass('dnc-tax-filter--loading');
    $inner.css('opacity', .5);

    try{
      const newInnerHtml = await fetchWidgetHtml(targetUrl, widgetId);
      // Remplace le fragment
      $inner.replaceWith($(newInnerHtml).first());
      // Met à jour l'URL (sans reload)
      window.history.pushState({ dnTaxFilter: true }, '', targetUrl);
      // Met à jour l’actif
      updateActiveLink($bar, term);
      // Ré-initialise le widget côté Elementor
      reinitElementorWidget($widget);
    }catch(err){
      console.error('DN Tax Filter error:', err);
      // fallback: si souci, on suit le lien (rechargement complet)
      window.location.href = $link.attr('href');
      return;
    }finally{
      $widget.removeClass('dnc-tax-filter--loading');
      $widget.css('opacity', 1);
    }
  });

  // Gère navigation via bouton Retour/Avant
  window.addEventListener('popstate', async function(ev){
    // On tente de re-synchroniser l’état si on a des barres présentes
    $('.dnc-tax-filter').each(async function(){
      const $bar = $(this);
      const param = $bar.data('param'); 
      const widgetId = $bar.data('widget-id');
      const url = new URL(window.location.href);
      const term = url.searchParams.get(param) || '';
      updateActiveLink($bar, term);

      const $widget = $('.elementor-element[data-id="'+widgetId+'"]').first();
      if (!$widget.length) return;

      try{
        const newInnerHtml = await fetchWidgetHtml(url.toString(), widgetId);
        const $inner = $widget.children().first();
        $inner.replaceWith($(newInnerHtml).first());
        reinitElementorWidget($widget);
      }catch(e){
        console.error(e);
      }
    });
  });

})(jQuery);
