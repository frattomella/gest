document.addEventListener('DOMContentLoaded', function () {
  function initCarousel(carousel) {
    if (!carousel || carousel.dataset.bound === '1') {
      return;
    }

    carousel.dataset.bound = '1';

    var shell = carousel.closest('.gpo-carousel-shell') || carousel.parentElement;
    var track = carousel.querySelector('.gpo-carousel-track');
    var prev = (shell && shell.querySelector('.gpo-carousel-prev')) || carousel.querySelector('.gpo-carousel-prev');
    var next = (shell && shell.querySelector('.gpo-carousel-next')) || carousel.querySelector('.gpo-carousel-next');
    var dotsWrap = carousel.querySelector('.gpo-carousel-dots');
    var slides = Array.prototype.slice.call(carousel.querySelectorAll('.gpo-carousel-slide'));
    var autoplay = carousel.dataset.autoplay === 'yes';
    var interval = parseInt(carousel.dataset.interval || '5000', 10);
    var loop = carousel.dataset.loop === 'yes' && slides.length > 1;
    var currentIndex = 0;
    var timer = null;
    var startX = 0;
    var pointerId = null;
    var scrollFrame = null;

    if (!track || !slides.length) {
      return;
    }

    function slidePositions() {
      return slides.map(function (slide) {
        return slide.offsetLeft;
      });
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

    function updateDots() {
      if (!dotsWrap) {
        return;
      }

      dotsWrap.querySelectorAll('.gpo-carousel-dot').forEach(function (dot, index) {
        dot.classList.toggle('is-active', index === currentIndex);
      });
    }

    function syncIndex() {
      currentIndex = nearestIndex();
      updateDots();
    }

    function goTo(index, behavior) {
      var positions = slidePositions();

      if (!positions.length) {
        return;
      }

      if (loop) {
        if (index < 0) {
          index = slides.length - 1;
        } else if (index >= slides.length) {
          index = 0;
        }
      } else {
        index = Math.max(0, Math.min(index, slides.length - 1));
      }

      currentIndex = index;
      track.scrollTo({
        left: positions[currentIndex] || 0,
        behavior: behavior || 'smooth'
      });
      updateDots();
    }

    function nextSlide() {
      goTo(currentIndex + 1, 'smooth');
    }

    function prevSlide() {
      goTo(currentIndex - 1, 'smooth');
    }

    function buildDots() {
      if (!dotsWrap || slides.length <= 1) {
        return;
      }

      dotsWrap.innerHTML = '';
      slides.forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'gpo-carousel-dot' + (index === currentIndex ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Vai alla slide ' + (index + 1));
        dot.addEventListener('click', function () {
          goTo(index, 'smooth');
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
      if (!autoplay || slides.length <= 1) {
        return;
      }

      timer = window.setInterval(nextSlide, interval);
    }

    function restart() {
      stop();
      start();
    }

    if (slides.length <= 1) {
      if (prev) {
        prev.disabled = true;
      }
      if (next) {
        next.disabled = true;
      }
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
      goTo(currentIndex, 'auto');
    });

    buildDots();
    goTo(0, 'auto');
    start();
  }

  document.querySelectorAll('[data-gpo-carousel="1"]').forEach(initCarousel);

  var sort = document.getElementById('gpo-sort');
  if (sort) {
    sort.addEventListener('change', function () {
      var form = document.getElementById('gpo-filter-form');
      if (form) {
        form.submit();
      }
    });
  }
});
