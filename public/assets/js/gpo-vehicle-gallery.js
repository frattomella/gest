(function () {
  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function bindSwipe(target, onPrev, onNext) {
    if (!target) {
      return;
    }

    var startX = 0;
    var pointerId = null;

    target.addEventListener('pointerdown', function (event) {
      pointerId = event.pointerId;
      startX = event.clientX;
    });

    target.addEventListener('pointerup', function (event) {
      if (pointerId !== event.pointerId) {
        return;
      }

      var delta = event.clientX - startX;
      pointerId = null;

      if (Math.abs(delta) < 48) {
        return;
      }

      if (delta > 0) {
        onPrev();
      } else {
        onNext();
      }
    });
  }

  function initGallery(gallery) {
    if (!gallery || gallery.dataset.bound === '1') {
      return;
    }

    gallery.dataset.bound = '1';

    var thumbs = qsa('.gpo-single-thumb', gallery);
    var main = qs('.gpo-single-main', gallery);
    var mainImage = qs('.gpo-single-main__image', gallery);
    var countLabel = qs('.gpo-single-gallery-count strong', gallery);
    var captionLabel = qs('.gpo-single-gallery-count small', gallery);
    var prev = qs('.gpo-single-stage__nav.prev', gallery);
    var next = qs('.gpo-single-stage__nav.next', gallery);
    var expand = qs('.gpo-single-gallery-expand', gallery);
    var lightbox = qs('.gpo-gallery-lightbox', gallery);
    var lightImage = qs('.gpo-gallery-lightbox__image', gallery);
    var lightCounter = qs('.gpo-gallery-lightbox__counter', gallery);
    var lightCaption = qs('.gpo-gallery-lightbox__caption span', gallery);
    var lightPrev = qs('.gpo-gallery-lightbox__nav.prev', gallery);
    var lightNext = qs('.gpo-gallery-lightbox__nav.next', gallery);
    var closeButtons = qsa('[data-gpo-gallery-close="1"]', gallery);
    var activeIndex = 0;
    var lastFocused = null;

    if (!thumbs.length || !main || !mainImage) {
      return;
    }

    var items = thumbs.map(function (thumb) {
      return {
        large: thumb.getAttribute('data-large-src') || '',
        full: thumb.getAttribute('data-full-src') || thumb.getAttribute('data-large-src') || '',
        alt: thumb.getAttribute('data-alt') || '',
        caption: thumb.getAttribute('data-caption') || ''
      };
    });

    function wrapIndex(index) {
      if (index < 0) {
        return items.length - 1;
      }

      if (index >= items.length) {
        return 0;
      }

      return index;
    }

    function update(index) {
      var item;

      activeIndex = wrapIndex(index);
      item = items[activeIndex];

      thumbs.forEach(function (thumb, thumbIndex) {
        var isActive = thumbIndex === activeIndex;
        thumb.classList.toggle('is-active', isActive);
        thumb.setAttribute('aria-current', isActive ? 'true' : 'false');
      });

      mainImage.src = item.large;
      mainImage.alt = item.alt;
      main.setAttribute('aria-label', 'Apri foto ' + (activeIndex + 1) + ' di ' + items.length);

      if (countLabel) {
        countLabel.textContent = (activeIndex + 1) + ' / ' + items.length;
      }

      if (captionLabel) {
        captionLabel.textContent = item.caption;
      }

      if (lightbox && !lightbox.hidden && lightImage) {
        lightImage.src = item.full || item.large;
        lightImage.alt = item.alt;
      }

      if (lightCounter) {
        lightCounter.textContent = (activeIndex + 1) + ' / ' + items.length;
      }

      if (lightCaption) {
        lightCaption.textContent = item.caption;
      }

      if (prev) {
        prev.disabled = items.length <= 1;
      }

      if (next) {
        next.disabled = items.length <= 1;
      }

      if (lightPrev) {
        lightPrev.disabled = items.length <= 1;
      }

      if (lightNext) {
        lightNext.disabled = items.length <= 1;
      }
    }

    function openLightbox() {
      if (!lightbox) {
        return;
      }

      lastFocused = document.activeElement;
      update(activeIndex);
      lightbox.hidden = false;
      lightbox.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('gpo-lightbox-open');
      document.body.classList.add('gpo-lightbox-open');

      var closeButton = qs('.gpo-gallery-lightbox__close', gallery);
      if (closeButton) {
        closeButton.focus();
      }
    }

    function closeLightbox() {
      if (!lightbox || lightbox.hidden) {
        return;
      }

      lightbox.hidden = true;
      lightbox.setAttribute('aria-hidden', 'true');
      document.documentElement.classList.remove('gpo-lightbox-open');
      document.body.classList.remove('gpo-lightbox-open');

      if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
      }
    }

    thumbs.forEach(function (thumb, index) {
      thumb.addEventListener('click', function () {
        update(index);
      });
    });

    if (prev) {
      prev.addEventListener('click', function () {
        update(activeIndex - 1);
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        update(activeIndex + 1);
      });
    }

    if (lightPrev) {
      lightPrev.addEventListener('click', function () {
        update(activeIndex - 1);
      });
    }

    if (lightNext) {
      lightNext.addEventListener('click', function () {
        update(activeIndex + 1);
      });
    }

    if (main) {
      main.addEventListener('click', openLightbox);
    }

    if (expand) {
      expand.addEventListener('click', openLightbox);
    }

    closeButtons.forEach(function (button) {
      button.addEventListener('click', closeLightbox);
    });

    bindSwipe(main, function () {
      update(activeIndex - 1);
    }, function () {
      update(activeIndex + 1);
    });

    bindSwipe(lightbox, function () {
      update(activeIndex - 1);
    }, function () {
      update(activeIndex + 1);
    });

    document.addEventListener('keydown', function (event) {
      if (!lightbox || lightbox.hidden) {
        return;
      }

      if (event.key === 'Escape') {
        event.preventDefault();
        closeLightbox();
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        update(activeIndex - 1);
      }

      if (event.key === 'ArrowRight') {
        event.preventDefault();
        update(activeIndex + 1);
      }
    });

    update(0);
  }

  function boot() {
    qsa('[data-gpo-gallery="1"]').forEach(initGallery);
  }

  document.addEventListener('DOMContentLoaded', boot);

  if (window.wp && window.wp.domReady) {
    window.wp.domReady(function () {
      window.setTimeout(boot, 80);
    });
  }
})();
