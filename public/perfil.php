<?php
/**
 * MIS COMPRAS (antes perfil.html + js/perfil.js + php/pedidos/*.php)
 *
 * Muestra el historial de compras. Los productos que el vendedor haya
 * desactivado SIGUEN apareciendo aquí (Pedido::historialUsuario no filtra
 * por estado), marcados como "Ya no disponible".
 */
require __DIR__ . '/../app/bootstrap.php';

// v4: página EXCLUSIVA del comprador (vendedores y administradores son redirigidos a su panel)
Auth::requerirComprador();

$usuario = Auth::usuario();

// ---------------- Acción: reducir cantidades de un pedido (regla 24 h) ----------------
if (es_post() && ($_POST['accion'] ?? '') === 'reducir_pedido') {
    verificar_csrf();

    $pedidoId   = (int) ($_POST['pedido_id'] ?? 0);
    $cantidades = [];
    foreach ((array) ($_POST['cantidades'] ?? []) as $detalleId => $cantidad) {
        $cantidades[(int) $detalleId] = (int) $cantidad;
    }

    try {
        $nuevoTotal = Pedido::reducirCantidades($pedidoId, (int) $usuario['id'], $cantidades);
        $nuevoTotal > 0
            ? flash('success', 'Pedido actualizado', 'Nuevo total: ' . precio($nuevoTotal))
            : flash('info', 'Pedido cancelado', 'Quitaste todos los productos, así que el pedido quedó cancelado.');
    } catch (PedidoException $e) {
        flash('error', 'No se pudo actualizar', $e->getMessage());
    }
    redirect('perfil.php');
}

$pedidos = Pedido::historialUsuario((int) $usuario['id']);

// ---------------- Vista ----------------
$titulo  = 'Mis compras - MotosX';
$estilos = ['css/style.css', 'css/panel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>
<div class="container py-5">

    <h2 class="panel-titulo mb-1">Mis compras</h2>
    <p class="text-muted"><?= e($usuario['nombre']) ?> · <?= e($usuario['correo']) ?></p>
    <hr>

    <?php if (!$pedidos): ?>
        <p class="text-muted">Todavía no has realizado ningún pedido. <a href="<?= e(url('index.php')) ?>">Ir a la tienda</a></p>
    <?php endif; ?>

    <?php foreach ($pedidos as $pedido): ?>
    <div class="card card-panel mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0">Pedido #<?= (int) $pedido['id'] ?> — <?= e(fecha_legible($pedido['fecha'])) ?></h6>
                <?= estado_pedido_badge($pedido['estado']) ?>
            </div>

            <ul class="list-unstyled mb-3">
                <?php foreach ($pedido['productos'] as $item): ?>
                <li class="d-flex align-items-center gap-3 mb-2 item-historial">
                    <img src="<?= e(asset($item['imagen'])) ?>" alt="">
                    <span>
                        <?= (int) $item['cantidad'] ?> × <?= e($item['producto_nombre']) ?> — <?= e(precio($item['precio'])) ?>
                        <?php if ($item['producto_estado'] !== 'activo'): ?>
                            <span class="badge bg-light text-secondary border ms-1">Ya no disponible en la tienda</span>
                        <?php endif; ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php if (count($pedido['vendedores']) > 0): ?>
            <div class="small mb-2">
                <?php foreach ($pedido['vendedores'] as $parte): ?>
                    <span class="me-3 text-nowrap"><i class="fa-solid fa-store me-1 text-muted"></i><?= e($parte['razon_social']) ?>: <?= estado_pedido_badge($parte['estado']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p class="fw-bold">Total: <?= e(precio($pedido['total'])) ?></p>

            <?php if ($pedido['editable']): ?>
                <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalPedido<?= (int) $pedido['id'] ?>">
                    Modificar pedido (quedan <?= e($pedido['horas_restantes']) ?> h)
                </button>

                <!-- Modal para reducir cantidades -->
                <div class="modal fade" id="modalPedido<?= (int) $pedido['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable">
                        <form class="modal-content" method="post" action="<?= e(url('perfil.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="reducir_pedido">
                            <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">

                            <div class="modal-header">
                                <h5 class="modal-title">Modificar pedido #<?= (int) $pedido['id'] ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?php foreach ($pedido['productos'] as $item): if ($item['estado_vendedor'] !== 'pendiente') { continue; } ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="cant<?= (int) $item['id'] ?>"><?= e($item['producto_nombre']) ?></label>
                                    <input type="number" id="cant<?= (int) $item['id'] ?>" class="form-control form-control-sm w-25"
                                           name="cantidades[<?= (int) $item['id'] ?>]" min="0" max="<?= (int) $item['cantidad'] ?>" value="<?= (int) $item['cantidad'] ?>">
                                </div>
                                <?php endforeach; ?>
                                <p class="text-muted small mt-2">Solo puedes bajar cantidades. Pon 0 para quitar un producto.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-dark">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <span class="text-muted small">Este pedido ya no se puede modificar.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<footer class="bg-light text-center py-4">© <?= date('Y') ?> MotosX. Todos los derechos reservados.</footer>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
