<?php
/**
 * =====================================================================
 *  HISTORIAL DE VENTAS (VENDEDOR)
 * =====================================================================
 *  Filtro ESTRICTO: solo muestra las partes de pedido del vendedor con
 *  estado "completado" (Pedido::ventasCompletadas). Los pedidos pendientes,
 *  aprobados, rechazados o cancelados NO aparecen aquí.
 * =====================================================================
 */
require __DIR__ . '/../../app/bootstrap.php';

Auth::requerirRol(Auth::VENDEDOR);

$vendedor = Vendedor::porUsuario((int) Auth::id());
if (!$vendedor) {
    redirect('registro_vendedor.php');
}
$vendedorId = (int) $vendedor['id'];

$ventas        = Pedido::ventasCompletadas($vendedorId);
$totalVendido  = array_sum(array_map(fn ($v) => (float) $v['subtotal'], $ventas));
$unidades      = 0;
foreach ($ventas as $venta) {
    foreach ($venta['productos'] as $item) {
        $unidades += (int) $item['cantidad'];
    }
}

$titulo  = 'Historial de ventas | MotosX';
$estilos = ['css/panel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>
<div class="container py-5">

    <h2 class="panel-titulo mb-3">Historial de ventas <small>· <?= e($vendedor['razon_social']) ?></small></h2>

    <?php vista('partials/nav_vendedor', ['seccionVendedor' => 'ventas', 'pendientesNav' => Pedido::contarPendientesVendedor($vendedorId)]); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat"><div class="numero"><?= count($ventas) ?></div><div class="etiqueta">Ventas completadas</div></div></div>
        <div class="col-md-4"><div class="stat"><div class="numero"><?= $unidades ?></div><div class="etiqueta">Unidades vendidas</div></div></div>
        <div class="col-md-4"><div class="stat"><div class="numero text-success"><?= e(precio($totalVendido)) ?></div><div class="etiqueta">Total vendido</div></div></div>
    </div>

    <div class="card card-panel">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fa-solid fa-receipt me-2"></i>Ventas completadas</span>
            <small class="text-muted fw-normal">Solo se listan ventas con estado "Completada"</small>
        </div>
        <div class="card-body p-0">
            <?php if (!$ventas): ?>
                <p class="text-muted p-4 mb-0">
                    Aún no tienes ventas completadas. Cuando apruebes un pedido y lo marques como
                    <strong>Completada</strong> en <a href="<?= e(url('vendedor/pedidos.php')) ?>">Pedidos pendientes</a>, aparecerá aquí.
                </p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Fecha de compra</th>
                            <th>Completada</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td class="fw-bold">#<?= (int) $venta['pedido_id'] ?></td>
                            <td class="small text-nowrap"><?= e(fecha_legible($venta['fecha'])) ?></td>
                            <td class="small text-nowrap"><?= e(fecha_legible($venta['fecha_actualizacion'])) ?></td>
                            <td class="small"><?= e($venta['nombre_cliente']) ?><br><span class="text-muted"><?= e($venta['correo_cliente']) ?></span></td>
                            <td class="small">
                                <?php foreach ($venta['productos'] as $item): ?>
                                    <div><?= (int) $item['cantidad'] ?> × <?= e($item['producto_nombre']) ?> <span class="text-muted">(<?= e(precio($item['precio'])) ?>)</span></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end fw-bold"><?= e(precio($venta['subtotal'])) ?></td>
                            <td class="text-center"><?= estado_pedido_badge($venta['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="bg-light text-center py-4 mt-4">© <?= date('Y') ?> MotosX. Todos los derechos reservados.</footer>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
