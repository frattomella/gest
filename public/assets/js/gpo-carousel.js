(function () {
  function initCarousel(carousel) {
    if (!carousel || carousel.dataset.bound === '1') {
      return;
    }

    var shell = carousel.closest('.gpo-carousel-shell') || carousel.parentElement;
    var track = carousel.querySelector('.gpo-carousel-track');
    var prev = (shell && shell.querySelector('.gpo-carousel-prev')) || carousel.querySelector('.gpo-carousel-prev');
    var next = (shell && shell.querySelector('.gpo-carousel-next')) || carousel.querySelector('.gpo-carousel-next');
    var dotsWrap = carousel.querySelector('.gpo-carousel-dots');
    var autoplay = carousel.dataset.autoplay === 'yes';
    var interval = parseInt(carousel.dataset.interval || '5000', 10);
    var currentIndex = 0;
    var currentPage = 0;
    var timer = null;
    var startX = 0;
    var pointerId = null;
    var scrollFrame = null;

    if (!track) {
      return;
    }

    carousel.dataset.bound = '1';

    function getSlides() {
      return Array.prototype.slice.call(track.querySelectorAll('.gpo-carousel-slide'));
    }

    function slideCount() {
      return getSlides().length;
    }

    function canLoop() {
      return carousel.dataset.loop === 'yes' && slideCount() > 1;
    }

    function slidePositions() {
      return getSlides().map(function (slide) {
        return slide.offsetLeft;
      });
    }

    function slideStep() {
      var slides = getSlides();
      var first = slides[0];
      var styles;

      if (!first) {
        return track.clientWidth;
      }

      styles = window.getComputedStyle(track);
      return first.getBoundingClientRect().width + parseFloat(styles.columnGap || styles.gap || '0');
    }

    function configuredVisiblePerPage() {
      var shellStyles = shell ? window.getComputedStyle(shell) : null;
      var carouselStyles = window.getComputedStyle(carousel);
      var raw = parseInt(
        (shellStyles && shellStyles.getPropertyValue('--gpo-carousel-items-per-page')) ||
        carouselStyles.getPropertyValue('--gpo-carousel-items-per-page') ||
        track.style.getPropertyValue('--gpo-carousel-items-per-page') ||
        '0',
        10
      );

      return Math.max(1, Math.min(4, raw || 0));
    }

    function visiblePerPage() {
      var configured = configuredVisiblePerPage();
      var step = slideStep();
      var visible = step > 0 ? Math.floor((track.clientWidth + 2) / step) : 1;

      if (window.matchMedia('(max-width: 720px)').matches) {
        return 1;
      }

      if (window.matchMedia('(max-width: 1180px)').matches) {
        return Math.min(configured || 2, 2);
      }

      return configured || Math.max(1, Math.min(4, visible || 1));
    }

    function pageCount() {
      return Math.max(1, Math.ceil(slideCount() / visiblePerPage()));
    }

    function nearestIndex() {
      var positions = slidePositions();
      var scrollLeft = track.scrollLeft;
      var bestIndex = 0;
      var bestDistance = Number.POSITIVE_INFINITY;

      positions.forEach(function (position, index) {
        var distance = Math.abs(position - scrollLeft);
        if (distance < bestDistance) {
          bestDistance = distance;
          bestIndex = index;
        }
      });

      return bestIndex;
    }

    function pageStartIndex(page) {
      return Math.min(page * visiblePerPage(), Math.max(0, slideCount() - 1));
    }

    function updateDots() {
      if (!dotsWrap) {
        return;
      }

      Array.prototype.slice.call(dotsWrap.querySelectorAll('.gpo-carousel-dot')).forEach(function (dot, index) {
        dot.classList.toggle('is-active', index === currentPage);
        dot.setAttribute('aria-current', index === currentPage ? 'true' : 'false');
      });
    }

    function updateControls() {
      var multiple = pageCount() > 1;

      if (prev) {
        prev.disabled = !multiple;
      }
      if (next) {
        next.disabled = !multiple;
      }
      if (dotsWrap) {
        dotsWrap.hidden = !multiple;
      }
    }

    function syncIndex() {
      currentIndex = nearestIndex();
      currentPage = Math.min(pageCount() - 1, Math.floor(currentIndex / visiblePerPage()));
      updateDots();
    }

    function goToPage(page, behavior) {
      var slides = getSlides();
      var positions;
      var startIndex;

      if (!slides.length) {
        return;
      }

      if (canLoop()) {
        if (page < 0) {
          page = pageCount() - 1;
        } else if (page >= pageCount()) {
          page = 0;
        }
      } else {
        page = Math.max(0, Math.min(page, pageCount() - 1));
      }

      currentPage = page;
      startIndex = pageStartIndex(currentPage);
      currentIndex = startIndex;
      positions = slidePositions();
      track.scrollTo({
        left: positions[startIndex] || 0,
        behavior: behavior || 'smooth'
      });

      updateDots();
    }

    function nextSlide() {
      goToPage(currentPage + 1, 'smooth');
    }

    function prevSlide() {
      goToPage(currentPage - 1, 'smooth');
    }

    function buildDots() {
      var pages = pageCount();

      if (!dotsWrap) {
        return;
      }

      dotsWrap.innerHTML = '';

      Array.from({ length: pages }).forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'gpo-carousel-dot' + (index === currentPage ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Vai alla pagina ' + (index + 1));
        dot.setAttribute('aria-current', index === currentPage ? 'true' : 'false');
        dot.addEventListener('click', function () {
          goToPage(index, 'smooth');
          restart();
        });
        dotsWrap.appendChild(dot);
      });
    }

    function stop() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    function start() {
      stop();

      if (!autoplay || slideCount() <= 1) {
        return;
      }

      timer = window.setInterval(nextSlide, interval);
    }

    function restart() {
      stop();
      start();
    }

    if (prev) {
      prev.addEventListener('click', function () {
        prevSlide();
        restart();
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        nextSlide();
        restart();
      });
    }

    track.addEventListener('pointerdown', function (event) {
      pointerId = event.pointerId;
      startX = event.clientX;
      stop();
    });

    track.addEventListener('pointerup', function (event) {
      var delta;

      if (pointerId !== event.pointerId) {
        return;
      }

      delta = event.clientX - startX;
      pointerId = null;

      if (Math.abs(delta) > 48) {
        if (delta < 0) {
          nextSlide();
        } else {
          prevSlide();
        }
      } else {
        syncIndex();
      }

      restart();
    });

    track.addEventListener('pointercancel', function () {
      pointerId = null;
      restart();
    });

    track.addEventListener('scroll', function () {
      if (scrollFrame) {
        window.cancelAnimationFrame(scrollFrame);
      }

      scrollFrame = window.requestAnimationFrame(syncIndex);
    }, { passive: true });

    track.addEventListener('mouseenter', stop);
    track.addEventListener('mouseleave', start);
    track.addEventListener('focusin', stop);
    track.addEventListener('focusout', start);

    window.addEventListener('resize', function () {
      buildDots();
      updateControls();
      goToPage(currentPage, 'auto');
    });

    buildDots();
    updateControls();
    goToPage(0, 'auto');
    start();
  }

  function initSort() {
    var sort = document.getElementById('gpo-sort');
    if (!sort || sort.dataset.bound === '1') {
      return;
    }

    sort.dataset.bound = '1';
    sort.addEventListener('change', function () {
      var form = document.getElementById('gpo-filter-form');
      if (form) {
        form.submit();
      }
    });
  }

  function boot() {
    Array.prototype.slice.call(document.querySelectorAll('[data-gpo-carousel="1"]')).forEach(initCarousel);
    initSort();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  if (window.wp && window.wp.domReady) {
    window.wp.domReady(function () {
      window.setTimeout(boot, 80);
    });
  }

  if (window.jQuery && window.elementorFrontend && window.elementorFrontend.hooks) {
    window.jQuery(window).on('elementor/frontend/init', function () {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function () {
        window.setTimeout(boot, 40);
      });
    });
  }
})();
