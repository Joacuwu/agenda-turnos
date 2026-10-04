<?php
/**
 * Endpoint Ajax: horarios libres.
 * GET servicio_id, profesional_id (0 = sin preferencia), fecha (aaaa-mm-dd). Responde JSON.
 */
require_once dirname(dirname(__FILE__)) . '/includes/turnos.php';

$servicio_id = entero(get('servicio_id'));
$profesional_id = max(0, entero(get('profesional_id')));
$fecha = get('fecha');

if ($servicio_id < 1 || !fecha_valida($fecha)) {
    responder_json(array('ok' => false, 'error' => 'Datos inválidos.'), 422);
}

try {
    responder_json(array('ok' => true, 'horarios' => horarios_disponibles($servicio_id, $profesional_id, $fecha, time())));
} catch (PDOException $ex) {
    error_interno($ex);
}
