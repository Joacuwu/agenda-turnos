$(function () {
    // Formularios que piden confirmación antes de enviarse.
    $('form.js-confirmar').on('submit', function (ev) {
        if (!window.confirm($(this).data('mensaje'))) {
            ev.preventDefault();
        }
    });
});
