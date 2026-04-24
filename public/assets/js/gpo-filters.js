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

  function initMultiFilter(control) {
    var picker;
    var valuesHost;
    var chipsHost;
    var inputName;

    if (!control || control.dataset.multiBound === '1') {
      return;
    }

    picker = control.querySelector('[data-gpo-filter-picker]');
    valuesHost = control.querySelector('[data-gpo-filter-values]');
    chipsHost = control.querySelector('[data-gpo-filter-chips]');

    if (!picker || !valuesHost || !chipsHost) {
      return;
    }

    control.dataset.multiBound = '1';
    inputName = picker.getAttribute('data-gpo-filter-picker');

    function currentValues() {
      return Array.prototype.slice.call(valuesHost.querySelectorAll('input[name]')).map(function (input) {
        return input.value;
      });
    }

    function findValueInput(value) {
      return Array.prototype.slice.call(valuesHost.querySelectorAll('input[data-gpo-filter-value]')).find(function (input) {
        return input.getAttribute('data-gpo-filter-value') === value;
      }) || null;
    }

    function updatePickerOptions() {
      var selected = currentValues();

      Array.prototype.slice.call(picker.options).forEach(function (option) {
        if (!option.value) {
          return;
        }

        option.disabled = selected.indexOf(option.value) !== -1;
      });
    }

    function renderChips() {
      var selected = currentValues();

      chipsHost.innerHTML = '';

      if (!selected.length) {
        chipsHost.hidden = true;
        updatePickerOptions();
        return;
      }

      selected.forEach(function (value) {
        var chip = document.createElement('button');
        var text = document.createElement('span');
        var remove = document.createElement('span');

        chip.type = 'button';
        chip.className = 'gpo-filter-selected-chip';
        chip.setAttribute('data-gpo-filter-chip-remove', inputName);
        chip.setAttribute('data-value', value);

        text.className = 'gpo-filter-selected-chip__text';
        text.textContent = value;

        remove.className = 'gpo-filter-selected-chip__remove';
        remove.setAttribute('aria-hidden', 'true');
        remove.textContent = '×';

        chip.appendChild(text);
        chip.appendChild(remove);
        chipsHost.appendChild(chip);
      });

      chipsHost.hidden = false;
      updatePickerOptions();
    }

    picker.addEventListener('change', function () {
      var value = picker.value;
      var input;

      if (!value) {
        return;
      }

      if (currentValues().indexOf(value) === -1) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName + '[]';
        input.value = value;
        input.setAttribute('data-gpo-filter-value', value);
        valuesHost.appendChild(input);
      }

      picker.value = '';
      renderChips();
    });

    chipsHost.addEventListener('click', function (event) {
      var chip = event.target.closest('[data-gpo-filter-chip-remove]');
      var value;
      var targetInput;

      if (!chip) {
        return;
      }

      value = chip.getAttribute('data-value');
      targetInput = findValueInput(value);
      if (targetInput) {
        targetInput.remove();
      }

      renderChips();
    });

    renderChips();
  }

  function initFilterSelects() {
    var form = document.getElementById('gpo-filter-form');

    if (!form) {
      return;
    }

    Array.prototype.slice.call(form.querySelectorAll('[data-gpo-filter-multi]')).forEach(initMultiFilter);
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
