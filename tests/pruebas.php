<?php
/**
 * Pruebas de la agenda, sin dependencias. Usa una base SQLite en memoria y una fecha fija
 * (el próximo lunes) para que los resultados no dependan del día en que se corren.
 * Ejecutar desde la carpeta del proyecto:  php tests/pruebas.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('TURNOS_DB_DSN=sqlite::memory:');
$_SESSION = array();
require dirname(dirname(__FILE__)) . '/includes/turnos.php';
crear_base_demo(db(), false);

$ok = 0;
$fallas = 0;
function probar($descripcion, $condicion)
{
    global $ok, $fallas;
    if ($condicion) {
        $ok++;
        echo "  OK    $descripcion\n";
    } else {
        $fallas++;
        echo "  FALLA $descripcion\n";
    }
}

function horas($lista)
{
    $h = array();
    foreach ($lista as $x) {
        $h[] = $x['hora'];
    }
    return $h;
}

$lunes = date('Y-m-d', strtotime('next monday'));
$martes = date('Y-m-d', strtotime($lunes . ' +1 day'));
$a_las_8 = strtotime($lunes . ' 08:00');
// Servicios: 1 Corte (30'), 2 Corte y barba (45'), 3 Barba (30'), 4 Color (90'). Profesionales: 1 Lucas, 2 Martina.

echo "Horarios libres\n";
probar('Lucas el lunes tiene 16 horarios de corte (9 a 13 y 15 a 19)', count(horarios_disponibles(1, 1, $lunes, $a_las_8)) === 16);
probar('Martina no trabaja los lunes', horarios_disponibles(1, 2, $lunes, $a_las_8) === array());
$color = horas(horarios_disponibles(4, 1, $lunes, $a_las_8));
probar('Color (90 min) solo empieza si termina dentro del bloque: 12 horarios, último 17:30',
    count($color) === 12 && end($color) === '17:30' && !in_array('12:00', $color, true));
probar('con la anticipación de 1 hora, a las 10:10 el primer horario es 11:30',
    current(horas(horarios_disponibles(1, 1, $lunes, strtotime($lunes . ' 10:10')))) === '11:30');
$martes_libres = horarios_disponibles(1, 0, $martes, $a_las_8);
$por_hora = array();
foreach ($martes_libres as $h) {
    $por_hora[$h['hora']] = $h['profesional'];
}
probar('"sin preferencia" el martes cubre de 9:00 a 18:30 sin huecos (20 horarios)', count($martes_libres) === 20);
probar('"sin preferencia" asigna a Martina el horario de las 13:00, cuando Lucas no atiende', $por_hora['13:00'] === 'Martina');
probar('fecha pasada o a más de 30 días no tiene horarios',
    horarios_disponibles(1, 1, date('Y-m-d', strtotime($lunes . ' -7 days')), $a_las_8) === array()
    && horarios_disponibles(1, 1, date('Y-m-d', strtotime($lunes . ' +31 days')), $a_las_8) === array());

echo "Reservas\n";
$datos = array('servicio_id' => '1', 'profesional_id' => '1', 'fecha' => $lunes, 'hora' => '10:00',
               'cliente' => 'Pablo Medina', 'telefono' => '11 5555-1234', 'email' => '');
$r = reservar_turno($datos, $a_las_8);
probar('reserva válida devuelve un código de 6 caracteres', $r['ok'] && strlen($r['codigo']) === 6);
$codigo = $r['codigo'];

$r2 = reservar_turno($datos, $a_las_8);
probar('el mismo horario no se puede reservar dos veces', !$r2['ok'] && isset($r2['errores']['hora']));
probar('el horario reservado desaparece de los libres', !in_array('10:00', horas(horarios_disponibles(1, 1, $lunes, $a_las_8)), true));
$barba = horas(horarios_disponibles(2, 1, $lunes, $a_las_8));
probar('un servicio de 45 min a las 9:30 se superpone con el turno de las 10:00 y no se ofrece',
    in_array('09:00', $barba, true) && !in_array('09:30', $barba, true) && in_array('10:30', $barba, true));

$r = reservar_turno(array_merge($datos, array('profesional_id' => '2', 'hora' => '11:00')), $a_las_8);
probar('no se puede reservar con Martina un lunes', !$r['ok'] && isset($r['errores']['hora']));
$r = reservar_turno(array_merge($datos, array('hora' => '10:15')), $a_las_8);
probar('una hora fuera de la grilla de 30 minutos se rechaza', !$r['ok'] && isset($r['errores']['hora']));
$r = reservar_turno(array_merge($datos, array('telefono' => '123', 'email' => 'no-es-mail', 'cliente' => 'P')), $a_las_8);
probar('teléfono corto, email inválido y nombre corto se rechazan',
    !$r['ok'] && isset($r['errores']['telefono'], $r['errores']['email'], $r['errores']['cliente']));
$r = reservar_turno(array_merge($datos, array('fecha' => date('Y-m-d', strtotime($lunes . ' -7 days')))), $a_las_8);
probar('una fecha pasada se rechaza', !$r['ok'] && isset($r['errores']['fecha']));

echo "Consulta y cancelación del cliente\n";
probar('el turno se encuentra con el teléfono escrito de otra forma', buscar_turno_cliente(strtolower($codigo), '(11) 5555 1234') !== null);
probar('con otro teléfono no se encuentra', buscar_turno_cliente($codigo, '11 4444-0000') === null);
probar("código \"' OR '1'='1\" no encuentra nada", buscar_turno_cliente("' OR '1'='1", "' OR '1'='1") === null);
$motivo = cancelar_turno_cliente($codigo, '1155551234', strtotime($lunes . ' 08:30'));
probar('a menos de 2 horas no se puede cancelar', strpos($motivo, 'menos de') !== false);
probar('a 2 horas exactas sí se cancela', cancelar_turno_cliente($codigo, '1155551234', $a_las_8) === '');
probar('el horario cancelado vuelve a estar libre', in_array('10:00', horas(horarios_disponibles(1, 1, $lunes, $a_las_8)), true));
probar('un turno cancelado no se puede volver a cancelar', cancelar_turno_cliente($codigo, '1155551234', $a_las_8) === 'Este turno ya no está activo.');

echo "Panel del negocio\n";
$r = reservar_turno(array_merge($datos, array('servicio_id' => '4', 'hora' => '15:00')), $a_las_8);
$color = buscar_turno_cliente($r['codigo'], $datos['telefono']);
probar('estado inexistente se rechaza', cambiar_estado($color['id'], 'borrado') === false);
probar('marcar "asistió" funciona', cambiar_estado($color['id'], 'asistio') === true);
$res = resumen_del_dia($lunes);
probar('resumen del día: 2 turnos, 1 cancelado, facturación solo del no cancelado ($ 25.000)',
    (int) $res['turnos'] === 2 && (int) $res['cancelados'] === 1 && (float) $res['facturacion'] === 25000.0);
probar('la agenda del día lista los 2 turnos', count(agenda_del_dia($lunes)) === 2);

echo "\n$ok pruebas OK, $fallas fallas\n";
exit($fallas > 0 ? 1 : 0);
