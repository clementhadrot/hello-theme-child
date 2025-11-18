(function() {

  // Initialise Tippy sur tous les éléments qui ont data-dnc-tooltip
  function initDncTooltips(root) {

    if (typeof tippy === 'undefined') return;

    var selector = '[data-dnc-tooltip]';
    var nodes = (root || document).querySelectorAll(selector);

    if (!nodes.length) return;



    nodes.forEach(function(el){
      // Evite doublons si re-render Elementor
      if (el._dncTippy) return;


      var content   = el.getAttribute('data-dnc-tooltip') || '';
      var placement = el.getAttribute('data-dnc-placement') || 'top';
      var trigger   = el.getAttribute('data-dnc-trigger') || 'mouseenter focus';
      var theme     = el.getAttribute('data-dnc-theme') || 'dark';
      var maxWidth  = parseInt(el.getAttribute('data-dnc-maxwidth') || '260', 10);
      var delay     = parseInt(el.getAttribute('data-dnc-delay') || '50', 10);

      trigger = trigger + 'click touch';

      if (!content.trim()) return;

      el._dncTippy = tippy(el, {
        content: content,
        placement: placement,
        trigger: trigger,
        theme: theme,
        maxWidth: maxWidth,
        delay: [delay, delay],
        arrow: true,
        allowHTML: false,     // simple & safe; passez à true si besoin d'HTML
        touch: ['hold', 250], // press long sur mobile
      });
    });
  }

  // Init au chargement
  document.addEventListener('DOMContentLoaded', function(){ initDncTooltips(document); });

  // Support Elementor (éditor live)
  window.addEventListener('elementor/frontend/init', function() {
    jQuery(window).on('elementor/frontend/init', function() {
      // Après chaque render de widget
      elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope){
        initDncTooltips($scope[0]);
      });
    });
  });
})();
