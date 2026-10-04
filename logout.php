<?php
require_once dirname(__FILE__) . '/includes/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}
verificar_csrf();

$_SESSION = array();
session_destroy();
redirigir('login.php');
