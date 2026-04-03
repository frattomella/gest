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
    var items = qsa('.gpo-brand-item', track);
    if (!carousel) return;
    var interval = parseInt(carousel.getAttribute('data-interval') || '4500', 10);
    var autoplay = carousel.getAttribute('data-autoplay') === 'yes';
    var speed = parseInt(carousel.getAttribute('data-speed') || '450', 10);
    var loop = carousel.getAttribute('data-loop') === 'yes';
    var autoTimer = null;
    var setWidth = 0;
    var scrollAnimation = null;
    if (!track || !viewport || items.length <= 1) return;

    function cloneItem(item) {
      var clone = item.cloneNode(true);
      clone.classList.add('is-clone');
      clone.setAttribute('aria-hidden', 'true');
      clone.tabIndex = -1;
      return clone;
    }

    function buildLoopTrack() {
      var before = document.createDocumentFragment();
      var after = document.createDocumentFragment();

      if (!loop || items.length <= 1 || track.dataset.loopReady === '1') {
        return;
      }

      items.forEach(function(item){
        before.appendChild(cloneItem(item));
        after.appendChild(cloneItem(item));
      });

      track.insertBefore(before, track.firstChild);
      track.appendChild(after);
      track.dataset.loopReady = '1';
    }

    function gapSize() {
      var styles = window.getComputedStyle(track);
      return parseFloat(styles.columnGap || styles.gap || '0');
    }

    function desiredCardSize() {
      return parseFloat(window.getComputedStyle(shell).getPropertyValue('--gpo-brand-card-size')) || 168;
    }

    function applySizing() {
      var gap = gapSize();
      var desired = desiredCardSize();
      var target = desired;

      if (window.matchMedia('(max-width: 640px)').matches) {
        target = Math.min(desired, Math.max(140, viewport.clientWidth - 12));
      } else if (window.matchMedia('(max-width: 980px)').matches) {
        target = Math.min(desired, Math.max(132, (viewport.clientWidth - gap) / 2));
      } else {
        target = Math.min(desired, Math.max(128, (viewport.clientWidth - (gap * 2)) / 3));
      }

      track.style.setProperty('--gpo-brand-card-effective-size', Math.floor(target) + 'px');
    }

    function step() {
      var visibleItems = qsa('.gpo-brand-item', track);
      var sample = visibleItems[Math.floor(visibleItems.length / 2)] || visibleItems[0];
      if (!sample) {
        return Math.max(220, Math.round(viewport.clientWidth * 0.8));
      }

      return sample.getBoundingClientRect().width + gapSize();
    }

    function computeSetWidth() {
      applySizing();
      setWidth = step() * items.length;
      if (loop && setWidth > 0 && !viewport.dataset.centered) {
        viewport.scrollLeft = setWidth;
        viewport.dataset.centered = '1';
      }
    }

    function normalizeLoopPosition() {
      if (!loop || setWidth <= 0) {
        return;
      }

      if (viewport.scrollLeft <= setWidth * 0.25) {
        viewport.scrollLeft += setWidth;
      } else if (viewport.scrollLeft >= setWidth * 1.75) {
        viewport.scrollLeft -= setWidth;
      }
    }

    function easeInOutQuad(value) {
      return value < 0.5 ? 2 * value * value : 1 - Math.pow(-2 * value + 2, 2) / 2;
    }

    function animateScroll(distance) {
      var start = viewport.scrollLeft;
      var target = start + distance;
      var startedAt = null;

      if (scrollAnimation) {
        window.cancelAnimationFrame(scrollAnimation);
        scrollAnimation = null;
      }

      function frame(timestamp) {
        var progress;

        if (!startedAt) {
          startedAt = timestamp;
        }

        progress = Math.min(1, (timestamp - startedAt) / Math.max(300, speed));
        viewport.scrollLeft = start + (distance * easeInOutQuad(progress));

        if (progress < 1) {
          scrollAnimation = window.requestAnimationFrame(frame);
        } else {
          scrollAnimation = null;
          normalizeLoopPosition();
        }
      }

      scrollAnimation = window.requestAnimationFrame(frame);
    }

    function go(dir){
      animateScroll(dir * step());
    }

    if (prev) prev.addEventListener('click', function(){ go(-1); stop(); start(); });
    if (next) next.addEventListener('click', function(){ go(1); stop(); start(); });

    function stop(){ if (autoTimer) clearInterval(autoTimer); autoTimer = null; }
    function start(){ stop(); if (autoplay) autoTimer = setInterval(function(){ go(1); }, interval); }

    shell.addEventListener('mouseenter', stop);
    shell.addEventListener('mouseleave', start);
    shell.addEventListener('focusin', stop);
    shell.addEventListener('focusout', start);

    viewport.addEventListener('scroll', normalizeLoopPosition, { passive: true });
    window.addEventListener('resize', function(){
      viewport.dataset.centered = '';
      computeSetWidth();
    });

    buildLoopTrack();
    computeSetWidth();
    start();
  }

  function boot(){
    qsa('.gpo-vehicle-search').forEach(initSearch);
    qsa('.gpo-brand-carousel-shell').forEach(initBrandCarousel);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  if (window.wp && window.wp.domReady) { window.wp.domReady(function(){ setTimeout(boot, 80); }); }
})();
