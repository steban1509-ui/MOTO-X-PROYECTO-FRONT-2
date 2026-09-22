<?php
/**
 * FICHA DE PRODUCTO (v4)
 *   producto.php?id=5
 *
 * Destino de las diapositivas del carrusel del inicio y del enlace de cada card.
 * Solo muestra productos ACTIVOS (vw_catalogo_publico).
 * Botones interactivos: selector de cantidad (− / +), "Añadir al carrito" con
 * confirmación en el propio botón, "Comprar ahora" y "Compartir".
 * Ninguno permite superar el stock disponible.
 */
require __DIR__ . '/../app/bootstrap.php';

$id       = (int) ($_GET['id'] ?? 0);
$producto = $id > 0 ? Producto::publicoPorId($id) : null;

if (!$producto) {
    flash('warning', 'Producto no disponible', 'El producto que buscas no existe o ya no está a la venta.');
    redirect('index.php');
}

$imagenes = array_slice(Producto::imagenes($id), 0, MAX_IMAGENES_PRODUCTO);
if (!$imagenes) {
    $imagenes = [['url_imagen' => $producto['imagen']]];
}
$resenas   = Resena::deProducto($id);
$promedio  = $resenas ? round(array_sum(array_column($resenas, 'calificacion')) / count($resenas), 1) : 0;
$categoria = Categoria::porSlug($producto['categoria_slug']);
$stock     = max(0, (int) $producto['stock']);

$titulo  = $producto['nombre'] . ' | MotosX';
$estilos = ['css/style.css', 'css/panel.css', 'css/producto.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>
<div class="container py-5">

    <!-- Ruta de navegación dentro de una caja (estilos en css/producto.css) -->
    <nav class="ruta-producto" aria-label="Ruta de navegación">
        <ol class="ruta-lista">
            <li>
                <a href="<?= e(url('index.php')) ?>" class="ruta-enlace">
                    <i class="fa-solid fa-house"></i><span>Inicio</span>
                </a>
            </li>
            <?php if ($categoria): ?>
            <li>
                <a href="<?= e(url('catalogo.php?categoria=' . urlencode($categoria['slug']))) ?>" class="ruta-enlace">
                    <i class="fa-solid fa-layer-group"></i><span><?= e($categoria['nombre']) ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <span class="ruta-actual" aria-current="page"><?= e($producto['nombre']) ?></span>
            </li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- ===================== Galería ===================== -->
        <div class="col-lg-6">
            <div class="card card-panel p-3">
                <div id="galeriaProducto" class="carousel slide">
                    <div class="carousel-inner">
                        <?php foreach ($imagenes as $i => $imagen): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <img src="<?= e(asset($imagen['url_imagen'])) ?>" class="d-block w-100 imagen-ficha" alt="<?= e($producto['nombre']) ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($imagenes) > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#galeriaProducto" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" style="filter:invert(1) grayscale(1);"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#galeriaProducto" data-bs-slide="next">
                        <span class="carousel-control-next-icon" style="filter:invert(1) grayscale(1);"></span>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (count($imagenes) > 1): ?>
                <div class="miniaturas mt-3">
                    <?php foreach ($imagenes as $i => $imagen): ?>
                    <img src="<?= e(asset($imagen['url_imagen'])) ?>" class="miniatura-ficha <?= $i === 0 ? 'activa' : '' ?>"
                         role="button" tabindex="0" alt="Foto <?= $i + 1 ?>"
                         data-bs-target="#galeriaProducto" data-bs-slide-to="<?= $i ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== Información y compra ===================== -->
        <div class="col-lg-6">
            <h1 class="fw-bold ficha-titulo"><?= e($producto['nombre']) ?></h1>

            <p class="text-muted mb-2">
                <?= $resenas ? '⭐ ' . e($promedio) . ' (' . count($resenas) . ' reseña' . (count($resenas) === 1 ? '' : 's') . ')' : 'Sin calificaciones todavía' ?>
            </p>

            <p class="fs-5"><?= e($producto['descripcion']) ?></p>

            <h2 class="fw-bold mb-1 ficha-precio"><?= e(precio($producto['precio'])) ?></h2>
            <p class="stock-disponible <?= $stock === 0 ? 'stock-agotado' : ($stock <= 5 ? 'stock-bajo' : '') ?>" id="etiquetaStock">
                <?= $stock === 0 ? 'Agotado' : 'Stock disponible: ' . $stock ?>
            </p>

            <?php if (Auth::puedeComprar()): ?>
            <div class="caja-compra">
                <?php if ($stock > 0): ?>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                    <span class="fw-bold">Cantidad</span>

                    <!-- Selector − / + -->
                    <div class="selector-cantidad" role="group" aria-label="Cantidad">
                        <button type="button" class="paso" id="btnMenos" aria-label="Quitar una unidad">−</button>
                        <input type="number" id="cantidad" value="1" min="1" max="<?= $stock ?>" inputmode="numeric" aria-live="polite">
                        <button type="button" class="paso" id="btnMas" aria-label="Agregar una unidad">+</button>
                    </div>

                    <span class="text-muted small" id="subtotalCalculado"
                          data-precio="<?= e((float) $producto['precio']) ?>">Subtotal: <?= e(precio($producto['precio'])) ?></span>
                </div>

                <div class="acciones-compra"
                     data-id="<?= (int) $producto['id'] ?>"
                     data-nombre="<?= e($producto['nombre']) ?>"
                     data-precio="<?= e((float) $producto['precio']) ?>"
                     data-imagen="<?= e(asset($producto['imagen'])) ?>"
                     data-stock="<?= $stock ?>">
                    <button type="button" class="btn-accion btn-agregar" id="btnAgregarFicha">
                        <span class="etiqueta"><i class="fa-solid fa-cart-plus"></i> Añadir al carrito</span>
                    </button>

                    <button type="button" class="btn-accion btn-comprar" id="btnComprarAhora">
                        <i class="fa-solid fa-bolt"></i> Comprar ahora
                    </button>

                    <button type="button" class="btn-accion btn-compartir" id="btnCompartir" title="Copiar enlace">
                        <i class="fa-solid fa-share-nodes"></i> Compartir
                    </button>
                </div>
                <?php else: ?>
                <button type="button" class="btn-accion btn-agregar" disabled>
                    <i class="fa-solid fa-ban"></i> Producto agotado
                </button>
                <p class="small text-muted mt-2 mb-0">Vuelve pronto: el vendedor puede reponer unidades.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-secondary my-4 small">
                <i class="fa-solid fa-circle-info me-1"></i>Estás viendo la tienda con una cuenta de vendedor o administrador; las compras están deshabilitadas.
            </div>
            <?php endif; ?>

            <div class="card card-panel my-4">
                <div class="card-body">
                    <p class="mb-1"><strong>Vendedor:</strong> <?= e($producto['vendedor_nombre']) ?></p>
                    <p class="mb-0"><strong>Ubicación:</strong> <?= e($producto['vendedor_ubicacion']) ?></p>
                </div>
            </div>

            <?php if ($caracteristicas = lista_caracteristicas($producto['caracteristicas'])): ?>
            <h5 class="fw-bold">Características</h5>
            <ul class="lista-caracteristicas">
                <?php foreach ($caracteristicas as $caracteristica): ?>
                <li><?= e($caracteristica) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <a href="<?= e(url('catalogo.php?categoria=' . urlencode($producto['categoria_slug']))) ?>" class="btn btn-outline-dark mt-2">
                <i class="fa-solid fa-arrow-left me-2"></i>Seguir viendo <?= e($categoria['nombre'] ?? 'productos') ?>
            </a>
        </div>
    </div>

    <!-- ===================== Reseñas ===================== -->
    <div class="row mt-5">
        <div class="col-lg-8">
            <h4 class="fw-bold mb-3">Reseñas</h4>
            <?php if (!$resenas): ?>
                <p class="text-muted">Este producto todavía no tiene reseñas.</p>
            <?php endif; ?>
            <?php foreach ($resenas as $resena): ?>
            <div class="border-bottom pb-2 mb-3">
                <strong><?= e($resena['nombre_usuario']) ?></strong>
                <?= str_repeat('⭐', (int) $resena['calificacion']) . str_repeat('☆', 5 - (int) $resena['calificacion']) ?>
                <small class="text-muted ms-2"><?= e(fecha_legible($resena['fecha'])) ?></small>
                <?php if ($resena['comentario']): ?><p class="mb-0 mt-1"><?= e($resena['comentario']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (Auth::esComprador()): ?>
            <div class="card card-panel mt-4">
                <div class="card-body">
                    <h6 class="fw-bold">Deja tu reseña</h6>
                    <div id="estrellasFicha" class="estrellas-seleccion mb-2"></div>
                    <textarea id="comentarioFicha" class="form-control mb-2" rows="2" maxlength="1000" placeholder="Escribe tu opinión (opcional)"></textarea>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="btnResenaFicha">Enviar reseña</button>
                </div>
            </div>
            <?php elseif (!Auth::check()): ?>
            <p class="small text-muted mt-3"><a href="<?= e(url('login.php')) ?>">Inicia sesión</a> como comprador para dejar una reseña.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="bg-light text-center py-4 mt-4">© <?= date('Y') ?> MotosX. Todos los derechos reservados.</footer>

<script src="<?= e(asset('js/carrito.js')) ?>"></script>
<script>
// =====================================================================
//  Miniaturas: marcar la que se está viendo
// =====================================================================
const galeria = document.getElementById('galeriaProducto');
if (galeria) {
    galeria.addEventListener('slid.bs.carousel', function (e) {
        document.querySelectorAll('.miniatura-ficha').forEach((m, i) => m.classList.toggle('activa', i === e.to));
    });
    document.querySelectorAll('.miniatura-ficha').forEach(m => {
        m.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); m.click(); } });
    });
}

// =====================================================================
//  Selector de cantidad + botones de compra
// =====================================================================
const zonaCompra = document.querySelector('.acciones-compra');

if (zonaCompra) {
    const datos     = zonaCompra.dataset;
    const stock     = Number(datos.stock);
    const precio    = Number(datos.precio);
    const input     = document.getElementById('cantidad');
    const btnMenos  = document.getElementById('btnMenos');
    const btnMas    = document.getElementById('btnMas');
    const subtotal  = document.getElementById('subtotalCalculado');
    const btnAgregar = document.getElementById('btnAgregarFicha');
    const btnComprar = document.getElementById('btnComprarAhora');

    function cantidadActual() {
        return Math.min(stock, Math.max(1, parseInt(input.value, 10) || 1));
    }

    function refrescar() {
        const cantidad = cantidadActual();
        input.value = cantidad;
        btnMenos.disabled = cantidad <= 1;
        btnMas.disabled   = cantidad >= stock;
        subtotal.textContent = 'Subtotal: ' + formatoPrecio(precio * cantidad);
    }

    btnMenos.addEventListener('click', () => { input.value = cantidadActual() - 1; refrescar(); });
    btnMas.addEventListener('click', () => {
        if (cantidadActual() >= stock) {
            alertaStock(datos.nombre, stock, 0);
            return;
        }
        input.value = cantidadActual() + 1;
        refrescar();
    });
    input.addEventListener('input', refrescar);
    refrescar();

    /** Agrega al carrito y devuelve true/false. */
    function agregar() {
        return agregarCarrito(Number(datos.id), datos.nombre, precio, datos.imagen, null, stock, cantidadActual());
    }

    // Botón "Añadir al carrito": confirma dentro del propio botón
    btnAgregar.addEventListener('click', function () {
        if (this.classList.contains('cargando')) return;

        const etiqueta = this.querySelector('.etiqueta');
        const original = etiqueta.innerHTML;

        this.classList.add('cargando');
        etiqueta.innerHTML = '<span class="spinner"></span> Agregando...';

        setTimeout(() => {
            const agregado = agregar();
            this.classList.remove('cargando');

            if (!agregado) {
                etiqueta.innerHTML = original;
                return;
            }

            this.classList.add('listo');
            etiqueta.innerHTML = '<i class="fa-solid fa-check"></i> ¡Agregado!';

            setTimeout(() => {
                this.classList.remove('listo');
                etiqueta.innerHTML = original;
            }, 1800);
        }, 350);
    });

    // Botón "Comprar ahora": agrega y va directo al carrito
    btnComprar.addEventListener('click', function () {
        if (agregar()) {
            this.disabled = true;
            this.innerHTML = '<span class="spinner"></span> Abriendo carrito...';
            setTimeout(() => { window.location.href = MOTOSX.baseUrl + '/carrito.php'; }, 500);
        }
    });

    // Botón "Compartir": usa el menú del sistema o copia el enlace
    document.getElementById('btnCompartir').addEventListener('click', async function () {
        const enlace = window.location.href;
        try {
            if (navigator.share) {
                await navigator.share({ title: datos.nombre, url: enlace });
                return;
            }
            await navigator.clipboard.writeText(enlace);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Enlace copiado', showConfirmButton: false, timer: 1500 });
        } catch (e) {
            Swal.fire({ icon: 'info', title: 'Copia el enlace', text: enlace, confirmButtonColor: '#212529' });
        }
    });
}

// =====================================================================
//  Reseña (solo compradores)
// =====================================================================
const botonResena = document.getElementById('btnResenaFicha');
if (botonResena) {
    let calificacion = 0;
    const contenedor = document.getElementById('estrellasFicha');

    function dibujar(resaltar = 0) {
        contenedor.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
            const estrella = document.createElement('span');
            estrella.textContent = i <= (resaltar || calificacion) ? '⭐' : '☆';
            estrella.className = 'estrella';
            estrella.onclick = () => { calificacion = i; dibujar(); };
            estrella.onmouseenter = () => dibujar(i);
            estrella.onmouseleave = () => dibujar();
            contenedor.appendChild(estrella);
        }
    }
    dibujar();

    botonResena.addEventListener('click', function () {
        if (calificacion < 1) {
            Swal.fire({ icon: 'warning', title: 'Selecciona de 1 a 5 estrellas', confirmButtonColor: '#212529' });
            return;
        }
        this.disabled = true;

        fetch(MOTOSX.baseUrl + '/api/calificar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': MOTOSX.csrf },
            body: JSON.stringify({
                producto_id: <?= (int) $producto['id'] ?>,
                calificacion: calificacion,
                comentario: document.getElementById('comentarioFicha').value.trim()
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Gracias!', text: data.mensaje, timer: 1400, showConfirmButton: false })
                    .then(() => window.location.reload());
            } else {
                this.disabled = false;
                Swal.fire({ icon: 'error', title: 'No se pudo enviar', text: data.mensaje, confirmButtonColor: '#212529' });
            }
        })
        .catch(() => {
            this.disabled = false;
            Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#212529' });
        });
    });
}
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
