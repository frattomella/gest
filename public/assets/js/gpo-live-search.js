(function(){
  function qs(sel, root){ return (root || document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function escapeHtml(value){
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function initSearch(form){
    if (!form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';
    var input = qs('.gpo-search-input', form);
    var results = qs('.gpo-search-results', form);
    var clear = qs('.gpo-search-clear', form);
    var timer = null;
    var targetUrl = form.getAttribute('data-target-url') || form.action || window.location.href;
    var catalogRef = form.getAttribute('data-catalog-ref') || 'default';

    function syncClear(){ if (clear) clear.hidden = !input.value.trim(); }
    function hide(){ results.hidden = true; results.innerHTML = ''; }
    function render(items){
      if (!items.length) { hide(); return; }
      results.innerHTML = items.map(function(item){
        var thumb = item.thumb
          ? '<span class="gpo-search-result-thumb"><img src="' + escapeHtml(item.thumb) + '" alt="' + escapeHtml(item.title) + '" loading="lazy" /></span>'
          : '<span class="gpo-search-result-thumb"></span>';
        var subtitle = item.subtitle || item.brand || '';
        return '<a class="gpo-search-result" href="'+ escapeHtml(item.url) +'">' +
          thumb +
          '<span class="gpo-search-result-main"><strong>'+ escapeHtml(item.title) +'</strong><small>'+ escapeHtml(subtitle) +'</small></span>' +
          '<span class="gpo-search-result-price">'+ escapeHtml(item.price || '') +'</span>' +
        '</a>';
      }).join('');
      results.hidden = false;
    }

    input.addEventListener('input', function(){
      clearTimeout(timer);
      var value = input.value.trim();
      syncClear();
      if (value.length < 2) { hide(); return; }
      timer = setTimeout(function(){
        var url = (window.gpoSearchData && gpoSearchData.ajaxUrl ? gpoSearchData.ajaxUrl : '/wp-admin/admin-ajax.php') + '?action=gpo_live_search&nonce=' + encodeURIComponent(window.gpoSearchData ? gpoSearchData.nonce : '') + '&q=' + encodeURIComponent(value);
        fetch(url, { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ render((data && data.success && data.data && data.data.results) ? data.data.results : []); })
          .catch(hide);
      }, 180);
    });

    input.addEventListener('focus', function(){ if (results.innerHTML.trim()) results.hidden = false; });
    input.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        hide();
        input.blur();
      }
    });
    if (clear) {
      clear.addEventListener('click', function(){
        input.value = '';
        syncClear();
        hide();
        input.focus();
      });
    }
    document.addEventListener('click', function(e){ if (!form.contains(e.target)) hide(); });
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var value = input.value.trim();
      if (!value) return;
      var url = new URL(targetUrl, window.location.origin);
      url.searchParams.set('gpo_search', value);
      url.searchParams.set('gpo_catalog_ref', catalogRef);
      window.location.href = url.toString();
    });
    syncClear();
  }

  function initBrandCarousel(shell){
    if (!shell || shell.dataset.bound === '1') return;
    shell.dataset.bound = '1';
    var viewport = qs('.gpo-brand-viewport', shell);
    var prev = qs('.gpo-brand-nav.prev', shell);
    var next = qs('.gpo-brand-nav.next', shell);
    var carousel = qs('.gpo-brand-carousel', shell);
    var track = qs('.gpo-brand-track', shell);
    var items = qsa('.gpo-brand-item', shell);
    if (!carousel) return;
    var interval = parseInt(carousel.getAttribute('data-interval') || '4500', 10);
    var autoplay = carousel.getAttribute('data-autoplay') === 'yes';
    var autoTimer = null;
    var scrollTarget = track || viewport;
    var frame = viewport || scrollTarget;
    if (!scrollTarget || !frame || items.length <= 1) return;
    function step(){
      if (items.length) {
        return Math.max(items[0].offsetWidth + 16, 220);
      }
      return Math.max(220, Math.round(frame.clientWidth * 0.7));
    }
    function go(dir){
      var nextLeft = scrollTarget.scrollLeft + (dir * step());
      var maxScroll = Math.max(0, scrollTarget.scrollWidth - scrollTarget.clientWidth);
      if (dir > 0 && scrollTarget.scrollLeft >= maxScroll - 8) {
        scrollTarget.scrollTo({ left: 0, behavior: 'smooth' });
        return;
      }
      if (dir < 0 && scrollTarget.scrollLeft <= 8) {
        scrollTarget.scrollTo({ left: maxScroll, behavior: 'smooth' });
        return;
      }
      scrollTarget.scrollTo({ left: Math.max(0, Math.min(nextLeft, maxScroll)), behavior: 'smooth' });
    }
    if (prev) prev.addEventListener('click', function(){ go(-1); });
    if (next) next.addEventListener('click', function(){ go(1); });
    function stop(){ if (autoTimer) clearInterval(autoTimer); }
    function start(){ stop(); if (autoplay) autoTimer = setInterval(function(){ go(1); }, interval); }
    shell.addEventListener('mouseenter', stop);
    shell.addEventListener('mouseleave', start);
    shell.addEventListener('focusin', stop);
    shell.addEventListener('focusout', start);
    start();
  }

  function boot(){
    qsa('.gpo-vehicle-search').forEach(initSearch);
    qsa('.gpo-brand-carousel-shell').forEach(initBrandCarousel);
  }
  document.addEventListener('DOMContentLoaded', boot);
  if (window.wp && window.wp.domReady) { window.wp.domReady(function(){ setTimeout(boot, 80); }); }
})();
