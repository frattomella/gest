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

    function syncClear(){
      var hasValue;
      if (!clear) return;
      hasValue = !!input.value.trim();
      clear.hidden = !hasValue;
      clear.setAttribute('aria-hidden', hasValue ? 'false' : 'true');
    }
    function hide(){ results.hidden = true; results.innerHTML = ''; }
    function render(items){
      if (!items.length) { hide(); return; }
      results.innerHTML = items.map(function(item){
        var thumb = item.thumb
          ? '<span class="gpo-search-result-thumb"><img src="' + escapeHtml(item.thumb) + '" alt="' + escapeHtml(item.title) + '" loading="lazy" /></span>'
          : '<span class="gpo-search-result-thumb"></span>';
        var subtitle = item.subtitle || item.brand || '';
        var promoBadge = item.promoBadge ? '<span class="gpo-search-result-badge">' + escapeHtml(item.promoBadge) + '</span>' : '';
        var neoBadge = item.neopatentati ? '<span class="gpo-search-result-badge gpo-search-result-badge--success">Neopatentati</span>' : '';
        var priceMarkup = '<span class="gpo-search-result-price">' + escapeHtml(item.price || '') + '</span>';
        if (item.originalPrice && item.originalPrice !== item.price) {
          priceMarkup = '<span class="gpo-search-result-price-stack"><small>' + escapeHtml(item.originalPrice) + '</small><strong>' + escapeHtml(item.price || '') + '</strong></span>';
        }
        var promoText = item.promoText ? '<small class="gpo-search-result-promo">' + escapeHtml(item.promoText) + '</small>' : '';
        return '<a class="gpo-search-result" href="'+ escapeHtml(item.url) +'">' +
          thumb +
          '<span class="gpo-search-result-main">' + promoBadge + neoBadge + '<strong>'+ escapeHtml(item.title) +'</strong><small>'+ escapeHtml(subtitle) +'</small>' + promoText + '</span>' +
          priceMarkup +
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
    if (!shell || shell.dataset.brandBound === '1') return;
    var carousel = qs('.gpo-brand-carousel', shell);
    var viewport = qs('.gpo-brand-viewport', shell);
    var track = qs('.gpo-brand-track', shell);
    var runs = qsa('.gpo-brand-run', track);
    var autoplay = carousel && carousel.dataset.autoplay === 'yes';
    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var runWidth = 0;
    var rafId = 0;
    var lastFrame = 0;
    var resumeTimer = 0;
    var pauseUntil = 0;
    var hoverPaused = false;
    var focusPaused = false;
    var dragging = false;
    var dragMoved = false;
    var dragStartX = 0;
    var dragStartScroll = 0;
    var activePointerId = null;
    var suppressClick = false;
    var speed = carousel ? parseInt(carousel.dataset.speed || '900', 10) : 900;
    var pxPerSecond = Math.max(14, Math.min(42, 32000 / Math.max(speed, 260)));

    if (!carousel || !viewport || !track || !runs.length) {
      return;
    }

    shell.dataset.brandBound = '1';
    carousel.classList.add('is-enhanced');

    function measure() {
      runWidth = runs[0] ? runs[0].scrollWidth : 0;

      if (!runWidth) {
        return;
      }

      if (autoplay && runs.length > 1 && viewport.scrollLeft < runWidth * 0.2) {
        viewport.scrollLeft = runWidth;
      }
    }

    function normalizeLoopPosition() {
      var maxScroll;

      if (!autoplay || runs.length < 2 || !runWidth) {
        return;
      }

      maxScroll = (runWidth * 2) - viewport.clientWidth - 2;

      if (viewport.scrollLeft <= 1) {
        viewport.scrollLeft += runWidth;
      } else if (viewport.scrollLeft >= maxScroll) {
        viewport.scrollLeft -= runWidth;
      }
    }

    function isPaused(now) {
      return prefersReducedMotion || hoverPaused || focusPaused || dragging || now < pauseUntil;
    }

    function stopLoop() {
      if (rafId) {
        window.cancelAnimationFrame(rafId);
        rafId = 0;
      }
      lastFrame = 0;
    }

    function frame(timestamp) {
      var delta;

      if (!rafId) {
        return;
      }

      if (!lastFrame) {
        lastFrame = timestamp;
      }

      delta = timestamp - lastFrame;
      lastFrame = timestamp;

      if (autoplay && runs.length > 1 && !isPaused(timestamp)) {
        viewport.scrollLeft += (pxPerSecond * delta) / 1000;
        normalizeLoopPosition();
      }

      rafId = window.requestAnimationFrame(frame);
    }

    function startLoop() {
      stopLoop();
      measure();
      if (!autoplay || prefersReducedMotion) {
        return;
      }
      rafId = window.requestAnimationFrame(frame);
    }

    function queueResume(delay) {
      window.clearTimeout(resumeTimer);
      if (!autoplay || prefersReducedMotion) {
        return;
      }
      pauseUntil = performance.now() + delay;
      resumeTimer = window.setTimeout(function () {
        pauseUntil = 0;
      }, delay);
    }

    function beginDrag(event) {
      if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
      }

      dragging = true;
      dragMoved = false;
      activePointerId = event.pointerId;
      dragStartX = event.clientX;
      dragStartScroll = viewport.scrollLeft;
      viewport.classList.add('is-dragging');

      if (viewport.setPointerCapture) {
        viewport.setPointerCapture(event.pointerId);
      }

      queueResume(1600);
    }

    function moveDrag(event) {
      var deltaX;

      if (!dragging || activePointerId !== event.pointerId) {
        return;
      }

      deltaX = event.clientX - dragStartX;

      if (Math.abs(deltaX) > 4) {
        dragMoved = true;
        suppressClick = true;
      }

      viewport.scrollLeft = dragStartScroll - deltaX;
      normalizeLoopPosition();

      if (dragMoved) {
        event.preventDefault();
      }
    }

    function endDrag(event) {
      if (!dragging || (event && activePointerId !== event.pointerId)) {
        return;
      }

      dragging = false;
      activePointerId = null;
      viewport.classList.remove('is-dragging');
      normalizeLoopPosition();
      queueResume(1200);

      if (dragMoved) {
        window.setTimeout(function () {
          suppressClick = false;
        }, 220);
      }
    }

    viewport.addEventListener('pointerdown', beginDrag);
    viewport.addEventListener('pointermove', moveDrag);
    viewport.addEventListener('pointerup', endDrag);
    viewport.addEventListener('pointercancel', endDrag);
    viewport.addEventListener('lostpointercapture', endDrag);
    viewport.addEventListener('scroll', function () {
      normalizeLoopPosition();
      queueResume(900);
    }, { passive: true });

    viewport.addEventListener('mouseenter', function () {
      hoverPaused = true;
    });
    viewport.addEventListener('mouseleave', function () {
      hoverPaused = false;
      queueResume(220);
    });
    viewport.addEventListener('focusin', function () {
      focusPaused = true;
    });
    viewport.addEventListener('focusout', function () {
      focusPaused = false;
      queueResume(220);
    });
    viewport.addEventListener('click', function (event) {
      if (!suppressClick) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      suppressClick = false;
    }, true);

    window.addEventListener('resize', function () {
      measure();
      normalizeLoopPosition();
    });

    measure();
    normalizeLoopPosition();
    startLoop();
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
  if (window.jQuery && window.elementorFrontend && window.elementorFrontend.hooks) {
    window.jQuery(window).on('elementor/frontend/init', function () {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function () {
        window.setTimeout(boot, 40);
      });
    });
  }
})();
