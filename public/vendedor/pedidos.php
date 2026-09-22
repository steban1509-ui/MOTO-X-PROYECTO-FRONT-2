<?php
/**
 * =====================================================================
 *  PEDIDOS PENDIENTES (VENDEDOR)
 * =====================================================================
 *  Muestra los pedidos entrantes con su estado (Pendiente / Aprobado / Rechazada)
 *  y permite cambiarlo con un selector. Desde "Aprobado" se puede marcar como
 *  "Completada": en ese momento la venta sale de aquí y pasa al Historial de ventas.
 *
 *  Acción POST: cambiar_estado  (pedido_id, estado)
 *  La regla de negocio y el manejo de stock están en Pedido::cambiarEstadoVendedor().
 * =====================================================================
 */
require __DIR__ . '/../../app/bootstrap.php';

Auth::requerirRol(Auth::VENDEDOR);

$vendedor = Vendedor::porUsuario((int) Auth::id());
if (!$vendedor) {
    redirect('registro_vendedor.php');
}
$vendedorId = (int) $vendedor['id'];

// ---------------------------------------------------------------------
// Cambio de estado
// ---------------------------------------------------------------------
if (es_post() && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    verificar_csrf();

    $pedidoId    = (int) ($_POST['pedido_id'] ?? 0);
    $nuevoEstado = (string) ($_POST['estado'] ?? '');

    try {
        Pedido::cambiarEstadoVendedor($pedidoId, $vendedorId, $nuevoEstado);

        $mensajes = [
            'pendiente'  => 'El pedido volvió a quedar pendiente.',
            'aprobado'   => 'Pedido aprobado. Cuando lo entregues márcalo como Completada.',
            'rechazado'  => 'Pedido rechazado. Las unidades volvieron a tu stock.',
            'completado' => 'Venta completada. Ya aparece en tu Historial de ventas.',
        ];
        flash('success', 'Pedido #' . $pedidoId . ' actualizado', $mensajes[$nuevoEstado] ?? '');
    } catch (PedidoException $e) {
        flash('error', 'No se pudo cambiar el estado', $e->getMessage());
    }

    redirect_actual();
}

// ---------------------------------------------------------------------
// Datos
// ---------------------------------------------------------------------
$filtro  = in_array($_GET['estado'] ?? '', Pedido::ESTADOS_GESTION, true) ? $_GET['estado'] : null;
$pedidos = Pedido::pedidosDelVendedor($vendedorId, $filtro);
$conteo  = Pedido::conteoVendedorPorEstado($vendedorId);

$titulo  = 'Pedidos pendientes | MotosX';
$estilos = ['css/panel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>
<div class="container py-5">

    <h2 class="panel-titulo mb-3">Pedidos pendientes <small>· <?= e($vendedor['razon_social']) ?></small></h2>

    <?php vista('partials/nav_vendedor', ['seccionVendedor' => 'pedidos', 'pendientesNav' => $conteo['pendiente']]); ?>

    <!-- Filtros por estado -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $filtros = [
            ''          => ['Todos', $conteo['pendiente'] + $conteo['aprobado'] + $conteo['rechazado']],
            'pendiente' => ['Pendientes', $conteo['pendiente']],
            'aprobado'  => ['Aprobados', $conteo['aprobado']],
            'rechazado' => ['Rechazadas', $conteo['rechazado']],
        ];
        foreach ($filtros as $clave => [$texto, $cantidad]): ?>
            <a href="<?= e(url('vendedor/pedidos.php' . ($clave ? '?estado=' . $clave : ''))) ?>"
               class="btn btn-sm <?= (string) $filtro === $clave ? 'btn-dark' : 'btn-outline-dark' ?>">
                <?= e($texto) ?> <span class="badge <?= (string) $filtro === $clave ? 'bg-light text-dark' : 'bg-secondary' ?> ms-1"><?= (int) $cantidad ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card card-panel">
        <div class="card-header"><i class="fa-solid fa-clipboard-list me-2"></i>Pedidos entrantes</div>
        <div class="card-body p-0">
            <?php if (!$pedidos): ?>
                <p class="text-muted p-4 mb-0">No hay pedidos <?= $filtro ? 'con estado "' . e(estado_pedido_texto($filtro)) . '"' : 'por gestionar' ?>.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Estado actual</th>
                            <th style="min-width:220px">Cambiar estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr class="<?= $pedido['estado'] === 'pendiente' ? 'fila-pendiente' : '' ?>">
                            <td class="fw-bold">#<?= (int) $pedido['pedido_id'] ?></td>
                            <td class="small text-nowrap"><?= e(fecha_legible($pedido['fecha'])) ?></td>
                            <td class="small"><?= e($pedido['nombre_cliente']) ?><br><span class="text-muted"><?= e($pedido['correo_cliente']) ?></span></td>
                            <td class="small">
                                <?php foreach ($pedido['productos'] as $item): ?>
                                    <div>
                                        <?= (int) $item['cantidad'] ?> × <?= e($item['producto_nombre']) ?>
                                        <?php if ($item['stock_actual'] !== null): ?>
                                            <span class="text-muted">· stock: <?= (int) $item['stock_actual'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end fw-bold"><?= e(precio($pedido['subtotal'])) ?></td>
                            <td class="text-center"><?= estado_pedido_badge($pedido['estado']) ?></td>
                            <td>
                                <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>" class="d-flex gap-2 form-estado-pedido"
                                      data-pedido="<?= (int) $pedido['pedido_id'] ?>" data-actual="<?= e($pedido['estado']) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['pedido_id'] ?>">
                                    <select name="estado" class="form-select form-select-sm" aria-label="Nuevo estado">
                                        <?php foreach ($pedido['opciones'] as $opcion): ?>
                                        <option value="<?= e($opcion) ?>" <?= $opcion === $pedido['estado'] ? 'selected' : '' ?>>
                                            <?= e(estado_pedido_texto($opcion)) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-dark" title="Guardar"><i class="fa-solid fa-check"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="small text-muted mt-3">
        <i class="fa-solid fa-circle-info me-1"></i>
        Al <strong>rechazar</strong> un pedido, las unidades vuelven a tu stock. Al <strong>reactivar</strong> uno rechazado se descuentan de nuevo
        (si no hay stock suficiente, no se permite). Una venta <strong>Completada</strong> pasa al Historial de ventas y ya no se puede modificar.
    </p>
</div>

<footer class="bg-light text-center py-4 mt-4">© <?= date('Y') ?> MotosX. Todos los derechos reservados.</footer>

<script>
// Confirmación para las acciones que mueven stock o cierran la venta
document.querySelectorAll('.form-estado-pedido').forEach(form => {
    form.addEventListener('submit', function (e) {
        const nuevo = this.querySelector('select').value;
        if (nuevo === this.dataset.actual) {
            e.preventDefault();
            Swal.fire({ icon: 'info', title: 'Selecciona un estado diferente', confirmButtonColor: '#212529', timer: 1800 });
            return;
        }
        if (this.dataset.confirmado || !['rechazado', 'completado'].includes(nuevo)) return;

        e.preventDefault();
        const rechazar = nuevo === 'rechazado';
        Swal.fire({
            icon: rechazar ? 'warning' : 'question',
            title: (rechazar ? '¿Rechazar' : '¿Marcar como completada') + ' el pedido #' + this.dataset.pedido + '?',
            text: rechazar
                ? 'Las unidades volverán a tu stock y el cliente verá el pedido como rechazado.'
                : 'La venta pasará al Historial de ventas y ya no podrás cambiar su estado.',
            showCancelButton: true,
            confirmButtonText: rechazar ? 'Sí, rechazar' : 'Sí, completar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: rechazar ? '#dc3545' : '#198754'
        }).then(r => {
            if (r.isConfirmed) {
                this.dataset.confirmado = '1';
                this.submit();
            }
        });
    });
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
