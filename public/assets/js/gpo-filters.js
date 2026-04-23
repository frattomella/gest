(function () {
  function initFilterPanel(panel) {
    var toggle;
    var body;
    var userToggled;
    var desktopAccordion;

    if (!panel || panel.dataset.bound === '1') {
      return;
    }

    toggle = panel.querySelector('.gpo-filter-toggle');
    body = panel.querySelector('.gpo-filter-panel__body');

    if (!toggle || !body) {
      return;
    }

    panel.dataset.bound = '1';
    userToggled = false;
    desktopAccordion = panel.classList.contains('gpo-filter-panel--marketplace-sidebar');

    function isCompact() {
      return window.matchMedia('(max-width: 1180px)').matches;
    }

    function setOpen(isOpen) {
      panel.classList.toggle('is-open', !!isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      body.hidden = !isOpen;
    }

    function syncMode() {
      if (isCompact() || desktopAccordion) {
        panel.classList.add('is-collapsible');
        if (!userToggled) {
          setOpen(desktopAccordion && !isCompact());
        }
        return;
      }

      panel.classList.remove('is-collapsible');
      setOpen(true);
    }

    toggle.addEventListener('click', function () {
      if (!isCompact() && !desktopAccordion) {
        return;
      }

      userToggled = true;
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    window.addEventListener('resize', syncMode);
    syncMode();
  }

  function initFilterSelects() {
    var form = document.getElementById('gpo-filter-form');

    if (!form) {
      return;
    }

    function submitForm() {
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
      }

      form.submit();
    }

    Array.prototype.slice.call(form.querySelectorAll('.gpo-filter-control__field, .gpo-filter-option__input, #gpo-sort, #gpo-limit')).forEach(function (field) {
      if (!field || field.dataset.bound === '1') {
        return;
      }

      field.dataset.bound = '1';
      field.addEventListener('change', function () {
        submitForm();
      });
    });
  }

  function boot() {
    Array.prototype.slice.call(document.querySelectorAll('[data-gpo-filter-panel="1"]')).forEach(initFilterPanel);
    initFilterSelects();
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
