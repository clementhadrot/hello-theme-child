(function(){
  if (document.body && document.body.classList.contains('elementor-editor-active')) {
    return;
  }

  function b64encodeUnicode(str){
    try { return btoa(unescape(encodeURIComponent(str))); }
    catch(e){ return btoa(str); }
  }
  function b64decodeUnicode(str){
    try { return decodeURIComponent(escape(atob(str))); }
    catch(e){ return atob(str); }
  }
  function normalizeRel(existing, toAdd) {
    var set = Object.create(null);
    (existing || '').split(/\s+/).forEach(function(x){ if(x) set[x]=1; });
    (toAdd || '').split(/\s+/).forEach(function(x){ if(x) set[x]=1; });
    return Object.keys(set).join(' ').trim();
  }
  function shouldObfuscate(url, mode){
    if (!url) return false;
    var isMail = /^mailto:/i.test(url);
    if (mode === 'mailto') return isMail;
    if (mode === 'urls')   return !isMail;
    return true; // auto
  }
  function pickTargets(wrapper) {
    var scope   = wrapper.getAttribute('data-dnc-obuf-scope') || 'first';
    var sel     = wrapper.getAttribute('data-dnc-obuf-selector') || 'a';
    if (scope === 'selector') {
      try { return wrapper.querySelectorAll(sel); }
      catch(e){ return []; }
    }
    var links = wrapper.querySelectorAll('a');
    if (!links.length) return [];
    if (scope === 'first') return [links[0]];
    return links; // all
  }

  function navigate(url, wantBlank, ev){
    if (!url) return;
    if (ev) ev.preventDefault();
    if (/^mailto:/i.test(url)) {
      window.location.href = url;
    } else if (wantBlank || (ev && (ev.ctrlKey || ev.metaKey || ev.button === 1))) {
      window.open(url, '_blank');
    } else {
      window.location.href = url;
    }
  }

  // Initialisation pour <a>
  function processWrapperAnchors(wrapper){
    var mode   = wrapper.getAttribute('data-dnc-obuf-mode')   || 'auto';
    var target = wrapper.getAttribute('data-dnc-obuf-target') || 'same';
    var relAdd = wrapper.getAttribute('data-dnc-obuf-rel')    || '';
    var wantBlank = (target === 'blank');

    var links = pickTargets(wrapper);
    if (!links || !links.length) return;

    links.forEach(function(a){
      if (!a || a.dataset.dncObfuscatd === '1') return;
      var href = a.getAttribute('href');
      if (!shouldObfuscate(href, mode)) return;

      var encoded = b64encodeUnicode(href);
      a.dataset.dncObf = encoded;

      a.setAttribute('href', '#');
      a.classList.add('dnc-obfuscated');

      if (wantBlank) { a.setAttribute('target', '_blank'); }
      var relFinal = normalizeRel(a.getAttribute('rel') || '', relAdd + (wantBlank ? ' noopener noreferrer' : ''));
      if (relFinal) a.setAttribute('rel', relFinal);

      a.addEventListener('click', function(ev){ navigate(b64decodeUnicode(a.dataset.dncObf || ''), wantBlank, ev); });
      a.addEventListener('auxclick', function(ev){ if (ev.button === 1) navigate(b64decodeUnicode(a.dataset.dncObf || ''), true, ev); });
      a.addEventListener('keydown', function(ev){ if (ev.key === 'Enter' || ev.keyCode === 13) navigate(b64decodeUnicode(a.dataset.dncObf || ''), wantBlank, ev); });

      a.dataset.dncObfuscatd = '1';
    });
  }

  // Initialisation pour éléments génériques (<span role="link">…)
  function processWrapperGenerics(wrapper){
    var wantBlankDefault = (wrapper.getAttribute('data-dnc-obuf-target') || 'same') === 'blank';

    var nodes = wrapper.querySelectorAll('[data-dnc-obf-gen="1"][data-dnc-obf]');
    if (!nodes.length) return;

    nodes.forEach(function(el){
      if (el.dataset.dncObfuscatd === '1') return;

      var wantBlank = (el.getAttribute('data-dnc-obuf-target') || (wantBlankDefault ? 'blank' : 'same')) === 'blank';
      // Style / UX
      el.style.cursor = el.style.cursor || 'pointer';

      var go = function(ev){ navigate(b64decodeUnicode(el.getAttribute('data-dnc-obf') || ''), wantBlank, ev); };
      el.addEventListener('click', go);
      el.addEventListener('auxclick', function(ev){ if (ev.button === 1) navigate(b64decodeUnicode(el.getAttribute('data-dnc-obf') || ''), true, ev); });
      el.addEventListener('keydown', function(ev){ if (ev.key === 'Enter' || ev.keyCode === 13) go(ev); });

      el.dataset.dncObfuscatd = '1';
    });
  }

  function init(root){
    var selector = '[data-dnc-obuf="1"]';
    var wrappers = (root || document).querySelectorAll(selector);
    if (!wrappers.length) return;
    wrappers.forEach(function(w){
      processWrapperAnchors(w);
      processWrapperGenerics(w);
    });
  }

  document.addEventListener('DOMContentLoaded', function(){ init(document); });

  window.addEventListener('elementor/frontend/init', function() {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope){
        init($scope && $scope[0] ? $scope[0] : document);
      });
    }
  });
})();
