<?php
require_once dirname(__FILE__) . '/includes/turnos.php';
require_once dirname(__FILE__) . '/includes/layout.php';

$codigo = post('codigo');
$telefono = post('telefono');
$turno = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    // Limita los intentos de búsqueda para que no se puedan probar códigos al azar.
    $intentos = isset($_SESSION['busquedas_turno']) ? $_SESSION['busquedas_turno'] : array();
    $intentos = array_filter($intentos, function ($t) {
        return $t > time() - 600;
    });
    if (count($intentos) >= 10) {
        $error = 'Demasiados intentos. Esperá unos minutos.';
    } else {
        $intentos[] = time();
        $_SESSION['busquedas_turno'] = array_values($intentos);
        try {
            if (post('accion') === 'cancelar') {
                $motivo = cancelar_turno_cliente($codigo, $telefono, time());
                if ($motivo === '') {
                    flash('success', 'Tu turno quedó cancelado. ¡Te esperamos otra vez!');
                } else {
                    $error = $motivo;
                }
            }
            $turno = buscar_turno_cliente($codigo, $telefono);
            if ($turno === null && $error === '') {
                $error = 'No encontramos un turno con ese código y teléfono.';
            }
        } catch (PDOException $ex) {
            error_interno($ex);
        }
    }
}

$estados = estados_turno();
layout_inicio('Mi turno');
?>
<div class="row justify-content-center">
<div class="col-md-7 col-lg-5">
    <h1 class="h3 mb-3">Mi turno</h1>
    <?php if ($error): ?>
        <div class="alert alert-warning"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($turno): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h2 class="h5 m-0"><?php echo e($turno['servicio']); ?></h2>
                    <span class="badge <?php echo $turno['estado'] === 'reservado' ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                        <?php echo e($estados[$turno['estado']]); ?></span>
                </div>
                <p class="mb-1"><i class="bi bi-calendar-event"></i> <?php echo e(date('d/m/Y', strtotime($turno['inicio']))); ?>
                    a las <?php echo e(date('H:i', strtotime($turno['inicio']))); ?> hs</p>
                <p class="mb-1"><i class="bi bi-person"></i> Con <?php echo e($turno['profesional']); ?></p>
                <p class="mb-3"><i class="bi bi-geo-alt"></i> <?php echo e(APP_DIRECCION); ?></p>
                <?php if (puede_cancelar($turno, time())): ?>
                    <form method="post" class="js-confirmar" data-mensaje="¿Seguro que querés cancelar el turno?">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="codigo" value="<?php echo e($turno['codigo']); ?>">
                        <input type="hidden" name="telefono" value="<?php echo e($turno['telefono']); ?>">
                        <input type="hidden" name="accion" value="cancelar">
                        <button class="btn btn-outline-danger w-100">Cancelar turno</button>
                    </form>
                <?php elseif ($turno['estado'] === 'reservado'): ?>
                    <p class="small text-body-secondary mb-0">Faltan menos de <?php echo HORAS_CANCELACION; ?> horas: para cancelar escribinos por WhatsApp al <?php echo e(APP_WHATSAPP); ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" class="card">
        <div class="card-body">
            <?php echo csrf_campo(); ?>
            <div class="mb-3">
                <label class="form-label" for="codigo">Código del turno</label>
                <input class="form-control text-uppercase font-monospace" id="codigo" name="codigo" maxlength="6" required
                       value="<?php echo e($codigo); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="telefono">Teléfono con el que reservaste</label>
                <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="20" required value="<?php echo e($telefono); ?>">
            </div>
            <button class="btn btn-dark w-100">Buscar</button>
        </div>
    </form>
</div>
</div>
<?php layout_fin('general.js'); ?>
