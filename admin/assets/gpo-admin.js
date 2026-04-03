document.addEventListener('DOMContentLoaded', function () {
    var apiShell = document.querySelector('.gpo-api-shell');
    if (!apiShell) {
        return;
    }

    function updateModeState() {
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

    apiShell.addEventListener('change', updateModeState);
    updateModeState();
});
