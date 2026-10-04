<?php
require_once dirname(__FILE__) . '/includes/funciones.php';
require_once dirname(__FILE__) . '/includes/layout.php';

if (usuario_actual() !== null) {
    redirigir('agenda.php');
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $usuario = post('usuario');
    $intentos = isset($_SESSION['intentos_login']) ? $_SESSION['intentos_login'] : 0;

    try {
        if ($intentos >= 5 && time() - $_SESSION['ultimo_intento'] < 300) {
            $error = 'Demasiados intentos. Esperá 5 minutos.';
        } elseif (intentar_login($usuario, isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '')) {
            unset($_SESSION['intentos_login'], $_SESSION['ultimo_intento']);
            redirigir('agenda.php');
        } else {
            $_SESSION['intentos_login'] = $intentos + 1;
            $_SESSION['ultimo_intento'] = time();
            $error = 'Usuario o contraseña incorrectos.';
        }
    } catch (PDOException $ex) {
        error_interno($ex);
    }
}

layout_inicio('Panel del negocio', true);
?>
<div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-sm-8 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1 text-center"><i class="bi bi-scissors"></i> <?php echo e(APP_NOMBRE); ?></h1>
                <p class="text-body-secondary text-center small mb-4">Panel del negocio</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?php echo e($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <?php echo csrf_campo(); ?>
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo e($usuario); ?>"
                               autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-dark w-100">Ingresar</button>
                </form>
                <p class="small text-body-secondary text-center mt-3 mb-0">
                    Demo: <code>admin</code> / <code>admin1234</code>
                </p>
            </div>
        </div>
    </div>
</div>
<?php layout_fin(); ?>
