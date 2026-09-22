<?php
/**
 * CATÁLOGO DINÁMICO
 * Reemplaza a llantas.html, frenos.html, repuestos.html, accesorios.html y aceites.html.
 *
 *   catalogo.php?categoria=llantas
 *   catalogo.php?categoria=frenos      ...etc.
 *
 * Los productos vienen de vw_catalogo_publico, que SOLO contiene productos activos.
 */
require __DIR__ . '/../app/bootstrap.php';

// ---------------- Controlador ----------------
$slug      = (string) ($_GET['categoria'] ?? '');
$categoria = Categoria::porSlug($slug);

if (!$categoria) {
    flash('warning', 'Categoría no encontrada', 'La sección que buscas no existe.');
    redirect('index.php');
}

$productos = Producto::catalogoPorCategoria((int) $categoria['id']);

// ---------------- Vista ----------------
$titulo  = $categoria['nombre'] . ' | MotosX';
$estilos = ['css/catalogo.css'];
require VIEW_PATH . '/layouts/head.php';
require VIEW_PATH . '/partials/header_catalogo.php';
?>

<div class="container">
    <section class="productos">

        <h2><?= e($categoria['titulo']) ?></h2>

        <div class="contenedor">
            <?php if (!$productos): ?>
                <p class="sin-productos">Por ahora no hay productos disponibles en esta categoría.</p>
            <?php endif; ?>

            <?php foreach ($productos as $producto): ?>
                <?php require VIEW_PATH . '/partials/producto_card.php'; ?>
            <?php endforeach; ?>
        </div>

    </section>
</div>

<?php
require VIEW_PATH . '/partials/footer.php';
require VIEW_PATH . '/partials/modal_producto.php';

$scripts = ['js/carrito.js', 'js/producto-modal.js'];
require VIEW_PATH . '/layouts/scripts.php';
