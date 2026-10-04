<?php
/**
 * Configuración. Compatible con PHP 5.6+ (sin sintaxis de PHP 7).
 * Las credenciales salen de config.local.php (fuera de git) o de variables de entorno.
 * Si no hay DSN se usa SQLite en /data (modo demo local).
 */
$local = dirname(__FILE__) . '/config.local.php';
$cfg = file_exists($local) ? require $local : array();

function cfg_valor($cfg, $clave, $env)
{
    if (isset($cfg[$clave])) {
        return $cfg[$clave];
    }
    $valor = getenv($env);
    return $valor === false ? '' : $valor;
}

define('DB_DSN',  cfg_valor($cfg, 'dsn',  'TURNOS_DB_DSN'));   // ej: 'mysql:host=localhost;dbname=turnos;charset=utf8mb4'
define('DB_USER', cfg_valor($cfg, 'user', 'TURNOS_DB_USER'));
define('DB_PASS', cfg_valor($cfg, 'pass', 'TURNOS_DB_PASS'));

define('APP_NOMBRE', 'Barbería Don Ramón');
define('APP_DIRECCION', 'Av. Rivadavia 18.250, Morón');
define('APP_WHATSAPP', '11 5555-0000');

// Reglas de la agenda
define('SLOT_MINUTOS', 30);            // cada cuánto empieza un turno posible
define('MINUTOS_ANTICIPACION', 60);    // no se reserva con menos de 1 hora
define('DIAS_MAXIMOS', 30);            // se reserva hasta 30 días adelante
define('HORAS_CANCELACION', 2);        // el cliente cancela solo hasta 2 horas antes

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Argentina/Buenos_Aires');
