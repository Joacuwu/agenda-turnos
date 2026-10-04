<?php
/**
 * Reglas de la agenda: horarios libres, reservas, cancelaciones y estados.
 * Todas las consultas son preparadas. Las fechas se guardan como 'Y-m-d H:i:s' en hora local,
 * así que se pueden comparar como texto.
 */
require_once dirname(__FILE__) . '/funciones.php';

function servicios_activos()
{
    return db()->query('SELECT id, nombre, duracion, precio FROM servicios WHERE activo = 1 ORDER BY id')->fetchAll();
}

function profesionales_activos()
{
    return db()->query('SELECT id, nombre FROM profesionales WHERE activo = 1 ORDER BY nombre')->fetchAll();
}

function obtener_servicio($id)
{
    $stmt = db()->prepare('SELECT id, nombre, duracion, precio FROM servicios WHERE id = ? AND activo = 1');
    $stmt->execute(array($id));
    $fila = $stmt->fetch();
    return $fila ? $fila : null;
}

function fecha_valida($texto)
{
    $fecha = DateTime::createFromFormat('!Y-m-d', $texto);
    return $fecha && $fecha->format('Y-m-d') === $texto;
}

/** La fecha está entre hoy y el máximo de días permitido. */
function fecha_reservable($fecha, $ahora)
{
    if (!fecha_valida($fecha)) {
        return false;
    }
    $hoy = date('Y-m-d', $ahora);
    $limite = date('Y-m-d', strtotime('+' . DIAS_MAXIMOS . ' days', $ahora));
    return $fecha >= $hoy && $fecha <= $limite;
}

/**
 * Horarios libres para un servicio en una fecha.
 * $profesional_id = 0 significa "sin preferencia": para cada hora devuelve el primer profesional libre.
 * Un horario es libre si entra completo dentro de un bloque de atención, no se superpone con un turno
 * no cancelado del mismo profesional y respeta la anticipación mínima.
 * Devuelve array(array('hora' => '10:30', 'profesional_id' => 1, 'profesional' => 'Lucas'), ...) ordenado por hora.
 */
function horarios_disponibles($servicio_id, $profesional_id, $fecha, $ahora)
{
    $servicio = obtener_servicio($servicio_id);
    if ($servicio === null || !fecha_reservable($fecha, $ahora)) {
        return array();
    }

    $sql = 'SELECT id, nombre FROM profesionales WHERE activo = 1';
    $params = array();
    if ($profesional_id > 0) {
        $sql .= ' AND id = ?';
        $params[] = $profesional_id;
    }
    $stmt = db()->prepare($sql . ' ORDER BY nombre');
    $stmt->execute($params);
    $profesionales = $stmt->fetchAll();

    $dia_semana = (int) date('N', strtotime($fecha));
    $minimo = date('Y-m-d H:i:s', $ahora + MINUTOS_ANTICIPACION * 60);
    $duracion = (int) $servicio['duracion'] * 60;

    $bloques = db()->prepare('SELECT desde, hasta FROM horarios WHERE profesional_id = ? AND dia_semana = ? ORDER BY desde');
    $ocupados = db()->prepare("SELECT inicio, fin FROM turnos
                                WHERE profesional_id = ? AND estado <> 'cancelado' AND inicio < ? AND fin > ?");

    $libres = array();
    foreach ($profesionales as $p) {
        $bloques->execute(array($p['id'], $dia_semana));
        $ocupados->execute(array($p['id'], $fecha . ' 23:59:59', $fecha . ' 00:00:00'));
        $turnos = $ocupados->fetchAll();

        foreach ($bloques->fetchAll() as $b) {
            $fin_bloque = strtotime($fecha . ' ' . $b['hasta']);
            for ($t = strtotime($fecha . ' ' . $b['desde']); $t + $duracion <= $fin_bloque; $t += SLOT_MINUTOS * 60) {
                $inicio = date('Y-m-d H:i:s', $t);
                $fin = date('Y-m-d H:i:s', $t + $duracion);
                $hora = date('H:i', $t);
                if ($inicio < $minimo || isset($libres[$hora]) || se_superpone($inicio, $fin, $turnos)) {
                    continue;
                }
                $libres[$hora] = array('hora' => $hora, 'profesional_id' => (int) $p['id'], 'profesional' => $p['nombre']);
            }
        }
    }

    ksort($libres);
    return array_values($libres);
}

/** Dos rangos [inicio, fin) se superponen si cada uno empieza antes de que termine el otro. */
function se_superpone($inicio, $fin, $turnos)
{
    foreach ($turnos as $t) {
        if ($inicio < $t['fin'] && $fin > $t['inicio']) {
            return true;
        }
    }
    return false;
}

/** Deja solo los dígitos del teléfono. Devuelve '' si no tiene entre 8 y 15. */
function normalizar_telefono($texto)
{
    $digitos = preg_replace('/\D+/', '', (string) $texto);
    $n = strlen($digitos);
    return $n >= 8 && $n <= 15 ? $digitos : '';
}

/** Código corto para que el cliente consulte su turno. Sin letras ni números que se confundan (0/O, 1/I). */
function generar_codigo()
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $bytes = function_exists('random_bytes') ? random_bytes(6) : openssl_random_pseudo_bytes(6);
    $codigo = '';
    for ($i = 0; $i < 6; $i++) {
        $codigo .= $alfabeto[ord($bytes[$i]) % 32];
    }
    return $codigo;
}

function largo($texto)
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : (int) preg_match_all('/./us', $texto);
}

/**
 * Valida y guarda una reserva. Antes de insertar bloquea al profesional dentro de la transacción
 * y vuelve a calcular los horarios libres: si dos personas eligen el mismo horario a la vez,
 * la segunda recibe "ese horario ya no está disponible".
 * Devuelve array('ok' => true, 'codigo' => ..., 'inicio' => ...) o array('ok' => false, 'errores' => array(campo => mensaje)).
 */
function reservar_turno($entrada, $ahora)
{
    $d = array();
    foreach (array('servicio_id', 'profesional_id', 'fecha', 'hora', 'cliente', 'telefono', 'email') as $campo) {
        $d[$campo] = isset($entrada[$campo]) && is_string($entrada[$campo]) ? trim($entrada[$campo]) : '';
    }
    $d['servicio_id'] = entero($d['servicio_id']);
    $d['profesional_id'] = entero($d['profesional_id']);
    $err = array();

    $servicio = $d['servicio_id'] > 0 ? obtener_servicio($d['servicio_id']) : null;
    if ($servicio === null) {
        $err['servicio_id'] = 'Elegí un servicio.';
    }
    if ($d['profesional_id'] < 1) {
        $err['hora'] = 'Elegí un horario.';
    }
    if (!fecha_reservable($d['fecha'], $ahora)) {
        $err['fecha'] = 'Elegí una fecha entre hoy y los próximos ' . DIAS_MAXIMOS . ' días.';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['hora'])) {
        $err['hora'] = 'Elegí un horario.';
    }
    $n = largo($d['cliente']);
    if ($n < 3 || $n > 80) {
        $err['cliente'] = 'Ingresá tu nombre y apellido.';
    }
    $telefono = normalizar_telefono($d['telefono']);
    if ($telefono === '') {
        $err['telefono'] = 'Ingresá un teléfono con código de área (ej. 11 5555-1234).';
    }
    if ($d['email'] !== '' && (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 120)) {
        $err['email'] = 'El email no es válido.';
    }
    if ($err) {
        return array('ok' => false, 'errores' => $err);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Bloqueo de la fila del profesional: serializa las reservas de un mismo profesional
        // (en MySQL bloquea la fila; en SQLite toma el bloqueo de escritura).
        $pdo->prepare('UPDATE profesionales SET activo = activo WHERE id = ?')->execute(array($d['profesional_id']));

        $libre = false;
        foreach (horarios_disponibles($d['servicio_id'], $d['profesional_id'], $d['fecha'], $ahora) as $h) {
            if ($h['hora'] === $d['hora']) {
                $libre = true;
                break;
            }
        }
        if (!$libre) {
            $pdo->rollBack();
            return array('ok' => false, 'errores' => array('hora' => 'Ese horario ya no está disponible. Elegí otro.'));
        }

        $inicio = strtotime($d['fecha'] . ' ' . $d['hora']);
        $stmt = $pdo->prepare('INSERT INTO turnos (codigo, servicio_id, profesional_id, inicio, fin, cliente, telefono, email, estado, creado_en)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'reservado\', ?)');
        $codigo = null;
        for ($intento = 0; $intento < 5 && $codigo === null; $intento++) {
            $candidato = generar_codigo();
            $existe = $pdo->prepare('SELECT COUNT(*) FROM turnos WHERE codigo = ?');
            $existe->execute(array($candidato));
            if ((int) $existe->fetchColumn() === 0) {
                $codigo = $candidato;
            }
        }
        $stmt->execute(array(
            $codigo, $d['servicio_id'], $d['profesional_id'],
            date('Y-m-d H:i:s', $inicio), date('Y-m-d H:i:s', $inicio + (int) $servicio['duracion'] * 60),
            $d['cliente'], $telefono, $d['email'] !== '' ? $d['email'] : null, date('Y-m-d H:i:s', $ahora),
        ));
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    return array('ok' => true, 'codigo' => $codigo, 'inicio' => date('Y-m-d H:i:s', $inicio));
}

function sql_turno()
{
    return 'SELECT t.id, t.codigo, t.inicio, t.fin, t.cliente, t.telefono, t.email, t.estado,
                   s.nombre AS servicio, s.precio, p.nombre AS profesional, p.id AS profesional_id
              FROM turnos t
              JOIN servicios s ON s.id = t.servicio_id
              JOIN profesionales p ON p.id = t.profesional_id';
}

/** Turno de un cliente: pide código y teléfono, para que no alcance con adivinar un código. */
function buscar_turno_cliente($codigo, $telefono)
{
    $stmt = db()->prepare(sql_turno() . ' WHERE t.codigo = ? AND t.telefono = ?');
    $stmt->execute(array(strtoupper(trim($codigo)), normalizar_telefono($telefono)));
    $fila = $stmt->fetch();
    return $fila ? $fila : null;
}

function puede_cancelar($turno, $ahora)
{
    return $turno['estado'] === 'reservado'
        && strtotime($turno['inicio']) - $ahora >= HORAS_CANCELACION * 3600;
}

/** Cancelación por parte del cliente. Devuelve '' si se canceló o el motivo por el que no se pudo. */
function cancelar_turno_cliente($codigo, $telefono, $ahora)
{
    $turno = buscar_turno_cliente($codigo, $telefono);
    if ($turno === null) {
        return 'No encontramos un turno con ese código y teléfono.';
    }
    if (!puede_cancelar($turno, $ahora)) {
        return $turno['estado'] !== 'reservado'
            ? 'Este turno ya no está activo.'
            : 'Faltan menos de ' . HORAS_CANCELACION . ' horas: para cancelar escribinos por WhatsApp.';
    }
    $stmt = db()->prepare("UPDATE turnos SET estado = 'cancelado' WHERE id = ? AND estado = 'reservado'");
    $stmt->execute(array($turno['id']));
    return '';
}

/* ---------- Panel del negocio ---------- */

function estados_turno()
{
    return array('reservado' => 'Reservado', 'asistio' => 'Asistió', 'ausente' => 'No vino', 'cancelado' => 'Cancelado');
}

function agenda_del_dia($fecha)
{
    $stmt = db()->prepare(sql_turno() . ' WHERE t.inicio >= ? AND t.inicio <= ? ORDER BY p.nombre, t.inicio');
    $stmt->execute(array($fecha . ' 00:00:00', $fecha . ' 23:59:59'));
    return $stmt->fetchAll();
}

/** Totales del día por estado y facturación estimada de los turnos no cancelados. */
function resumen_del_dia($fecha)
{
    $stmt = db()->prepare("SELECT COUNT(*) AS turnos,
                                  COALESCE(SUM(CASE WHEN t.estado = 'cancelado' THEN 1 ELSE 0 END), 0) AS cancelados,
                                  COALESCE(SUM(CASE WHEN t.estado = 'ausente' THEN 1 ELSE 0 END), 0) AS ausentes,
                                  COALESCE(SUM(CASE WHEN t.estado IN ('reservado', 'asistio') THEN s.precio ELSE 0 END), 0) AS facturacion
                             FROM turnos t
                             JOIN servicios s ON s.id = t.servicio_id
                            WHERE t.inicio >= ? AND t.inicio <= ?");
    $stmt->execute(array($fecha . ' 00:00:00', $fecha . ' 23:59:59'));
    return $stmt->fetch();
}

function cambiar_estado($id, $estado)
{
    if (!array_key_exists($estado, estados_turno())) {
        return false;
    }
    $stmt = db()->prepare('UPDATE turnos SET estado = ? WHERE id = ?');
    $stmt->execute(array($estado, $id));
    if ($stmt->rowCount() > 0) {
        return true;
    }
    // MySQL cuenta solo las filas que cambiaron: si ya tenía ese estado, rowCount() da 0 aunque el turno exista
    $stmt = db()->prepare('SELECT 1 FROM turnos WHERE id = ?');
    $stmt->execute(array($id));
    return (bool) $stmt->fetchColumn();
}
