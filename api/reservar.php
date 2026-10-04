<?php
/**
 * Endpoint Ajax: confirma una reserva. POST con los datos del formulario y el token CSRF.
 * Responde JSON con el código del turno o con los errores por campo.
 */
require_once dirname(dirname(__FILE__)) . '/includes/turnos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder_json(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}
verificar_csrf();

// Límite simple contra reservas en masa desde una misma sesión.
$recientes = isset($_SESSION['reservas']) ? array_filter($_SESSION['reservas'], function ($t) {
    return $t > time() - 3600;
}) : array();
if (count($recientes) >= 3) {
    responder_json(array('ok' => false, 'error' => 'Hiciste varias reservas seguidas. Si necesitás más turnos, escribinos por WhatsApp.'), 429);
}

try {
    $r = reservar_turno($_POST, time());
} catch (PDOException $ex) {
    error_interno($ex);
}

if (!$r['ok']) {
    responder_json($r, 422);
}

$recientes[] = time();
$_SESSION['reservas'] = array_values($recientes);

$turno = buscar_turno_cliente($r['codigo'], post('telefono'));
responder_json(array(
    'ok'      => true,
    'codigo'  => $r['codigo'],
    'detalle' => $turno['servicio'] . ' con ' . $turno['profesional'] . ', el ' . date('d/m', strtotime($turno['inicio']))
                 . ' a las ' . date('H:i', strtotime($turno['inicio'])) . ' hs.',
));
