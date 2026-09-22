<?php
/** Menú de sesión*/
$usuarioMenu = Auth::usuario();
$iconoLogin  = $iconoLogin ?? '<i class="fa-solid fa-user"></i>';
$claseLogin  = $claseLogin ?? 'text-dark me-4';

// Contador de pedidos pendientes del vendedor (una consulta por página)
$pendientesMenu = 0;
if ($usuarioMenu && Auth::tieneRol(Auth::VENDEDOR)) {
    $vendedorMenu   = Vendedor::porUsuario((int) $usuarioMenu['id']);
    $pendientesMenu = $vendedorMenu ? Pedido::contarPendientesVendedor((int) $vendedorMenu['id']) : 0;
}
?>
<?php if (!$usuarioMenu): ?>
    <a href="<?= e(url('login.php')) ?>" class="<?= e($claseLogin) ?>" title="Iniciar sesión"><?= $iconoLogin ?></a>
    <?php if (!empty($mostrarRegistro)): ?>
    <a href="<?= e(url('registro.php')) ?>" class="<?= e($claseLogin) ?>" title="Crear cuenta"><i class="fas fa-user-edit"></i></a>
    <?php endif; ?>
<?php else: ?>
    <div class="dropdown d-inline-block menu-usuario">
        <a href="javascript:void(0)" class="text-dark dropdown-toggle text-decoration-none position-relative"
           data-bs-toggle="dropdown" aria-expanded="false">
            👤 <?= e(explode(' ', trim($usuarioMenu['nombre']))[0]) ?>
            <?php if ($pendientesMenu > 0): ?>
                <span class="badge-circulo badge-circulo-toggle" title="Pedidos pendientes"><?= $pendientesMenu > 99 ? '99+' : $pendientesMenu ?></span>
            <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">

            <?php if (Auth::tieneRol(Auth::VENDEDOR)): ?>
                <li><a class="dropdown-item" href="<?= e(url('vendedor/perfil.php')) ?>"><i class="fa-solid fa-id-card me-2"></i>Perfil</a></li>
                <li><a class="dropdown-item" href="<?= e(url('vendedor/productos.php')) ?>"><i class="fa-solid fa-boxes-stacked me-2"></i>Mis productos</a></li>
                <li><a class="dropdown-item" href="<?= e(url('vendedor/ventas.php')) ?>"><i class="fa-solid fa-chart-line me-2"></i>Historial de ventas</a></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center" href="<?= e(url('vendedor/pedidos.php')) ?>">
                        <i class="fa-solid fa-clipboard-list me-2"></i>Pedidos pendientes
                        <?php if ($pendientesMenu > 0): ?>
                            <span class="badge-circulo ms-auto"><?= $pendientesMenu > 99 ? '99+' : $pendientesMenu ?></span>
                        <?php endif; ?>
                    </a>
                </li>

            <?php elseif (Auth::tieneRol(Auth::ADMIN)): ?>
                <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>"><i class="fa-solid fa-gauge me-2"></i>Panel de administración</a></li>

            <?php else: ?>
                <li><a class="dropdown-item" href="<?= e(url('perfil.php')) ?>"><i class="fa-solid fa-bag-shopping me-2"></i>Mis compras</a></li>
            <?php endif; ?>

            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar sesión</button>
                </form>
            </li>
        </ul>
    </div>
<?php endif; ?>
