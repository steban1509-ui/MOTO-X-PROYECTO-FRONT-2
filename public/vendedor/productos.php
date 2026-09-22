<?php
/**
 * =====================================================================
 *  MIS PRODUCTOS (VENDEDOR)
 * =====================================================================
 *  Este archivo es a la vez CONTROLADOR y VISTA (Page Controller):
 *    1) Arriba: la lógica PHP que procesa el formulario.
 *    2) Abajo:  el HTML del formulario y del listado.
 *  El formulario se envía a este mismo archivo (action = productos.php).
 *
 *  Acciones (POST "accion"):
 *    - crear           → nuevo producto + subida de imágenes
 *    - actualizar      → editar producto (datos, imágenes nuevas, borrar imágenes)
 *    - cambiar_estado  → Mostrar (activo) / Ocultar (inactivo) en la tienda
 *
 *  Nota: los productos nunca se eliminan. Se desactivan para que sigan
 *  apareciendo en el historial de compras de los clientes.
 * =====================================================================
 */
require __DIR__ . '/../../app/bootstrap.php';

Auth::requerirRol(Auth::VENDEDOR);

$usuarioId = (int) Auth::id();
$vendedor  = Vendedor::porUsuario($usuarioId);

// Cuentas con rol vendedor que aún no enviaron sus documentos (p. ej. migradas de v2)
if (!$vendedor) {
    flash('info', 'Completa tu registro', 'Antes de publicar productos debes enviar los datos y documentos de tu empresa.');
    redirect('registro_vendedor.php');
}

$vendedorId    = (int) $vendedor['id'];
$puedePublicar = Vendedor::puedePublicar($vendedor);
$categorias    = Categoria::todas();
$idsCategorias = array_map('intval', array_column($categorias, 'id'));

$camposVacios = [
    'nombre' => '', 'categoria_id' => '', 'descripcion' => '', 'caracteristicas' => '',
    'precio' => '', 'stock' => '', 'estado' => Producto::ACTIVO,
];
$form    = $camposVacios;
$errores = [];

// ---------------------------------------------------------------------
// ¿Modo edición?  productos.php?editar=ID
// ---------------------------------------------------------------------
$productoEditar = null;
$imagenesEditar = [];
$editarId       = (int) ($_GET['editar'] ?? 0);

if ($editarId > 0) {
    $productoEditar = Producto::delVendedorPorId($editarId, $vendedorId); // valida que sea SUYO
    if (!$productoEditar) {
        flash('error', 'Producto no encontrado', 'El producto no existe o no te pertenece.');
        redirect('vendedor/productos.php');
    }
    $imagenesEditar = Producto::imagenes($editarId);

    $form = array_merge($form, array_intersect_key($productoEditar, $camposVacios));
    $form['caracteristicas'] = implode("\n", lista_caracteristicas($productoEditar['caracteristicas']));
    $form['precio']          = (string) (float) $productoEditar['precio'];
}

// =====================================================================
//  PROCESAMIENTO DEL FORMULARIO
// =====================================================================
if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');

    // -----------------------------------------------------------------
    // A) Cambiar estado Activo / Inactivo
    // -----------------------------------------------------------------
    if ($accion === 'cambiar_estado') {
        $productoId  = (int) ($_POST['producto_id'] ?? 0);
        $nuevoEstado = (string) ($_POST['estado'] ?? '');

        if (!in_array($nuevoEstado, Producto::ESTADOS, true)) {
            flash('error', 'Estado no válido');
        } elseif ($nuevoEstado === Producto::ACTIVO && !$puedePublicar) {
            flash('warning', 'Cuenta en verificación', 'Podrás activar productos cuando un administrador apruebe tu cuenta.');
        } elseif (Producto::cambiarEstado($productoId, $nuevoEstado, $vendedorId)) {
            $nuevoEstado === Producto::INACTIVO
                ? flash('success', 'Producto oculto', 'Ya no aparece en el catálogo, pero sigue visible en el historial de compras de tus clientes.')
                : flash('success', 'Producto visible', 'El producto vuelve a estar visible en el catálogo.');
        } else {
            flash('error', 'No se pudo cambiar el estado', 'El producto no existe o no te pertenece.');
        }
        redirect('vendedor/productos.php#mis-productos');
    }

    // -----------------------------------------------------------------
    // B) Crear o actualizar producto
    // -----------------------------------------------------------------
    if ($accion === 'crear' || $accion === 'actualizar') {

        if (!$puedePublicar) {
            flash('warning', 'Cuenta en verificación', 'Podrás publicar productos cuando un administrador apruebe tu cuenta.');
            redirect('vendedor/productos.php');
        }
        if ($accion === 'actualizar' && !$productoEditar) {
            flash('error', 'Producto no encontrado');
            redirect('vendedor/productos.php');
        }

        // 1. Leer datos
        foreach (array_keys($camposVacios) as $campo) {
            $form[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        // 2. Validar datos
        if (mb_strlen($form['nombre']) < 3 || mb_strlen($form['nombre']) > 150) {
            $errores['nombre'] = 'El nombre debe tener entre 3 y 150 caracteres.';
        }
        if (!in_array((int) $form['categoria_id'], $idsCategorias, true)) {
            $errores['categoria_id'] = 'Selecciona una categoría.';
        }
        if (mb_strlen($form['descripcion']) < 10 || mb_strlen($form['descripcion']) > 255) {
            $errores['descripcion'] = 'La descripción debe tener entre 10 y 255 caracteres.';
        }
        if (mb_strlen($form['caracteristicas']) > 1000) {
            $errores['caracteristicas'] = 'Las características no pueden superar 1000 caracteres.';
        }
        $precio = normalizar_precio($form['precio']); // admite 45000, 45.000 o 45000,50
        if ($precio === null || (float) $precio <= 0 || (float) $precio > 99999999) {
            $errores['precio'] = 'Escribe un precio válido mayor que 0.';
        }
        if (filter_var($form['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]) === false) {
            $errores['stock'] = 'El stock debe ser un número entero de 0 en adelante.';
        }
        if (!in_array($form['estado'], Producto::ESTADOS, true)) {
            $errores['estado'] = 'Estado no válido.';
        }

        // 3. Validar imágenes
        $imagenesNuevas = Uploader::normalizarMultiples($_FILES['imagenes'] ?? null);

        $idsExistentes     = array_map('intval', array_column($imagenesEditar, 'id'));
        $imagenesAEliminar = $accion === 'actualizar'
            ? array_values(array_intersect(array_map('intval', (array) ($_POST['eliminar_imagenes'] ?? [])), $idsExistentes))
            : [];

        $totalFinal = count($idsExistentes) - count($imagenesAEliminar) + count($imagenesNuevas);

        if ($totalFinal < 1) {
            $errores['imagenes'] = 'El producto debe tener al menos una imagen.';
        } elseif ($totalFinal > MAX_IMAGENES_PRODUCTO) {
            $errores['imagenes'] = 'Máximo ' . MAX_IMAGENES_PRODUCTO . ' imágenes por producto.';
        }
        foreach ($imagenesNuevas as $imagen) {
            if ($error = Uploader::validar($imagen, Uploader::IMAGENES, MAX_IMAGEN_BYTES)) {
                $errores['imagenes'] = $error;
                break;
            }
        }

        // 4. Guardar (transacción: si algo falla no queda nada a medias)
        if (!$errores) {
            $datosProducto = [
                'categoria_id'    => (int) $form['categoria_id'],
                'nombre'          => $form['nombre'],
                'descripcion'     => $form['descripcion'],
                // una característica por línea → formato "a|b|c" que ya usaba el proyecto
                'caracteristicas' => implode('|', array_filter(array_map(
                    fn ($linea) => trim(str_replace('|', '/', $linea)),
                    preg_split('/\R/', $form['caracteristicas'])
                ), 'strlen')) ?: null,
                'precio'          => round((float) $precio, 2),
                'stock'           => (int) $form['stock'],
                'estado'          => $form['estado'],
            ];

            $pdo              = Database::conexion();
            $archivosSubidos  = [];
            $archivosABorrar  = [];

            try {
                $pdo->beginTransaction();

                if ($accion === 'crear') {
                    $productoId = Producto::crear($vendedorId, $datosProducto);
                } else {
                    $productoId = $editarId;
                    Producto::actualizar($productoId, $vendedorId, $datosProducto);

                    foreach ($imagenesAEliminar as $imagenId) {
                        $urlBorrada = Producto::eliminarImagen($imagenId, $productoId);
                        // Solo se borran del disco las imágenes subidas por vendedores
                        if ($urlBorrada && strpos($urlBorrada, UPLOAD_PRODUCTOS_URL . '/') === 0) {
                            $archivosABorrar[] = PUBLIC_PATH . '/' . $urlBorrada;
                        }
                    }
                }

                // Subida de imágenes al servidor: public/uploads/productos/
                $orden = Producto::siguienteOrden($productoId);
                foreach ($imagenesNuevas as $imagen) {
                    $nombreArchivo     = Uploader::guardar($imagen, UPLOAD_PRODUCTOS_DIR, Uploader::IMAGENES);
                    $archivosSubidos[] = UPLOAD_PRODUCTOS_DIR . '/' . $nombreArchivo;
                    Producto::agregarImagen($productoId, UPLOAD_PRODUCTOS_URL . '/' . $nombreArchivo, $orden++);
                }

                Producto::sincronizarImagenPrincipal($productoId);
                $pdo->commit();

                foreach ($archivosABorrar as $ruta) {
                    Uploader::eliminar($ruta);
                }

                $accion === 'crear'
                    ? flash('success', '¡Producto publicado!', '"' . $datosProducto['nombre'] . '" se agregó a tu catálogo.')
                    : flash('success', 'Cambios guardados', '"' . $datosProducto['nombre'] . '" se actualizó correctamente.');

                redirect('vendedor/productos.php#mis-productos');

            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($archivosSubidos as $ruta) {
                    Uploader::eliminar($ruta);
                }
                error_log((string) $ex);
                $errores['general'] = APP_DEBUG ? $ex->getMessage() : 'No se pudo guardar el producto. Intenta de nuevo.';
            }
        }

        flash('error', 'Revisa el formulario', reset($errores));
    }
}

// =====================================================================
//  DATOS PARA LA VISTA
// =====================================================================
$misProductos = Producto::delVendedor($vendedorId);   // visibles + ocultos
$conteo       = Producto::conteoPorEstado($vendedorId);

$claseCampo = fn (string $campo): string => isset($errores[$campo]) ? ' is-invalid' : '';
$errorCampo = fn (string $campo): string => isset($errores[$campo])
    ? '<div class="invalid-feedback d-block">' . e($errores[$campo]) . '</div>'
    : '';

$titulo  = 'Mis productos | MotosX';
$estilos = ['css/panel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="panel-titulo mb-0">Mis productos <small>· <?= e($vendedor['razon_social']) ?></small></h2>
        <a href="<?= e(url('vendedor/productos.php')) ?>#form-producto" class="btn btn-naranja <?= $puedePublicar ? '' : 'disabled' ?>">
            <i class="fa-solid fa-plus me-2"></i>Agregar producto
        </a>
    </div>

    <?php vista('partials/nav_vendedor', ['seccionVendedor' => 'productos', 'pendientesNav' => Pedido::contarPendientesVendedor($vendedorId)]); ?>

    <?php if (!$puedePublicar): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-hourglass-half me-2"></i>
        Tu cuenta de vendedor está <strong><?= e($vendedor['estado_verificacion']) ?></strong>.
        Podrás publicar y mostrar productos cuando un administrador la apruebe.
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat"><div class="numero"><?= count($misProductos) ?></div><div class="etiqueta">Productos</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="numero text-success"><?= $conteo['activo'] ?></div><div class="etiqueta">Visibles en la tienda</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="numero text-secondary"><?= $conteo['inactivo'] ?></div><div class="etiqueta">Ocultos</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="numero text-danger"><?= count(array_filter($misProductos, fn ($p) => (int) $p['stock'] === 0)) ?></div><div class="etiqueta">Sin stock</div></div></div>
    </div>

    <!-- ============================================================
         FORMULARIO: AGREGAR / EDITAR PRODUCTO  (con subida de imágenes)
         ============================================================ -->
    <div class="card card-panel mb-4" id="form-producto">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <?php if ($productoEditar): ?>
                    <i class="fa-solid fa-pen-to-square me-2"></i>Editar producto: <?= e($productoEditar['nombre']) ?>
                <?php else: ?>
                    <i class="fa-solid fa-plus me-2"></i>Agregar producto
                <?php endif; ?>
            </span>
            <?php if ($productoEditar): ?>
                <a href="<?= e(url('vendedor/productos.php')) ?>" class="btn btn-sm btn-outline-secondary">Cancelar edición</a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <form method="post"
                  action="<?= e(url('vendedor/productos.php' . ($productoEditar ? '?editar=' . (int) $productoEditar['id'] : ''))) ?>"
                  enctype="multipart/form-data" id="formProducto" novalidate>

                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="<?= $productoEditar ? 'actualizar' : 'crear' ?>">

                <fieldset <?= $puedePublicar ? '' : 'disabled' ?>>
                <div class="row g-3">

                    <div class="col-md-8">
                        <label for="nombre" class="form-label">Nombre del producto *</label>
                        <input type="text" class="form-control<?= $claseCampo('nombre') ?>" id="nombre" name="nombre"
                               maxlength="150" value="<?= e($form['nombre']) ?>" required>
                        <?= $errorCampo('nombre') ?>
                    </div>

                    <div class="col-md-4">
                        <label for="categoria_id" class="form-label">Categoría *</label>
                        <select class="form-select<?= $claseCampo('categoria_id') ?>" id="categoria_id" name="categoria_id" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= (int) $categoria['id'] ?>" <?= (int) $form['categoria_id'] === (int) $categoria['id'] ? 'selected' : '' ?>>
                                <?= e($categoria['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $errorCampo('categoria_id') ?>
                    </div>

                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripción corta * <small class="text-muted">(se ve en la tarjeta)</small></label>
                        <input type="text" class="form-control<?= $claseCampo('descripcion') ?>" id="descripcion" name="descripcion"
                               maxlength="255" value="<?= e($form['descripcion']) ?>" required>
                        <?= $errorCampo('descripcion') ?>
                    </div>

                    <div class="col-12">
                        <label for="caracteristicas" class="form-label">Características <small class="text-muted">(una por línea)</small></label>
                        <textarea class="form-control<?= $claseCampo('caracteristicas') ?>" id="caracteristicas" name="caracteristicas"
                                  rows="3" maxlength="1000" placeholder="Compuesto de goma reforzada&#10;Resistente a pinchazos"><?= e($form['caracteristicas']) ?></textarea>
                        <?= $errorCampo('caracteristicas') ?>
                    </div>

                    <div class="col-md-4">
                        <label for="precio" class="form-label">Precio (COP) *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" inputmode="decimal" class="form-control<?= $claseCampo('precio') ?>" id="precio" name="precio"
                                   placeholder="120000" value="<?= e($form['precio']) ?>" required>
                        </div>
                        <?= $errorCampo('precio') ?>
                    </div>

                    <div class="col-md-4">
                        <label for="stock" class="form-label">Stock *</label>
                        <input type="number" class="form-control<?= $claseCampo('stock') ?>" id="stock" name="stock"
                               min="0" step="1" value="<?= e($form['stock']) ?>" required>
                        <?= $errorCampo('stock') ?>
                    </div>

                    <div class="col-md-4">
                        <label for="estado" class="form-label">Visibilidad *</label>
                        <select class="form-select<?= $claseCampo('estado') ?>" id="estado" name="estado">
                            <option value="activo"   <?= $form['estado'] === 'activo' ? 'selected' : '' ?>>Mostrar (visible en la tienda)</option>
                            <option value="inactivo" <?= $form['estado'] === 'inactivo' ? 'selected' : '' ?>>Ocultar (no visible)</option>
                        </select>
                        <?= $errorCampo('estado') ?>
                    </div>

                    <?php if ($productoEditar && $imagenesEditar): ?>
                    <div class="col-12">
                        <label class="form-label d-block">Imágenes actuales <small class="text-muted">(la primera es la principal)</small></label>
                        <div class="galeria-edicion">
                            <?php foreach ($imagenesEditar as $imagen): ?>
                            <div class="item">
                                <img src="<?= e(asset($imagen['url_imagen'])) ?>" alt="">
                                <div class="form-check d-flex justify-content-center gap-1 mt-1">
                                    <input class="form-check-input" type="checkbox" name="eliminar_imagenes[]"
                                           value="<?= (int) $imagen['id'] ?>" id="imgDel<?= (int) $imagen['id'] ?>">
                                    <label class="form-check-label text-danger" for="imgDel<?= (int) $imagen['id'] ?>">Eliminar</label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label for="imagenes" class="form-label">
                            <?= $productoEditar ? 'Agregar imágenes' : 'Imágenes del producto *' ?>
                            <small class="text-muted">
                                (JPG, PNG o WEBP · máx. <?= MAX_IMAGEN_BYTES / 1048576 ?> MB c/u · hasta <?= MAX_IMAGENES_PRODUCTO ?> en total)
                            </small>
                        </label>
                        <input type="file" class="form-control<?= $claseCampo('imagenes') ?>" id="imagenes" name="imagenes[]"
                               accept="image/jpeg,image/png,image/webp" multiple <?= $productoEditar ? '' : 'required' ?>>
                        <?= $errorCampo('imagenes') ?>
                        <div id="vistaPrevia" class="mt-2"></div>
                    </div>

                    <?= $errorCampo('general') ?>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-naranja px-4" id="btnGuardarProducto">
                            <i class="fa-solid fa-floppy-disk me-2"></i><?= $productoEditar ? 'Guardar cambios' : 'Publicar producto' ?>
                        </button>
                    </div>
                </div>
                </fieldset>
            </form>
        </div>
    </div>

    <!-- ============================================================
         MIS PRODUCTOS (activos e inactivos)
         ============================================================ -->
    <div class="card card-panel" id="mis-productos">
        <div class="card-header"><i class="fa-solid fa-boxes-stacked me-2"></i>Mis productos (<?= count($misProductos) ?>)</div>
        <div class="card-body p-0">
            <?php if (!$misProductos): ?>
                <p class="text-muted p-4 mb-0">Aún no has publicado productos.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Visibilidad</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($misProductos as $producto):
                        $activo = $producto['estado'] === Producto::ACTIVO; ?>
                        <tr class="<?= $activo ? '' : 'fila-inactiva' ?>">
                            <td><img class="miniatura" src="<?= e(asset($producto['imagen'])) ?>" alt=""></td>
                            <td>
                                <?= e($producto['nombre']) ?>
                                <?php if ((int) $producto['veces_vendido'] > 0): ?>
                                    <div class="small text-muted">En <?= (int) $producto['veces_vendido'] ?> pedido(s)</div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($producto['categoria']) ?></td>
                            <td class="text-end"><?= e(precio($producto['precio'])) ?></td>
                            <td class="text-center"><?= (int) $producto['stock'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= $activo ? 'bg-success' : 'bg-secondary' ?>"><?= $activo ? 'Visible' : 'Oculto' ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= e(url('vendedor/productos.php?editar=' . (int) $producto['id'])) ?>#form-producto"
                                   class="btn btn-sm btn-outline-dark" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="post" action="<?= e(url('vendedor/productos.php')) ?>" class="d-inline form-estado"
                                      data-nombre="<?= e($producto['nombre']) ?>" data-activo="<?= $activo ? '1' : '0' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                                    <input type="hidden" name="estado" value="<?= $activo ? 'inactivo' : 'activo' ?>">
                                    <button type="submit" class="btn btn-sm <?= $activo ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <i class="fa-solid <?= $activo ? 'fa-eye-slash' : 'fa-eye' ?> me-1"></i><?= $activo ? 'Ocultar' : 'Mostrar' ?>
                                    </button>
                                </form>
                            </td>
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

<script>
const MAX_IMAGEN_BYTES = <?= MAX_IMAGEN_BYTES ?>;

// Vista previa de las imágenes seleccionadas
document.getElementById('imagenes').addEventListener('change', function () {
    const contenedor = document.getElementById('vistaPrevia');
    contenedor.innerHTML = '';
    Array.from(this.files).forEach(archivo => {
        if (!archivo.type.startsWith('image/')) return;
        const img = document.createElement('img');
        img.src = URL.createObjectURL(archivo);
        img.title = archivo.name;
        img.onload = () => URL.revokeObjectURL(img.src);
        contenedor.appendChild(img);
    });
});

// Validación rápida en el navegador (el servidor valida de nuevo)
document.getElementById('formProducto').addEventListener('submit', function (e) {
    const archivos = Array.from(document.getElementById('imagenes').files);
    const pesado = archivos.find(a => a.size > MAX_IMAGEN_BYTES);

    if (!this.checkValidity() || pesado) {
        e.preventDefault();
        this.classList.add('was-validated');
        Swal.fire({
            icon: 'warning',
            title: pesado ? 'Imagen muy pesada' : 'Faltan datos',
            text: pesado ? pesado.name + ' supera el tamaño máximo.' : 'Completa los campos obligatorios.',
            confirmButtonColor: '#212529'
        });
        return;
    }
    document.getElementById('btnGuardarProducto').disabled = true;
});

// Confirmación con SweetAlert antes de activar / desactivar
document.querySelectorAll('.form-estado').forEach(form => {
    form.addEventListener('submit', function (e) {
        if (this.dataset.confirmado) return;
        e.preventDefault();
        const desactivar = this.dataset.activo === '1';
        Swal.fire({
            icon: 'question',
            title: (desactivar ? '¿Ocultar ' : '¿Mostrar ') + '"' + this.dataset.nombre + '"?',
            text: desactivar
                ? 'Dejará de mostrarse en el catálogo. Los clientes que ya lo compraron lo seguirán viendo en su historial.'
                : 'El producto volverá a mostrarse en el catálogo.',
            showCancelButton: true,
            confirmButtonText: desactivar ? 'Sí, ocultar' : 'Sí, mostrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: desactivar ? '#dc3545' : '#198754'
        }).then(r => {
            if (r.isConfirmed) {
                this.dataset.confirmado = '1';
                this.submit();
            }
        });
    });
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
