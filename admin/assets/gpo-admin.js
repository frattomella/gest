document.addEventListener('DOMContentLoaded', function () {
    var apiShell = document.querySelector('.gpo-api-shell');
    var brandModeField = document.querySelector('.gpo-brand-mode-field select');
    var brandPicker = document.querySelector('.gpo-brand-picker');

    function updateModeState() {
        if (!apiShell) {
            return;
        }
        var checkedMode = apiShell.querySelector('input[name="gpo_settings[api][connection_mode]"]:checked');
        var manualFormat = apiShell.querySelector('select[name="gpo_settings[api][manual_format]"]');
        var mode = checkedMode ? checkedMode.value : 'gestpark_auto';
        var format = manualFormat ? manualFormat.value : 'gestpark';

        apiShell.dataset.connectionMode = mode;
        apiShell.dataset.manualFormat = format;

        apiShell.querySelectorAll('.gpo-mode-card').forEach(function (card) {
            var input = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-active', !!input && input.checked);
        });
    }

    function updateBrandMode() {
        if (!brandPicker || !brandModeField) {
            return;
        }

        brandPicker.dataset.brandMode = brandModeField.value || 'inventory';
    }

    function updatePromoTargets(scope) {
        (scope || document).querySelectorAll('.gpo-promo-rule').forEach(function (rule) {
            var select = rule.querySelector('select[name*="[target_type]"]');
            if (!select) {
                return;
            }

            rule.dataset.ruleTarget = select.value || 'vehicle';
        });
    }

    function parseSelected(picker) {
        try {
            return JSON.parse(picker.dataset.selected || '[]').map(function (id) {
                return String(id);
            });
        } catch (error) {
            return [];
        }
    }

    function renderVehiclePicker(picker) {
        if (!picker) {
            return;
        }

        var multiple = picker.dataset.multiple === '1';
        var inputName = picker.dataset.inputName || '';
        var selected = parseSelected(picker);
        var search = picker.querySelector('.gpo-vehicle-picker__search');
        var count = picker.querySelector('.gpo-vehicle-picker__count');
        var selectedWrap = picker.querySelector('.gpo-vehicle-picker__selected');
        var inputsWrap = picker.querySelector('.gpo-vehicle-picker__inputs');
        var options = Array.prototype.slice.call(picker.querySelectorAll('.gpo-vehicle-picker__option'));

        function updateHiddenInputs() {
            inputsWrap.innerHTML = '';
            if (multiple) {
                selected.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = inputName;
                    input.value = id;
                    inputsWrap.appendChild(input);
                });
            } else {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = selected[0] || '';
                inputsWrap.appendChild(input);
            }
        }

        function updateSelectedChips() {
            var emptyLabel = picker.dataset.emptyLabel || 'Nessun veicolo selezionato';
            selectedWrap.innerHTML = '';

            if (selected.length === 0) {
                var empty = document.createElement('span');
                empty.className = 'gpo-vehicle-picker__empty';
                empty.textContent = emptyLabel;
                selectedWrap.appendChild(empty);
                count.textContent = emptyLabel;
                return;
            }

            count.textContent = multiple ? selected.length + ' selezionati' : '1 selezionato';

            selected.forEach(function (id) {
                var option = picker.querySelector('.gpo-vehicle-picker__option[data-id="' + id + '"]');
                if (!option) {
                    return;
                }

                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'gpo-chip gpo-chip--interactive';
                chip.dataset.id = id;
                chip.innerHTML = '<span>' + option.dataset.label + '</span><strong>&times;</strong>';
                selectedWrap.appendChild(chip);
            });
        }

        function updateOptions() {
            var needle = (search && search.value ? search.value : '').toLowerCase();
            options.forEach(function (option) {
                var id = String(option.dataset.id || '');
                var haystack = String(option.dataset.search || '').toLowerCase();
                var isSelected = selected.indexOf(id) !== -1;
                var matches = needle === '' || haystack.indexOf(needle) !== -1;

                option.hidden = !matches;
                option.classList.toggle('is-selected', isSelected);
            });
        }

        function commit() {
            picker.dataset.selected = JSON.stringify(selected);
            updateHiddenInputs();
            updateSelectedChips();
            updateOptions();
        }

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                var id = String(option.dataset.id || '');
                var index = selected.indexOf(id);

                if (multiple) {
                    if (index === -1) {
                        selected.push(id);
                    } else {
                        selected.splice(index, 1);
                    }
                } else {
                    selected = index === -1 ? [id] : [];
                }

                commit();
            });
        });

        selectedWrap.addEventListener('click', function (event) {
            var chip = event.target.closest('.gpo-chip--interactive');
            if (!chip) {
                return;
            }

            var id = String(chip.dataset.id || '');
            selected = selected.filter(function (item) {
                return item !== id;
            });
            commit();
        });

        if (search) {
            search.addEventListener('input', updateOptions);
        }

        commit();
    }

    function initVehiclePickers(scope) {
        (scope || document).querySelectorAll('.gpo-vehicle-picker').forEach(function (picker) {
            if (picker.dataset.ready === '1') {
                return;
            }

            picker.dataset.ready = '1';
            renderVehiclePicker(picker);
        });
    }

    function initCardToggles(scope) {
        (scope || document).querySelectorAll('.gpo-promo-card').forEach(function (card, index) {
            if (card.dataset.ready === '1') {
                return;
            }

            card.dataset.ready = '1';
            if (index > 0) {
                card.classList.add('is-collapsed');
            }
        });
    }

    function initRepeatables() {
        document.addEventListener('click', function (event) {
            var addButton = event.target.closest('.gpo-repeatable__add');
            if (addButton) {
                event.preventDefault();
                var target = document.querySelector(addButton.dataset.target || '');
                var template = document.querySelector(addButton.dataset.template || '');
                if (!target || !template) {
                    return;
                }

                var nextIndex = parseInt(target.dataset.nextIndex || '0', 10);
                var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                target.dataset.nextIndex = String(nextIndex + 1);

                target.insertAdjacentHTML('beforeend', html);
                target.querySelectorAll('.gpo-empty-state').forEach(function (empty) {
                    empty.remove();
                });
                initVehiclePickers(target);
                initCardToggles(target);
                updatePromoTargets(target);
                return;
            }

            var removeButton = event.target.closest('.gpo-row-remove');
            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('.gpo-schedule-row, .gpo-promo-card');
                if (row) {
                    var parent = row.parentElement;
                    row.remove();
                    if (parent && !parent.querySelector('.gpo-schedule-row, .gpo-promo-card')) {
                        parent.insertAdjacentHTML('beforeend', '<div class="gpo-empty-state"><strong>Nessun elemento configurato</strong><span>Puoi aggiungere un nuovo elemento quando vuoi.</span></div>');
                    }
                }
                return;
            }

            var cardToggle = event.target.closest('.gpo-card-toggle');
            if (cardToggle) {
                event.preventDefault();
                var card = cardToggle.closest('.gpo-promo-card');
                if (card) {
                    card.classList.toggle('is-collapsed');
                }
            }
        });
    }

    function initShowcaseToggle() {
        document.addEventListener('change', function (event) {
            var field = event.target.closest('.gpo-showcase-toggle');
            if (!field || !window.gpoAdmin || !window.fetch) {
                return;
            }

            var label = field.closest('.gpo-list-toggle');
            if (label) {
                label.classList.add('is-loading');
            }

            var body = new URLSearchParams();
            body.append('action', 'gpo_toggle_showcase_vehicle');
            body.append('nonce', gpoAdmin.nonce);
            body.append('post_id', field.dataset.postId || '');
            body.append('featured', field.checked ? '1' : '0');

            fetch(gpoAdmin.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error((payload && payload.data && payload.data.message) || gpoAdmin.strings.showcaseError);
                }

                if (label) {
                    label.classList.toggle('is-active', !!payload.data.featured);
                    var text = label.querySelector('.gpo-list-toggle__text');
                    if (text) {
                        text.textContent = payload.data.label || (payload.data.featured ? gpoAdmin.strings.showcaseOn : gpoAdmin.strings.showcaseOff);
                    }
                }
            }).catch(function () {
                field.checked = !field.checked;
                window.alert(gpoAdmin.strings.showcaseError);
            }).finally(function () {
                if (label) {
                    label.classList.remove('is-loading');
                }
            });
        });
    }

    if (apiShell) {
        apiShell.addEventListener('change', updateModeState);
        updateModeState();
    }
    if (brandModeField) {
        brandModeField.addEventListener('change', updateBrandMode);
        updateBrandMode();
    }

    document.addEventListener('change', function (event) {
        if (event.target && String(event.target.name || '').indexOf('[target_type]') !== -1) {
            updatePromoTargets();
        }
    });

    initVehiclePickers();
    initCardToggles();
    initRepeatables();
    initShowcaseToggle();
    updatePromoTargets();
});
