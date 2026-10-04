<?php
require_once dirname(__FILE__) . '/includes/turnos.php';
require_once dirname(__FILE__) . '/includes/layout.php';

try {
    $servicios = servicios_activos();
    $profesionales = profesionales_activos();
} catch (PDOException $ex) {
    error_interno($ex);
}
$hoy = date('Y-m-d');
$limite = date('Y-m-d', strtotime('+' . DIAS_MAXIMOS . ' days'));

layout_inicio('Reservá tu turno');
?>
<div class="row justify-content-center">
<div class="col-lg-8">
    <h1 class="h3 mb-1">Reservá tu turno</h1>
    <p class="text-body-secondary mb-4">Elegí el servicio, con quién y el horario. Te damos un código para consultar o cancelar.</p>

    <!-- Paso final: confirmación (se muestra por Ajax al reservar) -->
    <div class="card border-success d-none" id="confirmacion">
        <div class="card-body text-center p-4">
            <i class="bi bi-check-circle-fill text-success display-5"></i>
            <h2 class="h4 mt-2">¡Turno reservado!</h2>
            <p class="mb-1 js-conf-detalle"></p>
            <p class="mb-3">Tu código: <strong class="fs-4 font-monospace js-conf-codigo"></strong></p>
            <p class="small text-body-secondary">Guardalo: con el código y tu teléfono podés ver o cancelar el turno en <a href="mi-turno.php">Mi turno</a>.</p>
            <a href="index.php" class="btn btn-outline-success">Reservar otro turno</a>
        </div>
    </div>

    <form id="formReserva" action="api/reservar.php" method="post" novalidate>
        <?php echo csrf_campo(); ?>
        <input type="hidden" name="profesional_id" id="profesionalElegido">
        <input type="hidden" name="hora" id="horaElegida">

        <section class="card mb-3">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary">1. Servicio</h2>
                <div class="row g-2" role="radiogroup">
                    <?php foreach ($servicios as $i => $s): ?>
                        <div class="col-sm-6">
                            <input type="radio" class="btn-check" name="servicio_id" id="servicio<?php echo (int) $s['id']; ?>"
                                   value="<?php echo (int) $s['id']; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>>
                            <label class="btn btn-outline-dark w-100 text-start d-flex justify-content-between" for="servicio<?php echo (int) $s['id']; ?>">
                                <span><?php echo e($s['nombre']); ?> <small class="opacity-75">· <?php echo (int) $s['duracion']; ?> min</small></span>
                                <span>$ <?php echo e(number_format($s['precio'], 0, ',', '.')); ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-sm-6">
                    <h2 class="h6 text-uppercase text-body-secondary"><label for="profesional">2. Con quién</label></h2>
                    <select class="form-select" id="profesional">
                        <option value="0">Sin preferencia</option>
                        <?php foreach ($profesionales as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>"><?php echo e($p['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6">
                    <h2 class="h6 text-uppercase text-body-secondary"><label for="fecha">3. Día</label></h2>
                    <input type="date" class="form-control" id="fecha" name="fecha" min="<?php echo $hoy; ?>" max="<?php echo $limite; ?>"
                           value="<?php echo $hoy; ?>">
                </div>
                <div class="col-12">
                    <h2 class="h6 text-uppercase text-body-secondary">4. Horario</h2>
                    <div id="horarios" class="d-flex flex-wrap gap-2" aria-live="polite"></div>
                    <div class="text-danger small mt-2 js-error" data-campo="hora"></div>
                </div>
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-body row g-3">
                <h2 class="h6 text-uppercase text-body-secondary mb-0">5. Tus datos</h2>
                <div class="col-sm-6">
                    <label class="form-label" for="cliente">Nombre y apellido</label>
                    <input class="form-control" id="cliente" name="cliente" maxlength="80" autocomplete="name" required>
                    <div class="invalid-feedback js-error" data-campo="cliente"></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="20" placeholder="11 5555-1234"
                           autocomplete="tel" required>
                    <div class="invalid-feedback js-error" data-campo="telefono"></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="email">Email <span class="text-body-secondary">(opcional)</span></label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="120" autocomplete="email">
                    <div class="invalid-feedback js-error" data-campo="email"></div>
                </div>
            </div>
        </section>

        <div class="alert alert-danger d-none js-error-general" role="alert"></div>
        <div class="d-grid">
            <button class="btn btn-dark btn-lg js-reservar" disabled>Elegí un horario</button>
        </div>
    </form>
</div>
</div>
<?php layout_fin('reserva.js'); ?>
