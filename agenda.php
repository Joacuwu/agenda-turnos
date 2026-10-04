<?php
require_once dirname(__FILE__) . '/includes/turnos.php';
require_once dirname(__FILE__) . '/includes/layout.php';
requiere_login();

$fecha = get('fecha');
if (!fecha_valida($fecha)) {
    $fecha = date('Y-m-d');
}
$anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));

try {
    $turnos = agenda_del_dia($fecha);
    $resumen = resumen_del_dia($fecha);
} catch (PDOException $ex) {
    error_interno($ex);
}

// Agrupa los turnos por profesional para mostrar una columna por cada uno.
$por_profesional = array();
foreach ($turnos as $t) {
    $por_profesional[$t['profesional']][] = $t;
}

$estados = estados_turno();
$colores = array('reservado' => 'primary', 'asistio' => 'success', 'ausente' => 'warning', 'cancelado' => 'secondary');
$dias = array(1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo');

layout_inicio('Agenda', true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group" role="group" aria-label="Cambiar de día">
        <a class="btn btn-outline-dark" href="agenda.php?fecha=<?php echo $anterior; ?>" aria-label="Día anterior"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-outline-dark" href="agenda.php">Hoy</a>
        <a class="btn btn-outline-dark" href="agenda.php?fecha=<?php echo $siguiente; ?>" aria-label="Día siguiente"><i class="bi bi-chevron-right"></i></a>
    </div>
    <h1 class="h4 m-0"><?php echo e($dias[(int) date('N', strtotime($fecha))] . ' ' . date('d/m/Y', strtotime($fecha))); ?></h1>
    <form method="get" class="d-flex gap-2">
        <input type="date" name="fecha" class="form-control" value="<?php echo e($fecha); ?>" aria-label="Ir a fecha">
        <button class="btn btn-dark">Ir</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
        <div class="small text-body-secondary">Turnos</div><div class="fs-4 fw-semibold"><?php echo (int) $resumen['turnos']; ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
        <div class="small text-body-secondary">Cancelados</div><div class="fs-4 fw-semibold"><?php echo (int) $resumen['cancelados']; ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
        <div class="small text-body-secondary">No vinieron</div><div class="fs-4 fw-semibold"><?php echo (int) $resumen['ausentes']; ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
        <div class="small text-body-secondary">Facturación estimada</div><div class="fs-4 fw-semibold">$ <?php echo e(number_format($resumen['facturacion'], 0, ',', '.')); ?></div>
    </div></div></div>
</div>

<?php if (!$por_profesional): ?>
    <div class="alert alert-light border">No hay turnos para este día.</div>
<?php endif; ?>

<div class="row g-3">
<?php foreach ($por_profesional as $profesional => $lista): ?>
    <div class="col-lg-6">
        <h2 class="h5"><i class="bi bi-person"></i> <?php echo e($profesional); ?></h2>
        <div class="list-group">
        <?php foreach ($lista as $t): ?>
            <div class="list-group-item" id="turno-<?php echo (int) $t['id']; ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold"><?php echo e(date('H:i', strtotime($t['inicio'])) . ' – ' . date('H:i', strtotime($t['fin']))); ?>
                            · <?php echo e($t['servicio']); ?></div>
                        <div><?php echo e($t['cliente']); ?>
                            <a class="small ms-1" href="https://wa.me/549<?php echo e($t['telefono']); ?>" target="_blank" rel="noopener">
                                <i class="bi bi-whatsapp"></i> <?php echo e($t['telefono']); ?></a></div>
                        <div class="small text-body-secondary font-monospace"><?php echo e($t['codigo']); ?></div>
                    </div>
                    <select class="form-select form-select-sm w-auto js-estado border-<?php echo $colores[$t['estado']]; ?>"
                            data-id="<?php echo (int) $t['id']; ?>" data-anterior="<?php echo e($t['estado']); ?>" aria-label="Estado del turno">
                        <?php foreach ($estados as $clave => $nombre): ?>
                            <option value="<?php echo e($clave); ?>"<?php echo $clave === $t['estado'] ? ' selected' : ''; ?>><?php echo e($nombre); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<input type="hidden" id="csrf" value="<?php echo e(csrf_token()); ?>">
<?php layout_fin('panel.js'); ?>
