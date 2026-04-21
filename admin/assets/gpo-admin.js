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

    function updatePromoTargets() {
        document.querySelectorAll('.gpo-promo-rule').forEach(function (rule) {
            var select = rule.querySelector('select[name*="[target_type]"]');
            if (!select) {
                return;
            }

            rule.dataset.ruleTarget = select.value || 'vehicle';
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
    updatePromoTargets();
});
