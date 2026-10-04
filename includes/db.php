<?php
require_once dirname(__FILE__) . '/config.php';

/**
 * Conexión PDO única. En modo demo (sin DSN) crea una base SQLite con datos de ejemplo.
 */
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $opciones = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    );

    if (DB_DSN !== '') {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $opciones);
        return $pdo;
    }

    $archivo = dirname(dirname(__FILE__)) . '/data/demo.sqlite';
    $nueva = !file_exists($archivo);
    $pdo = new PDO('sqlite:' . $archivo, null, null, $opciones);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($nueva) {
        crear_base_demo($pdo, true);
    }
    return $pdo;
}

function crear_base_demo(PDO $pdo, $con_turnos)
{
    $pdo->exec('CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        nombre VARCHAR(100) NOT NULL
    )');
    $pdo->exec('CREATE TABLE profesionales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre VARCHAR(60) NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1
    )');
    $pdo->exec('CREATE TABLE servicios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre VARCHAR(60) NOT NULL,
        duracion INTEGER NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1
    )');
    $pdo->exec('CREATE TABLE horarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        profesional_id INTEGER NOT NULL REFERENCES profesionales(id),
        dia_semana TINYINT NOT NULL,
        desde CHAR(5) NOT NULL,
        hasta CHAR(5) NOT NULL
    )');
    $pdo->exec('CREATE TABLE turnos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo CHAR(6) NOT NULL UNIQUE,
        servicio_id INTEGER NOT NULL REFERENCES servicios(id),
        profesional_id INTEGER NOT NULL REFERENCES profesionales(id),
        inicio DATETIME NOT NULL,
        fin DATETIME NOT NULL,
        cliente VARCHAR(80) NOT NULL,
        telefono VARCHAR(20) NOT NULL,
        email VARCHAR(120),
        estado VARCHAR(10) NOT NULL DEFAULT \'reservado\',
        creado_en DATETIME NOT NULL
    )');
    $pdo->exec('CREATE INDEX idx_turnos_agenda ON turnos (profesional_id, inicio)');

    $stmt = $pdo->prepare('INSERT INTO usuarios (usuario, password_hash, nombre) VALUES (?, ?, ?)');
    $stmt->execute(array('admin', password_hash('admin1234', PASSWORD_DEFAULT), 'Ramón'));

    $stmt = $pdo->prepare('INSERT INTO profesionales (nombre) VALUES (?)');
    $stmt->execute(array('Lucas'));
    $stmt->execute(array('Martina'));

    $stmt = $pdo->prepare('INSERT INTO servicios (nombre, duracion, precio) VALUES (?, ?, ?)');
    $stmt->execute(array('Corte', 30, 9000));
    $stmt->execute(array('Corte y barba', 45, 13500));
    $stmt->execute(array('Barba', 30, 6000));
    $stmt->execute(array('Color', 90, 25000));

    // Lucas: lunes a viernes de 9 a 13 y de 15 a 19, sábados de 9 a 14. Martina: martes a sábado de 10 a 18.
    $stmt = $pdo->prepare('INSERT INTO horarios (profesional_id, dia_semana, desde, hasta) VALUES (?, ?, ?, ?)');
    for ($dia = 1; $dia <= 5; $dia++) {
        $stmt->execute(array(1, $dia, '09:00', '13:00'));
        $stmt->execute(array(1, $dia, '15:00', '19:00'));
    }
    $stmt->execute(array(1, 6, '09:00', '14:00'));
    for ($dia = 2; $dia <= 6; $dia++) {
        $stmt->execute(array(2, $dia, '10:00', '18:00'));
    }

    if (!$con_turnos) {
        return;
    }

    // Algunos turnos de ejemplo en los próximos días, para que la agenda no arranque vacía.
    $ejemplos = array(
        array(1, 1, '10:00', 30, 'Pablo Medina', '1144556677'),
        array(2, 1, '11:30', 45, 'Andrés Vega', '1133221100'),
        array(4, 2, '11:00', 90, 'Camila Ruiz', '1166778899'),
        array(3, 2, '15:00', 30, 'Federico Paz', '1122334455'),
    );
    $stmt = $pdo->prepare('INSERT INTO turnos (codigo, servicio_id, profesional_id, inicio, fin, cliente, telefono, estado, creado_en)
                           VALUES (?, ?, ?, ?, ?, ?, ?, \'reservado\', ?)');
    $n = 0;
    for ($d = 1; $d <= 4; $d++) {
        $fecha = date('Y-m-d', strtotime("+$d day"));
        $dia = (int) date('N', strtotime($fecha));
        foreach ($ejemplos as $t) {
            $trabaja = $t[1] === 1 ? $dia <= 6 : ($dia >= 2 && $dia <= 6);
            if (!$trabaja) {
                continue;
            }
            $inicio = strtotime($fecha . ' ' . $t[2]);
            $stmt->execute(array(sprintf('DEMO%02d', ++$n), $t[0], $t[1], date('Y-m-d H:i:s', $inicio),
                date('Y-m-d H:i:s', $inicio + $t[3] * 60), $t[4], $t[5], date('Y-m-d H:i:s')));
        }
    }
}
