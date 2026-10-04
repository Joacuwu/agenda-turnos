<?php
/**
 * Crea un usuario del panel desde la consola, con la contraseña hasheada.
 * Uso: php sql/crear_usuario.php <usuario> <contraseña> "<Nombre>"
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 4 || strlen($argv[2]) < 8) {
    fwrite(STDERR, "Uso: php sql/crear_usuario.php <usuario> <contraseña de 8+ caracteres> \"<Nombre>\"\n");
    exit(1);
}

require dirname(dirname(__FILE__)) . '/includes/db.php';

$stmt = db()->prepare('INSERT INTO usuarios (usuario, password_hash, nombre) VALUES (?, ?, ?)');
$stmt->execute(array($argv[1], password_hash($argv[2], PASSWORD_DEFAULT), $argv[3]));
echo "Usuario {$argv[1]} creado.\n";
