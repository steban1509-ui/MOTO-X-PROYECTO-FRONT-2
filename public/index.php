<?php
/**
 * Página de inicio (antes index.html).
 * Las tarjetas de categorías ahora salen de la tabla categorias.
 */
require __DIR__ . '/../app/bootstrap.php';

$categorias = Categoria::todas();
$slides     = Producto::aleatoriosParaCarrusel(8);   // v4: carrusel aleatorio

$titulo  = 'MotosX';
$estilos = ['css/style.css', 'css/carrusel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar', ['mostrarBuscador' => true]);
?>

<!-- =================================================================
     HERO CARRUSEL (v4)
     Imágenes de productos activos de la BD en orden ALEATORIO en cada visita
     (Producto::aleatoriosParaCarrusel). Cada diapositiva lleva a producto.php.
     Estilos: css/carrusel.css
     ================================================================= -->
<?php if ($slides): ?>
<section class="hero-carrusel">
    <div id="heroCarrusel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">

        <div class="carousel-inner">
            <?php foreach ($slides as $i => $slide): $imagenSlide = asset($slide['imagen']); ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <a href="<?= e(url('producto.php?id=' . (int) $slide['id'])) ?>" class="hero-slide" aria-label="Ver <?= e($slide['nombre']) ?>">
                    <span class="hero-fondo" style='background-image:url("<?= e($imagenSlide) ?>")'></span>
                    <span class="hero-degradado"></span>

                    <div class="container hero-contenido">
                        <div class="hero-texto">
                            <span class="hero-categoria"><?= e(ucfirst($slide['categoria_slug'])) ?></span>
                            <h2 class="hero-titulo"><?= e($slide['nombre']) ?></h2>
                            <p class="hero-descripcion"><?= e($slide['descripcion']) ?></p>
                            <div class="hero-acciones">
                                <span class="hero-precio"><?= e(precio($slide['precio'])) ?></span>
                                <span class="hero-boton">Ver producto <span aria-hidden="true">→</span></span>
                            </div>
                        </div>

                        <div class="hero-imagen">
                            <img src="<?= e($imagenSlide) ?>" alt="<?= e($slide['nombre']) ?>" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($slides) > 1): ?>
        <div class="carousel-indicators hero-indicadores">
            <?php foreach ($slides as $i => $slide): ?>
            <button type="button" data-bs-target="#heroCarrusel" data-bs-slide-to="<?= $i ?>"
                    class="<?= $i === 0 ? 'active' : '' ?>" <?= $i === 0 ? 'aria-current="true"' : '' ?>
                    aria-label="<?= e($slide['nombre']) ?>"></button>
            <?php endforeach; ?>
        </div>

        <button class="carousel-control-prev hero-control" type="button" data-bs-target="#heroCarrusel" data-bs-slide="prev">
            <span class="hero-control-icono"><span class="carousel-control-prev-icon" aria-hidden="true"></span></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next hero-control" type="button" data-bs-target="#heroCarrusel" data-bs-slide="next">
            <span class="hero-control-icono"><span class="carousel-control-next-icon" aria-hidden="true"></span></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<!-- Sin productos todavía: portada estática -->
<section class="hero">
    <div class="overlay">
        <div class="container text-center">
            <h1>Encuentra el repuesto exacto para tu moto</h1>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CATEGORÍAS (dinámicas) -->
<section class="py-5">
    <div class="container">
        <h2 class="fw-bold mb-4">Repuestos 100% Compatibles</h2>
        <div class="row g-4">
            <?php foreach ($categorias as $categoria): ?>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 shadow">
                    <img src="<?= e(asset($categoria['imagen'])) ?>" class="card-img-top" alt="<?= e($categoria['nombre']) ?>">
                    <div class="card-body d-flex flex-column">
                        <h5><?= e($categoria['nombre']) ?></h5>
                        <p><?= e($categoria['descripcion']) ?></p>
                        <a href="<?= e(url('catalogo.php?categoria=' . urlencode($categoria['slug']))) ?>" class="btn btn-dark w-100 mt-auto">
                            Ver más
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<footer class="bg-light text-center py-4">
    © <?= date('Y') ?> MotosX. Todos los derechos reservados.
</footer>

<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
