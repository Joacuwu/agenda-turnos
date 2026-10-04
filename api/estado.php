<?php
/**
 * Endpoint Ajax del panel: cambia el estado de un turno. POST id, estado, csrf. Responde JSON.
 */
require_once dirname(dirname(__FILE__)) . '/includes/turnos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder_json(array('ok' => false, 'error' => 'Método no permitido.'), 405);
}
if (usuario_actual() === null) {
    responder_json(array('ok' => false, 'error' => 'La sesión expiró. Volvé a ingresar.'), 401);
}
verificar_csrf();

try {
    if (!cambiar_estado(entero(post('id')), post('estado'))) {
        responder_json(array('ok' => false, 'error' => 'No se pudo cambiar el estado.'), 422);
    }
} catch (PDOException $ex) {
    error_interno($ex);
}
responder_json(array('ok' => true));
