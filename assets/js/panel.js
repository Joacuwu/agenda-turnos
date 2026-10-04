$(function () {
    var colores = { reservado: 'primary', asistio: 'success', ausente: 'warning', cancelado: 'secondary' };

    // Cambio de estado por Ajax; si falla, el select vuelve al estado anterior.
    $('.js-estado').on('change', function () {
        var $select = $(this);
        var anterior = $select.data('anterior');
        var nuevo = $select.val();
        $select.prop('disabled', true);

        $.post('api/estado.php', { id: $select.data('id'), estado: nuevo, csrf: $('#csrf').val() }, null, 'json')
            .done(function () {
                $select.removeClass('border-' + colores[anterior]).addClass('border-' + colores[nuevo]).data('anterior', nuevo);
            })
            .fail(function (xhr) {
                if (xhr.status === 401) {
                    window.location = 'login.php';
                    return;
                }
                $select.val(anterior);
                window.alert((xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo cambiar el estado.');
            })
            .always(function () {
                $select.prop('disabled', false);
            });
    });
});
