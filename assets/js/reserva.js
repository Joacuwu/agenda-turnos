$(function () {
    var $form = $('#formReserva');
    var $horarios = $('#horarios');
    var $boton = $('.js-reservar');
    var pedido = null;

    // Pide los horarios libres cada vez que cambia el servicio, el profesional o el día.
    function cargarHorarios() {
        elegirHorario(null);
        $horarios.html('<span class="text-body-secondary small"><span class="spinner-border spinner-border-sm"></span> Buscando horarios…</span>');
        if (pedido) {
            pedido.abort();
        }
        pedido = $.getJSON('api/horarios.php', {
            servicio_id: $form.find('[name=servicio_id]:checked').val(),
            profesional_id: $('#profesional').val(),
            fecha: $('#fecha').val()
        }).done(function (r) {
            $horarios.empty();
            if (!r.horarios.length) {
                $horarios.append($('<span class="text-body-secondary small">').text('No quedan horarios libres ese día. Probá con otra fecha.'));
                return;
            }
            var sinPreferencia = $('#profesional').val() === '0';
            $.each(r.horarios, function (_, h) {
                $('<button type="button" class="btn btn-outline-dark btn-sm js-hora">')
                    .text(h.hora + (sinPreferencia ? ' · ' + h.profesional : ''))
                    .data('horario', h)
                    .appendTo($horarios);
            });
        }).fail(function (xhr, estado) {
            if (estado !== 'abort') {
                $horarios.html('<span class="text-danger small">No se pudieron cargar los horarios. Recargá la página.</span>');
            }
        });
    }

    function elegirHorario(h) {
        $('#horaElegida').val(h ? h.hora : '');
        $('#profesionalElegido').val(h ? h.profesional_id : '');
        $boton.prop('disabled', !h).text(h ? 'Reservar ' + h.hora + ' con ' + h.profesional : 'Elegí un horario');
    }

    $horarios.on('click', '.js-hora', function () {
        $horarios.find('.js-hora').removeClass('active');
        elegirHorario($(this).addClass('active').data('horario'));
    });

    $form.on('change', '[name=servicio_id], #profesional, #fecha', cargarHorarios);

    $form.on('submit', function (ev) {
        ev.preventDefault();
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.js-error').text('');
        $('.js-error-general').addClass('d-none');
        $boton.prop('disabled', true);

        $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), dataType: 'json' })
            .done(function (r) {
                $form.addClass('d-none');
                $('.js-conf-codigo').text(r.codigo);
                $('.js-conf-detalle').text(r.detalle);
                $('#confirmacion').removeClass('d-none');
                window.scrollTo(0, 0);
            })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                var generales = [];
                $.each(r.errores || {}, function (campo, mensaje) {
                    var $error = $form.find('.js-error[data-campo="' + campo + '"]');
                    if ($error.length) {
                        $error.text(mensaje);
                        $form.find('[name="' + campo + '"]').addClass('is-invalid');
                    } else {
                        generales.push(mensaje);
                    }
                });
                if (r.error) {
                    generales.push(r.error);
                }
                if (generales.length) {
                    $('.js-error-general').text(generales.join(' ')).removeClass('d-none');
                }
                // Si el horario se ocupó mientras completaba los datos, se recargan los libres.
                if (r.errores && r.errores.hora) {
                    var mensaje = r.errores.hora;
                    cargarHorarios();
                    $form.find('.js-error[data-campo="hora"]').text(mensaje);
                } else {
                    $boton.prop('disabled', false);
                }
            });
    });

    cargarHorarios();
});
