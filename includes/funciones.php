<?php
require_once dirname(__FILE__) . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/', '', isset($_SERVER['HTTPS']), true);
    session_start();
}

/** Escapa texto para HTML. */
function e($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function redirigir($url)
{
    header('Location: ' . $url);
    exit;
}

function get($campo)
{
    return isset($_GET[$campo]) && is_string($_GET[$campo]) ? trim($_GET[$campo]) : '';
}

function post($campo)
{
    return isset($_POST[$campo]) && is_string($_POST[$campo]) ? trim($_POST[$campo]) : '';
}

/** Entero no negativo o -1 si no es válido. */
function entero($valor)
{
    return ctype_digit((string) $valor) ? (int) $valor : -1;
}

/** 1234567.5 -> 1.234.567,50 */
function pesos($numero)
{
    return number_format((float) $numero, 2, ',', '.');
}

function es_ajax()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function responder_json($datos, $codigo = 200)
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos);
    exit;
}

/** Registra el error real en el log y responde un mensaje genérico. */
function error_interno(Exception $ex)
{
    error_log('[turnos] ' . $ex->getMessage());
    if (es_ajax()) {
        responder_json(array('ok' => false, 'error' => 'Error interno. Intentá de nuevo.'), 500);
    }
    http_response_code(500);
    exit('Ocurrió un error interno. Intentá de nuevo más tarde.');
}

/* ---------- Autenticación (panel del negocio) ---------- */

function usuario_actual()
{
    return isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
}

function requiere_login()
{
    if (usuario_actual() !== null) {
        return;
    }
    if (es_ajax()) {
        responder_json(array('ok' => false, 'error' => 'La sesión expiró. Volvé a ingresar.'), 401);
    }
    redirigir('login.php');
}

function intentar_login($usuario, $password)
{
    $stmt = db()->prepare('SELECT id, usuario, password_hash, nombre FROM usuarios WHERE usuario = ?');
    $stmt->execute(array($usuario));
    $fila = $stmt->fetch();

    if (!$fila || !password_verify($password, $fila['password_hash'])) {
        return null;
    }

    session_regenerate_id(true);
    unset($fila['password_hash']);
    $fila['id'] = (int) $fila['id'];
    $_SESSION['usuario'] = $fila;
    return $fila;
}

/* ---------- CSRF ---------- */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = function_exists('random_bytes')
            ? bin2hex(random_bytes(32))
            : bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verificar_csrf()
{
    $token = post('csrf');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        if (es_ajax()) {
            responder_json(array('ok' => false, 'error' => 'Solicitud inválida. Recargá la página.'), 403);
        }
        http_response_code(403);
        exit('Solicitud inválida. Volvé atrás y recargá la página.');
    }
}

/* ---------- Mensajes flash ---------- */

function flash($tipo, $texto)
{
    $_SESSION['flash'] = array('tipo' => $tipo, 'texto' => $texto);
}

function mostrar_flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . e($f['tipo']) . ' alert-dismissible fade show" role="alert">' . e($f['texto'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
}
