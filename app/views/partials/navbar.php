<?php
/**
 * Barra de navegación principal.
 * El carrito solo se muestra a visitantes y compradores (no a vendedores/admins).
 *   $mostrarBuscador bool
 */
?>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(asset('img/logo/moto_x_logo.jpg')) ?>" height="60" alt="MotosX">
        </a>

        <?php if (!empty($mostrarBuscador)): ?>
        <form class="mx-auto w-50 d-none d-md-block" onsubmit="return false;">
            <div class="input-group">
                <input type="text" class="form-control rounded-pill" placeholder="Buscar repuestos, accesorios...">
            </div>
        </form>
        <?php endif; ?>

        <div class="d-flex align-items-center">
            <?php if (Auth::puedeComprar()): ?>
            <a href="<?= e(url('carrito.php')) ?>" class="text-dark me-4" title="Carrito">
                <i class="fa-solid fa-cart-shopping"></i>
            </a>
            <?php endif; ?>
            <span id="auth-area">
                <?php vista('partials/menu_usuario', ['mostrarRegistro' => true]); ?>
            </span>
        </div>
    </div>
</nav>
