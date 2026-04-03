document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-gpo-carousel="1"]').forEach(function (carousel) {
    const track = carousel.querySelector('.gpo-carousel-track');
    const prev = carousel.parentElement.querySelector('.gpo-carousel-prev') || carousel.querySelector('.gpo-carousel-prev');
    const next = carousel.parentElement.querySelector('.gpo-carousel-next') || carousel.querySelector('.gpo-carousel-next');
    const dotsWrap = carousel.querySelector('.gpo-carousel-dots');
    const slides = Array.from(carousel.querySelectorAll('.gpo-carousel-slide'));
    const autoplay = carousel.dataset.autoplay === 'yes';
    const interval = parseInt(carousel.dataset.interval || '5000', 10);

    if (!track || !slides.length) return;

    let currentIndex = 0;
    let timer = null;
    let startX = 0;

    function slideWidth() {
      const first = slides[0];
      return first ? first.getBoundingClientRect().width + 18 : 320;
    }

    function goTo(index) {
      currentIndex = Math.max(0, Math.min(index, slides.length - 1));
      track.scrollTo({ left: currentIndex * slideWidth(), behavior: 'smooth' });
      updateDots();
    }

    function updateDots() {
      if (!dotsWrap) return;
      dotsWrap.querySelectorAll('.gpo-carousel-dot').forEach(function (dot, idx) {
        dot.classList.toggle('is-active', idx === currentIndex);
      });
    }

    function buildDots() {
      if (!dotsWrap || slides.length <= 1) return;
      dotsWrap.innerHTML = '';
      slides.forEach(function (_, idx) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'gpo-carousel-dot' + (idx === 0 ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Vai alla slide ' + (idx + 1));
        dot.addEventListener('click', function () { goTo(idx); restart(); });
        dotsWrap.appendChild(dot);
      });
    }

    function nextSlide() {
      goTo(currentIndex >= slides.length - 1 ? 0 : currentIndex + 1);
    }

    function prevSlide() {
      goTo(currentIndex <= 0 ? slides.length - 1 : currentIndex - 1);
    }

    function start() {
      if (!autoplay || slides.length <= 1) return;
      timer = window.setInterval(nextSlide, interval);
    }

    function stop() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    function restart() {
      stop();
      start();
    }

    if (prev) prev.addEventListener('click', function () { prevSlide(); restart(); });
    if (next) next.addEventListener('click', function () { nextSlide(); restart(); });

    track.addEventListener('pointerdown', function (event) { startX = event.clientX; stop(); });
    track.addEventListener('pointerup', function (event) {
      const delta = event.clientX - startX;
      if (Math.abs(delta) > 50) {
        if (delta < 0) nextSlide(); else prevSlide();
      }
      restart();
    });

    track.addEventListener('mouseenter', stop);
    track.addEventListener('mouseleave', start);

    buildDots();
    start();
  });

  const sort = document.getElementById('gpo-sort');
  if (sort) {
    sort.addEventListener('change', function () {
      const form = document.getElementById('gpo-filter-form');
      if (form) form.submit();
    });
  }
});
