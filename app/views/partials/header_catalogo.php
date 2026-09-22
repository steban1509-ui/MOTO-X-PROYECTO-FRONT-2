<?php /** Encabezado fijo de las páginas de catálogo */ ?>
<header>
    <a href="<?= e(url('index.php')) ?>" class="btn-volver">← Inicio</a>

    <div class="logo">
        <img src="<?= e(asset('img/logo/moto_x_logo.jpg')) ?>" alt="MotosX">
    </div>

    <div class="menu">
        <?php if (Auth::puedeComprar()): ?>
        <div class="carrito-container">
            <a href="<?= e(url('carrito.php')) ?>" class="btn-carrito" id="icono-carrito">🛒</a>
            <span id="contador-carrito">0</span>
        </div>
        <?php endif; ?>

        <span id="auth-area">
            <?php vista('partials/menu_usuario', ['iconoLogin' => '👤', 'claseLogin' => 'btn-login']); ?>
        </span>
    </div>
</header>
