(function () {
  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function safeJsonParse(value) {
    if (!value) {
      return [];
    }

    try {
      return JSON.parse(value);
    } catch (error) {
      return [];
    }
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
    var visibleThumbLastIndex = -1;

    if (!thumbs.length || !main || !mainImage) {
      return;
    }

    var items = safeJsonParse(gallery.getAttribute('data-gpo-gallery-items')).filter(function (item) {
      return item && item.large;
    });

    if (!items.length) {
      items = thumbs.map(function (thumb) {
        return {
          large: thumb.getAttribute('data-large-src') || '',
          full: thumb.getAttribute('data-full-src') || thumb.getAttribute('data-large-src') || '',
          alt: thumb.getAttribute('data-alt') || '',
          caption: thumb.getAttribute('data-caption') || ''
        };
      });
    }

    visibleThumbLastIndex = thumbs.length ? parseInt(thumbs[thumbs.length - 1].getAttribute('data-index') || (thumbs.length - 1), 10) : -1;

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
        var thumbTargetIndex = parseInt(thumb.getAttribute('data-index') || thumbIndex, 10);
        var isOverflowThumb = thumbIndex === (thumbs.length - 1) && visibleThumbLastIndex >= 0 && items.length > thumbs.length;
        var isActive = thumbTargetIndex === activeIndex || (isOverflowThumb && activeIndex >= visibleThumbLastIndex);
        thumb.classList.toggle('is-active', isActive);
        thumb.setAttribute('aria-current', isActive ? 'true' : 'false');
      });

      mainImage.src = item.large;
      mainImage.alt = item.alt;
      main.setAttribute('aria-label', 'Apri foto ' + (activeIndex + 1) + ' di ' + items.length);

      if (countLabel) {
        countLabel.textContent = (activeIndex + 1) + ' / ' + items.length;
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
        update(parseInt(thumb.getAttribute('data-index') || index, 10) || 0);
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

  function copyButtonLabelTarget(button) {
    if (!button) {
      return null;
    }

    return button.querySelector('span:last-child') || button.lastElementChild || button;
  }

  function initCopyButtons() {
    qsa('[data-gpo-copy-link]').forEach(function (button) {
      if (!button || button.dataset.bound === '1') {
        return;
      }

      button.dataset.bound = '1';
      button.addEventListener('click', function () {
        var url = button.getAttribute('data-gpo-copy-link') || window.location.href;
        var labelTarget = copyButtonLabelTarget(button);
        var originalText = button.dataset.originalText || (labelTarget ? labelTarget.textContent : button.textContent);
        var copiedLabel = button.getAttribute('data-gpo-copy-label') || 'Link copiato';

        button.dataset.originalText = originalText;

        function markCopied() {
          button.classList.add('is-copied');
          if (labelTarget) {
            labelTarget.textContent = copiedLabel;
          }
          window.setTimeout(function () {
            button.classList.remove('is-copied');
            if (labelTarget) {
              labelTarget.textContent = originalText;
            }
          }, 1800);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(markCopied);
          return;
        }

        var input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        markCopied();
      });
    });
  }

  function initShareModals() {
    qsa('[data-gpo-share-open]').forEach(function (trigger) {
      if (!trigger || trigger.dataset.shareBound === '1') {
        return;
      }

      trigger.dataset.shareBound = '1';
      trigger.addEventListener('click', function () {
        var modalId = trigger.getAttribute('data-gpo-share-open');
        var modal = modalId ? document.getElementById(modalId) : null;
        if (!modal) {
          return;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('gpo-share-modal-open');
        document.body.classList.add('gpo-share-modal-open');

        var firstAction = qs('.gpo-share-modal__action, [data-gpo-copy-link]', modal);
        if (firstAction) {
          firstAction.focus();
        } else {
          var dialog = qs('.gpo-share-modal__dialog', modal);
          if (dialog) {
            dialog.focus();
          }
        }
      });
    });

    qsa('[data-gpo-share-close]').forEach(function (button) {
      if (!button || button.dataset.shareCloseBound === '1') {
        return;
      }

      button.dataset.shareCloseBound = '1';
      button.addEventListener('click', function () {
        var modalId = button.getAttribute('data-gpo-share-close');
        var modal = modalId ? document.getElementById(modalId) : button.closest('.gpo-share-modal');
        if (!modal) {
          return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('gpo-share-modal-open');
        document.body.classList.remove('gpo-share-modal-open');
      });
    });

    qsa('[data-gpo-share-dismiss]').forEach(function (button) {
      if (!button || button.dataset.shareDismissBound === '1') {
        return;
      }

      button.dataset.shareDismissBound = '1';
      button.addEventListener('click', function () {
        var modal = button.closest('.gpo-share-modal');
        if (!modal) {
          return;
        }

        window.setTimeout(function () {
          modal.hidden = true;
          modal.setAttribute('aria-hidden', 'true');
          document.documentElement.classList.remove('gpo-share-modal-open');
          document.body.classList.remove('gpo-share-modal-open');
        }, 0);
      });
    });

    if (document.documentElement.dataset.gpoShareEscapeBound !== '1') {
      document.documentElement.dataset.gpoShareEscapeBound = '1';
      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
          return;
        }

        qsa('.gpo-share-modal').forEach(function (modal) {
          if (modal.hidden) {
            return;
          }

          modal.hidden = true;
          modal.setAttribute('aria-hidden', 'true');
        });

        document.documentElement.classList.remove('gpo-share-modal-open');
        document.body.classList.remove('gpo-share-modal-open');
      });
    }
  }

  function initPrintButtons() {
    qsa('[data-gpo-print-sheet]').forEach(function (button) {
      if (!button || button.dataset.printBound === '1') {
        return;
      }

      button.dataset.printBound = '1';
      button.addEventListener('click', function () {
        window.print();
      });
    });
  }

  function boot() {
    qsa('[data-gpo-gallery="1"]').forEach(initGallery);
    initCopyButtons();
    initShareModals();
    initPrintButtons();
  }

  document.addEventListener('DOMContentLoaded', boot);

  if (window.wp && window.wp.domReady) {
    window.wp.domReady(function () {
      window.setTimeout(boot, 80);
    });
  }
})();
