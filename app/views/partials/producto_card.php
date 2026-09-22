<?php
/**
 * CARD ÚNICA DE PRODUCTO
 * catalogo.php la repite dentro de un foreach para cada producto de la base de datos.
 *
 * v4:
 *  - Muestra el "Stock disponible".
 *  - El botón guarda el stock en data-stock para que carrito.js no permita
 *    agregar más unidades de las disponibles.
 *  - Vendedores y administradores no ven el botón de carrito.
 *
 * @var array $producto  Fila de vw_catalogo_publico
 */
$idProducto = (int) $producto['id'];
$stock      = max(0, (int) $producto['stock']);
$agotado    = $stock === 0;
$urlImagen  = asset($producto['imagen']);
$claseStock = $agotado ? 'stock-agotado' : ($stock <= 5 ? 'stock-bajo' : '');
?>
<div class="card">
    <a href="<?= e(url('producto.php?id=' . $idProducto)) ?>" class="card-imagen">
        <img src="<?= e($urlImagen) ?>" alt="<?= e($producto['nombre']) ?>" loading="lazy">
    </a>

    <h3><?= e($producto['nombre']) ?></h3>
    <p><?= e($producto['descripcion']) ?></p>
    <h4><?= e(precio($producto['precio'])) ?></h4>

    <span class="stock-disponible <?= $claseStock ?>">
        <?= $agotado ? 'Agotado' : 'Stock disponible: ' . $stock ?>
    </span>

    <a href="javascript:void(0)" class="btn-ver-producto" onclick="verProducto(<?= $idProducto ?>)">
        Ver producto
    </a>

    <?php if (Auth::puedeComprar()): ?>
    <button type="button"
            data-id="<?= $idProducto ?>"
            data-nombre="<?= e($producto['nombre']) ?>"
            data-precio="<?= e((float) $producto['precio']) ?>"
            data-imagen="<?= e($urlImagen) ?>"
            data-stock="<?= $stock ?>"
            onclick="agregarCarritoDesdeBoton(this, event)"
            <?= $agotado ? 'disabled' : '' ?>>
        <?= $agotado ? 'Agotado' : 'Añadir al carrito' ?>
    </button>
    <?php endif; ?>
</div>
