/**
 * DNC – Quick Tax Filters (AJAX + A11Y)
 * - Gère: chip, "Tout" taxo, "Réinitialiser tout"
 * - Remplace filtres + loop du groupe
 * - A11Y: keyboard, aria-busy, aria-live, focus management
 */
(function () {
  'use strict';

  function qs(root, sel){ return (root||document).querySelector(sel); }
  function qsa(root, sel){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function findLoopForGroup(group){
    var sel = '[data-dnc-group="' + group + '"].elementor-widget-loop-grid,'
            + '[data-dnc-group="' + group + '"].elementor-widget-loop-carousel,'
            + '[data-dnc-group="' + group + '"].elementor-widget-posts';
    return qs(document, sel);
  }

  function findFiltersForGroup(group){
    return qs(document, '.dnc-qtf[data-dnc-filters-group="' + group + '"]');
  }

  function extractGroupFragments(htmlText, group){
    var tpl = document.createElement('template');
    tpl.innerHTML = htmlText;
    var newFilters = tpl.content.querySelector('.dnc-qtf[data-dnc-filters-group="' + group + '"]') || null;
    var newLoop = tpl.content.querySelector(
      '[data-dnc-group="' + group + '"].elementor-widget-loop-grid,'
      + '[data-dnc-group="' + group + '"].elementor-widget-loop-carousel,'
      + '[data-dnc-group="' + group + '"].elementor-widget-posts'
    ) || null;
    return {filters:newFilters, loop:newLoop};
  }

  function replaceGroupDOM(group, parts){
    var curFilters = findFiltersForGroup(group);
    var curLoop = findLoopForGroup(group);

    if (curFilters && parts.filters) curFilters.replaceWith(parts.filters);
    if (curLoop && parts.loop) curLoop.replaceWith(parts.loop);
  }

  function setLoopBusy(loopEl, busy){
    if (!loopEl) return;
    loopEl.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function syncSelectedTermCount(group){
    var wrapper = findFiltersForGroup(group);
    if (!wrapper) return;

    var countBox = wrapper.querySelector('.dnc-qtf__count[data-mode="selected-term"]');
    if (!countBox) return;

    var activeChip = wrapper.querySelector('.dnc-qtf__terms .dnc-qtf__chip.dnc-qtf__chip--active[data-term]:not([data-term=""])');
    var val = '';
    if (activeChip) {
      var badge = activeChip.querySelector('.dnc-qtf__term-count');
      if (badge) {
        var m = badge.textContent.match(/\d+/);
        if (m) val = m[0];
      }
    }
    countBox.querySelector('.dnc-qtf__count-value').textContent = val ? val : '—';
  }

  function ajaxReplace(group, targetURL, focusSelector){
    if (!group || !targetURL) return;

    var loop = findLoopForGroup(group);
    setLoopBusy(loop, true);

    return fetch(targetURL, {credentials:'same-origin'})
      .then(function(r){ return r.text(); })
      .then(function(html){
        var parts = extractGroupFragments(html, group);
        replaceGroupDOM(group, parts);
        syncSelectedTermCount(group);
        setLoopBusy(findLoopForGroup(group), false);

        // Restore focus (a11y): focus first active chip or header reset
        var f = findFiltersForGroup(group);
        if (f) {
          var toFocus = f.querySelector(focusSelector || '.dnc-qtf__chip.dnc-qtf__chip--active') ||
                        f.querySelector('.dnc-qtf__reset-all') ||
                        f.querySelector('.dnc-qtf__chip');
          if (toFocus) toFocus.focus({preventScroll:false});
        }
      })
      .catch(function(e){
        setLoopBusy(loop, false);
        window.location.href = targetURL; // fallback
      });
  }

  // Click + keyboard (Enter/Space) delegation
  function handleActivate(link, ev){
    var href  = link.getAttribute('href') || '';
    if (!href) return;

    var group = link.getAttribute('data-group');
    if (!group) {
      var qtf = link.closest('.dnc-qtf');
      if (qtf) group = qtf.getAttribute('data-dnc-filters-group');
    }
    if (!group) return;

    ev.preventDefault();

    // history
    if (history && history.pushState) {
      history.pushState({ dncQtf:true, group:group, url:href }, '', href);
    }

    // pass selector to restore focus to the clicked “role=button”
    ajaxReplace(group, href, '.dnc-qtf__chip[aria-pressed="true"]');
  }

  document.addEventListener('click', function(ev){
    var link = ev.target.closest('a[data-dnc-chip], a[data-dnc-reset="all"]');
    if (!link) return;
    if (document.body && document.body.classList.contains('elementor-editor-active')) return;
    handleActivate(link, ev);
  });

  document.addEventListener('keydown', function(ev){
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var link = ev.target.closest('a[data-dnc-chip], a[data-dnc-reset="all"]');
    if (!link) return;
    ev.preventDefault();
    handleActivate(link, ev);
  });

  window.addEventListener('popstate', function(ev){
    if (!ev.state || !ev.state.dncQtf) { window.location.reload(); return; }
    var group = ev.state.group;
    var url   = ev.state.url || window.location.href;
    ajaxReplace(group, url);
  });

  document.addEventListener('DOMContentLoaded', function(){
    // Init selected-term counter for all groups
    qsa(document, '.dnc-qtf[data-dnc-filters-group]').forEach(function(el){
      var g = el.getAttribute('data-dnc-filters-group');
      syncSelectedTermCount(g);
    });
  });
})();
