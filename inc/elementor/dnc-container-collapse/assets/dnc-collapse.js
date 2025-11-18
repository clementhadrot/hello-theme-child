(function($){
  const uid = () => 'dnc-' + Math.random().toString(36).slice(2, 9);

  function setHeightAnim(el, to) {
    const from = el.getBoundingClientRect().height;
    el.style.height = from + 'px';
    el.offsetHeight; // reflow
    el.style.height = to + 'px';
  }
  function closeArea(area) {
    setHeightAnim(area, 0);
    area.dataset.dncState = 'closed';
    area.setAttribute('aria-hidden', 'true');
    area.classList.add('is-collapsed');
  }
  function openArea(area) {
    const targetH = area.scrollHeight;
    setHeightAnim(area, targetH);
    area.dataset.dncState = 'open';
    area.setAttribute('aria-hidden', 'false');
    area.classList.remove('is-collapsed');
    area.addEventListener('transitionend', function handler(e){
      if (e.propertyName === 'height') {
        area.style.height = 'auto';
        area.removeEventListener('transitionend', handler);
      }
    });
  }

  // Trouve l’ancêtre commun le plus bas d’une liste de noeuds
  function commonAncestor(nodes) {
    if (!nodes.length) return null;
    if (nodes.length === 1) return nodes[0].parentElement || nodes[0];

    const path = (el) => {
      const p = [];
      while (el) { p.push(el); el = el.parentElement; }
      return p;
    };
    const paths = nodes.map(n => path(n));
    // Prend le plus court comme référence
    let ref = paths.reduce((a,b) => a.length <= b.length ? a : b);
    // remonte jusqu’à trouver un ancêtre présent dans tous
    for (let i = 0; i < ref.length; i++) {
      const candidate = ref[i];
      if (paths.every(p => p.includes(candidate))) {
        return candidate;
      }
    }
    return document.body;
  }

  function buildButton(area, labels, insertWhere){
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dnc-toggle';
    const labelSpan = document.createElement('span');
    const chev = document.createElement('span');
    chev.className = 'dnc-toggle__chev';
    chev.innerHTML = '▾';
    btn.appendChild(labelSpan);
    btn.appendChild(chev);

    // état initial
    if (!area.id) area.id = uid();
    btn.setAttribute('aria-controls', area.id);

    const isClosed = (area.dataset.dncState || 'open') === 'closed';
    btn.setAttribute('aria-expanded', String(!isClosed));
    labelSpan.textContent = isClosed ? labels.open : labels.close;

    // placement
    if (insertWhere === 'after') {
      area.parentNode.insertBefore(btn, area.nextSibling);
    } else {
      area.parentNode.insertBefore(btn, area);
    }

    btn.addEventListener('click', () => {
      const opened = area.dataset.dncState === 'open';
      if (opened) {
        area.style.height = area.scrollHeight + 'px';
        requestAnimationFrame(()=> closeArea(area));
        btn.setAttribute('aria-expanded', 'false');
        labelSpan.textContent = labels.open;
      } else {
        area.style.height = '0px';
        requestAnimationFrame(()=> openArea(area));
        btn.setAttribute('aria-expanded', 'true');
        labelSpan.textContent = labels.close;
      }
    });

    return btn;
  }

  // Initialisation mode "non groupé" (legacy)
  function initSingle($root){
    const targetSel = $root.data('dnc-target') || '> .e-con-inner';
    const area = $root.find(targetSel).get(0);
    if (!area) return;

    const defaultState = ($root.data('dnc-default') || 'open') + '';
    const labels = {
      open:  ($root.data('dnc-open-label')  || 'Ouvrir'),
      close: ($root.data('dnc-close-label') || 'Fermer'),
    };
    const pos = $root.data('dnc-button-position') || 'before';

    area.classList.add('dnc-collapsible');
    area.style.height = 'auto';
    const autoH = area.scrollHeight;

    if (defaultState === 'closed') {
      area.style.height = '0px';
      area.setAttribute('aria-hidden', 'true');
      area.dataset.dncState = 'closed';
      area.classList.add('is-collapsed');
    } else {
      area.style.height = autoH + 'px';
      requestAnimationFrame(()=> area.style.height = 'auto');
      area.setAttribute('aria-hidden', 'false');
      area.dataset.dncState = 'open';
      area.classList.remove('is-collapsed');
    }

    buildButton(area, labels, pos);
  }

  // Initialisation mode "groupé" (un seul bouton)
  function initGrouped($scope){
    // Regroupe par data-dnc-group
    const $allGrouped = $scope.find('[data-dnc-collapse="yes"][data-dnc-group]');
    if (!$allGrouped.length) return;

    const groups = {};
    $allGrouped.each(function(){
      const $el = $(this);
      const key = $el.data('dnc-group');
      if (!groups[key]) groups[key] = [];
      groups[key].push(this);
    });

    Object.entries(groups).forEach(([key, nodes]) => {
      const $nodes = $(nodes);
      // Détermine le contrôleur
      let controller =
        $nodes.filter('[data-dnc-role="controller"]')[0] ||
        $nodes.filter('[data-dnc-role="auto"]')[0] || // 1er auto
        nodes[0];

      const $ctrl = $(controller);
      const defaultState = ($ctrl.data('dnc-default') || 'open') + '';
      const labels = {
        open:  ($ctrl.data('dnc-open-label')  || 'Ouvrir'),
        close: ($ctrl.data('dnc-close-label') || 'Fermer'),
      };
      const pos = $ctrl.data('dnc-button-position') || 'before';
      const wrapperSel = ($ctrl.data('dnc-wrapper') || '').toString().trim();

      // Calcule la "grande zone" (area)
      let area = null;

      if (wrapperSel && wrapperSel !== ':scope') {
        // on part du contrôleur, on cherche d’abord un ancêtre, sinon un descendant
        area = $ctrl.closest(wrapperSel).get(0) || $ctrl.find(wrapperSel).get(0);
      }

      if (!area) {
        // si pas de wrapper explicite, on prend l’ancêtre commun des nodes
        area = commonAncestor(nodes);
      }
      if (!area) return;

      // prépare l’area
      area.classList.add('dnc-collapsible');
      area.style.height = 'auto';
      const autoH = area.scrollHeight;

      if (defaultState === 'closed') {
        area.style.height = '0px';
        area.setAttribute('aria-hidden', 'true');
        area.dataset.dncState = 'closed';
        area.classList.add('is-collapsed');
      } else {
        area.style.height = autoH + 'px';
        requestAnimationFrame(()=> area.style.height = 'auto');
        area.setAttribute('aria-hidden', 'false');
        area.dataset.dncState = 'open';
        area.classList.remove('is-collapsed');
      }

      // insère 1 SEUL bouton pour le groupe
      buildButton(area, labels, pos);
    });
  }

  function initAll($scope){
    const $roots = $scope ? $scope.find('[data-dnc-collapse="yes"]') : $('[data-dnc-collapse="yes"]');
    if (!$roots.length) return;

    // D’abord les groupes (pour éviter de créer 3 boutons)
    initGrouped($scope || $(document));

    // Puis les éléments non groupés
    $roots.filter(':not([data-dnc-group])').each(function(){ initSingle($(this)); });
  }

  $(window).on('elementor/frontend/init', function(){
    initAll($(document));
    //elementorFrontend.hooks.addAction('frontend/element_ready/container', initAll);
    //elementorFrontend.hooks.addAction('frontend/element_ready/section',   initAll);
  });

})(jQuery);
