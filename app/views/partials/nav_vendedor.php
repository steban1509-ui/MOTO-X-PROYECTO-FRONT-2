<?php
/**
 * Pestañas del panel del vendedor (mismas secciones del menú desplegable).
 *   $seccionVendedor  'perfil' | 'productos' | 'ventas' | 'pedidos'
 *   $pendientesNav    int
 */
$pestanas = [
    'perfil'    => ['vendedor/perfil.php',    'fa-id-card',         'Perfil'],
    'productos' => ['vendedor/productos.php', 'fa-boxes-stacked',   'Mis productos'],
    'ventas'    => ['vendedor/ventas.php',    'fa-chart-line',      'Historial de ventas'],
    'pedidos'   => ['vendedor/pedidos.php',   'fa-clipboard-list',  'Pedidos pendientes'],
];
?>
<ul class="nav nav-pills mb-4 flex-wrap gap-1">
    <?php foreach ($pestanas as $clave => [$ruta, $icono, $texto]): ?>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center <?= ($seccionVendedor ?? '') === $clave ? 'active' : '' ?>" href="<?= e(url($ruta)) ?>">
            <i class="fa-solid <?= e($icono) ?> me-2"></i><?= e($texto) ?>
            <?php if ($clave === 'pedidos' && !empty($pendientesNav)): ?>
                <span class="badge-circulo ms-2"><?= (int) $pendientesNav ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
