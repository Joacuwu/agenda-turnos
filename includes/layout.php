<?php
/**
 * $panel = true muestra la barra del panel del negocio; si no, la barra pública.
 */
function layout_inicio($titulo, $panel = false)
{
    $u = usuario_actual();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($titulo); ?> · <?php echo e(APP_NOMBRE); ?></title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $panel ? 'agenda.php' : 'index.php'; ?>"><i class="bi bi-scissors"></i> <?php echo e(APP_NOMBRE); ?></a>
        <?php if ($panel && $u): ?>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="d-none d-sm-inline"><i class="bi bi-person-circle"></i> <?php echo e($u['nombre']); ?></span>
                <form action="logout.php" method="post" class="m-0">
                    <?php echo csrf_campo(); ?>
                    <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Salir</button>
                </form>
            </div>
        <?php elseif (!$panel): ?>
            <a class="btn btn-sm btn-outline-light" href="mi-turno.php"><i class="bi bi-search"></i> Mi turno</a>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
    <?php echo mostrar_flash(); ?>
<?php
}

function layout_fin($script = '')
{
    ?>
</main>
<footer class="text-center text-body-secondary small pb-4">
    <?php echo e(APP_NOMBRE); ?> · <?php echo e(APP_DIRECCION); ?> · WhatsApp <?php echo e(APP_WHATSAPP); ?>
</footer>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php if ($script !== ''): ?>
<script src="assets/js/<?php echo e($script); ?>"></script>
<?php endif; ?>
</body>
</html>
<?php
}
