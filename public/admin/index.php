<?php
/**
 * PANEL DEL ADMINISTRADOR
 *   ?seccion=vendedores  → revisar documentos y aprobar / rechazar / suspender
 *   ?seccion=categorias  → (v4.1) crear, editar y eliminar categorías de la tienda
 *   ?seccion=productos   → TODOS los productos (activos e INACTIVOS)
 *   ?seccion=cambios     → (v4) cambios de perfil de vendedores pendientes de aprobación
 *   ?seccion=pedidos     → últimos pedidos con el estado de cada vendedor
 */
require __DIR__ . '/../../app/bootstrap.php';

Auth::requerirRol(Auth::ADMIN);

$secciones = ['vendedores' => 'Vendedores', 'cambios' => 'Cambios de perfil', 'categorias' => 'Categorías', 'productos' => 'Productos', 'pedidos' => 'Pedidos'];
$seccion   = array_key_exists($_GET['seccion'] ?? '', $secciones) ? $_GET['seccion'] : 'vendedores';

// =====================================================================
//  ACCIONES
// =====================================================================
if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'verificar_vendedor') {
        $vendedorId    = (int) ($_POST['vendedor_id'] ?? 0);
        $estado        = (string) ($_POST['estado'] ?? '');
        $observaciones = mb_substr(trim((string) ($_POST['observaciones'] ?? '')), 0, 255);

        if (in_array($estado, ['rechazado', 'suspendido'], true) && $observaciones === '') {
            flash('warning', 'Falta el motivo', 'Escribe el motivo para que el vendedor sepa qué corregir.');
        } elseif (Vendedor::cambiarEstado($vendedorId, $estado, $observaciones, (int) Auth::id())) {
            flash('success', 'Vendedor actualizado', 'Nuevo estado: ' . $estado . '.');
        } else {
            flash('error', 'No se pudo actualizar el vendedor');
        }
    }

    // v4: aprobar / rechazar cambios de perfil de un vendedor
    if ($accion === 'revisar_cambio_perfil') {
        $solicitudId   = (int) ($_POST['solicitud_id'] ?? 0);
        $decision      = (string) ($_POST['decision'] ?? '');
        $observaciones = mb_substr(trim((string) ($_POST['observaciones'] ?? '')), 0, 255);

        if ($decision === 'aprobar') {
            Vendedor::aprobarSolicitud($solicitudId, (int) Auth::id())
                ? flash('success', 'Cambios aprobados', 'Los nuevos datos del vendedor ya son públicos.')
                : flash('error', 'La solicitud ya no está pendiente');
        } elseif ($decision === 'rechazar') {
            if ($observaciones === '') {
                flash('warning', 'Falta el motivo', 'Escribe por qué se rechazan los cambios.');
            } else {
                Vendedor::rechazarSolicitud($solicitudId, (int) Auth::id(), $observaciones)
                    ? flash('success', 'Cambios rechazados', 'El vendedor verá el motivo en su perfil.')
                    : flash('error', 'La solicitud ya no está pendiente');
            }
        }
    }

    // v4.1: crear / editar / eliminar categorías
    if ($accion === 'guardar_categoria' || $accion === 'eliminar_categoria') {
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);

        if ($accion === 'eliminar_categoria') {
            $categoria = Categoria::porId($categoriaId);

            if (Categoria::eliminar($categoriaId)) {
                // Si la imagen la había subido el administrador, se borra del disco
                if ($categoria && strpos((string) $categoria['imagen'], UPLOAD_CATEGORIAS_URL . '/') === 0) {
                    Uploader::eliminar(PUBLIC_PATH . '/' . $categoria['imagen']);
                }
                flash('success', 'Categoría eliminada');
            } else {
                flash('error', 'No se pudo eliminar', 'Solo se pueden eliminar categorías que no tengan productos.');
            }
            redirect('admin/index.php?seccion=categorias');
        }

        $datosCategoria = [
            'nombre'      => trim((string) ($_POST['nombre'] ?? '')),
            'titulo'      => trim((string) ($_POST['titulo'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'orden'       => (int) ($_POST['orden'] ?? 0),
            'imagen'      => null,
        ];
        $erroresCat = [];

        if (mb_strlen($datosCategoria['nombre']) < 3 || mb_strlen($datosCategoria['nombre']) > 100) {
            $erroresCat[] = 'El nombre debe tener entre 3 y 100 caracteres.';
        } elseif (Categoria::existeNombre($datosCategoria['nombre'], $categoriaId ?: null)) {
            $erroresCat[] = 'Ya existe una categoría con ese nombre.';
        }
        if (mb_strlen($datosCategoria['titulo']) > 150) {
            $erroresCat[] = 'El título es demasiado largo.';
        }
        if (mb_strlen($datosCategoria['descripcion']) > 255) {
            $erroresCat[] = 'La descripción no puede superar 255 caracteres.';
        }

        $imagenCategoria = $_FILES['imagen'] ?? null;
        if ($error = Uploader::validar($imagenCategoria, Uploader::IMAGENES, MAX_IMAGEN_BYTES, false)) {
            $erroresCat[] = $error;
        }

        if ($erroresCat) {
            flash('error', 'Revisa la categoría', reset($erroresCat));
            redirect('admin/index.php?seccion=categorias' . ($categoriaId ? '&editar=' . $categoriaId : ''));
        }

        try {
            if ($imagenCategoria && $imagenCategoria['error'] === UPLOAD_ERR_OK) {
                $nombreArchivo = Uploader::guardar($imagenCategoria, UPLOAD_CATEGORIAS_DIR, Uploader::IMAGENES);
                $datosCategoria['imagen'] = UPLOAD_CATEGORIAS_URL . '/' . $nombreArchivo;
            }

            if ($datosCategoria['titulo'] === '') {
                $datosCategoria['titulo'] = $datosCategoria['nombre'];   // encabezado del catálogo
            }

            if ($categoriaId) {
                $datosCategoria['slug'] = Categoria::generarSlug($datosCategoria['nombre'], $categoriaId);
                Categoria::actualizar($categoriaId, $datosCategoria);
                flash('success', 'Categoría actualizada', 'Los cambios ya se ven en la tienda.');
            } else {
                $datosCategoria['slug'] = Categoria::generarSlug($datosCategoria['nombre']);
                Categoria::crear($datosCategoria);
                flash('success', 'Categoría creada', 'Ya aparece en el inicio y los vendedores pueden publicar en ella.');
            }
        } catch (Throwable $ex) {
            error_log((string) $ex);
            flash('error', 'No se pudo guardar la categoría', APP_DEBUG ? $ex->getMessage() : 'Intenta de nuevo.');
        }

        redirect('admin/index.php?seccion=categorias');
    }

    if ($accion === 'estado_producto') {
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $estado     = (string) ($_POST['estado'] ?? '');
        Producto::cambiarEstado($productoId, $estado) // sin vendedor_id: el admin puede cambiar cualquiera
            ? flash('success', 'Producto actualizado', 'El producto quedó ' . $estado . '.')
            : flash('error', 'No se pudo actualizar el producto');
    }

    redirect_actual();
}

// =====================================================================
//  DATOS
// =====================================================================
$stats = [
    'roles'      => Usuario::conteoPorRol(),
    'productos'  => Producto::conteoPorEstado(),
    'pendientes' => Vendedor::contarPendientes(),
    'pedidos'    => Pedido::contar(),
    'cambios'    => Vendedor::contarSolicitudesPendientes(),
    'categorias' => count(Categoria::todas()),
];

$filtroEstado    = in_array($_GET['estado'] ?? '', Producto::ESTADOS, true) ? $_GET['estado'] : null;
$filtroCategoria = (int) ($_GET['categoria'] ?? 0) ?: null;
$filtroVendedor  = in_array($_GET['verificacion'] ?? '', Vendedor::ESTADOS, true) ? $_GET['verificacion'] : null;

if ($seccion === 'vendedores') {
    $vendedores = Vendedor::listarParaAdmin($filtroVendedor);
} elseif ($seccion === 'productos') {
    $productos  = Producto::listarParaAdmin($filtroEstado, $filtroCategoria);
    $categorias = Categoria::todas();
} elseif ($seccion === 'categorias') {
    $categorias       = Categoria::listarParaAdmin();
    $categoriaEditar  = (int) ($_GET['editar'] ?? 0) ? Categoria::porId((int) $_GET['editar']) : null;
} elseif ($seccion === 'cambios') {
    $solicitudes = Vendedor::solicitudesPendientes();
} else {
    $pedidos = Pedido::recientes(50);
}

$badgesVendedor = ['pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger', 'suspendido' => 'bg-secondary'];

$titulo  = 'Panel de administración | MotosX';
$estilos = ['css/panel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>
<div class="container py-5">

    <h2 class="panel-titulo mb-4">Panel de administración</h2>

    <!-- Resumen -->
    <div class="row g-3 mb-4">
        <?php foreach ($stats['roles'] as $rol): ?>
        <div class="col-6 col-lg-2">
            <div class="stat"><div class="numero"><?= (int) $rol['total'] ?></div><div class="etiqueta"><?= e($rol['nombre']) ?>(es)</div></div>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-lg-2">
            <div class="stat"><div class="numero text-warning"><?= $stats['pendientes'] ?></div><div class="etiqueta">Vendedores por revisar</div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat"><div class="numero text-success"><?= $stats['productos']['activo'] ?></div><div class="etiqueta">Productos activos</div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat"><div class="numero text-secondary"><?= $stats['productos']['inactivo'] ?></div><div class="etiqueta">Productos inactivos</div></div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <?php foreach ($secciones as $clave => $nombre): ?>
        <li class="nav-item">
            <a class="nav-link <?= $seccion === $clave ? 'active' : '' ?>" href="<?= e(url('admin/index.php?seccion=' . $clave)) ?>">
                <?= e($nombre) ?>
                <?php if ($clave === 'vendedores' && $stats['pendientes']): ?><span class="badge bg-warning text-dark ms-1"><?= $stats['pendientes'] ?></span><?php endif; ?>
                <?php if ($clave === 'cambios' && $stats['cambios']): ?><span class="badge-circulo ms-1"><?= $stats['cambios'] ?></span><?php endif; ?>
                <?php if ($clave === 'categorias'): ?><span class="badge bg-light text-dark ms-1"><?= $stats['categorias'] ?></span><?php endif; ?>
                <?php if ($clave === 'pedidos'): ?><span class="badge bg-light text-dark ms-1"><?= $stats['pedidos'] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

<?php if ($seccion === 'vendedores'): ?>
    <!-- =========================== VENDEDORES =========================== -->
    <div class="card card-panel">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Vendedores</span>
            <form class="d-flex gap-2" method="get">
                <input type="hidden" name="seccion" value="vendedores">
                <select name="verificacion" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach (Vendedor::ESTADOS as $estado): ?>
                    <option value="<?= e($estado) ?>" <?= $filtroVendedor === $estado ? 'selected' : '' ?>><?= e(ucfirst($estado)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (!$vendedores): ?><p class="text-muted p-4 mb-0">No hay vendedores.</p><?php endif; ?>
            <div class="accordion accordion-flush" id="listaVendedores">
            <?php foreach ($vendedores as $v): $documentosV = Vendedor::documentos((int) $v['id']); ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vend<?= (int) $v['id'] ?>">
                            <span class="me-auto">
                                <strong><?= e($v['razon_social']) ?></strong>
                                <span class="text-muted small ms-2">NIT <?= e($v['nit']) ?> · <?= e($v['ciudad']) ?> · <?= (int) $v['total_productos'] ?> productos</span>
                            </span>
                            <span class="badge <?= e($badgesVendedor[$v['estado_verificacion']]) ?> me-3"><?= e(ucfirst($v['estado_verificacion'])) ?></span>
                        </button>
                    </h2>
                    <div id="vend<?= (int) $v['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#listaVendedores">
                        <div class="accordion-body">
                            <div class="row g-4">
                                <div class="col-md-5">
                                    <dl class="row small mb-0">
                                        <dt class="col-5">Correo</dt><dd class="col-7"><?= e($v['correo']) ?></dd>
                                        <dt class="col-5">Teléfono</dt><dd class="col-7"><?= e($v['telefono_comercial'] ?: $v['telefono']) ?></dd>
                                        <dt class="col-5">Dirección</dt><dd class="col-7"><?= e($v['direccion'] ?: '—') ?></dd>
                                        <dt class="col-5">Representante</dt><dd class="col-7"><?= e($v['representante_legal']) ?></dd>
                                        <dt class="col-5">C.C.</dt><dd class="col-7"><?= e($v['cedula_representante']) ?></dd>
                                        <dt class="col-5">Registro</dt><dd class="col-7"><?= e(fecha_legible($v['fecha_registro'])) ?></dd>
                                        <?php if ($v['observaciones']): ?>
                                        <dt class="col-5">Observaciones</dt><dd class="col-7"><?= e($v['observaciones']) ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="col-md-3">
                                    <p class="fw-bold small mb-2">Documentos (<?= (int) $v['total_documentos'] ?>/4)</p>
                                    <?php foreach (Vendedor::DOCUMENTOS as $tipo => $etiqueta): ?>
                                        <?php if (isset($documentosV[$tipo])): ?>
                                            <a class="d-block small" target="_blank" href="<?= e(url('documento.php?vendedor=' . (int) $v['id'] . '&tipo=' . $tipo)) ?>">
                                                <i class="fa-solid fa-file-lines me-1"></i><?= e($etiqueta) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="d-block small text-danger"><i class="fa-solid fa-xmark me-1"></i><?= e($etiqueta) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <div class="col-md-4">
                                    <form method="post" action="<?= e(url('admin/index.php?seccion=vendedores')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="verificar_vendedor">
                                        <input type="hidden" name="vendedor_id" value="<?= (int) $v['id'] ?>">
                                        <textarea name="observaciones" class="form-control form-control-sm mb-2" rows="2" maxlength="255"
                                                  placeholder="Motivo (obligatorio para rechazar o suspender)"><?= e($v['observaciones']) ?></textarea>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button name="estado" value="aprobado"   class="btn btn-sm btn-success">Aprobar</button>
                                            <button name="estado" value="rechazado"  class="btn btn-sm btn-outline-danger">Rechazar</button>
                                            <button name="estado" value="suspendido" class="btn btn-sm btn-outline-secondary">Suspender</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ($seccion === 'productos'): ?>
    <!-- =========================== PRODUCTOS =========================== -->
    <div class="card card-panel">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Todos los productos (<?= count($productos) ?>)</span>
            <form class="d-flex gap-2" method="get">
                <input type="hidden" name="seccion" value="productos">
                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="activo"   <?= $filtroEstado === 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select>
                <select name="categoria" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $filtroCategoria === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th></th><th>#</th><th>Producto</th><th>Vendedor</th><th>Categoría</th><th class="text-end">Precio</th><th class="text-center">Stock</th><th class="text-center">Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($productos as $p): $activo = $p['estado'] === 'activo'; ?>
                        <tr class="<?= $activo ? '' : 'fila-inactiva' ?>">
                            <td><img class="miniatura" src="<?= e(asset($p['imagen'])) ?>" alt=""></td>
                            <td><?= (int) $p['id'] ?></td>
                            <td><?= e($p['nombre']) ?></td>
                            <td>
                                <?= e($p['vendedor']) ?>
                                <?php if ($p['estado_verificacion'] !== 'aprobado'): ?>
                                    <span class="badge <?= e($badgesVendedor[$p['estado_verificacion']]) ?>"><?= e($p['estado_verificacion']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['categoria']) ?></td>
                            <td class="text-end"><?= e(precio($p['precio'])) ?></td>
                            <td class="text-center"><?= (int) $p['stock'] ?></td>
                            <td class="text-center"><span class="badge <?= $activo ? 'bg-success' : 'bg-secondary' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="text-end">
                                <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="estado_producto">
                                    <input type="hidden" name="producto_id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="estado" value="<?= $activo ? 'inactivo' : 'activo' ?>">
                                    <button class="btn btn-sm <?= $activo ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= $activo ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($seccion === 'categorias'): ?>
    <!-- =========================== CATEGORÍAS (v4.1) =========================== -->
    <div class="row g-4">

        <!-- Formulario: crear / editar -->
        <div class="col-lg-5">
            <div class="card card-panel" id="form-categoria">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fa-solid <?= $categoriaEditar ? 'fa-pen-to-square' : 'fa-folder-plus' ?> me-2"></i>
                        <?= $categoriaEditar ? 'Editar categoría' : 'Nueva categoría' ?>
                    </span>
                    <?php if ($categoriaEditar): ?>
                    <a href="<?= e(url('admin/index.php?seccion=categorias')) ?>" class="btn btn-sm btn-outline-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/index.php?seccion=categorias')) ?>" enctype="multipart/form-data" id="formCategoria" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="guardar_categoria">
                        <input type="hidden" name="categoria_id" value="<?= (int) ($categoriaEditar['id'] ?? 0) ?>">

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required
                                   placeholder="Cascos" value="<?= e($categoriaEditar['nombre'] ?? '') ?>">
                            <div class="form-text">Es el texto que se ve en el inicio y en el menú del vendedor.</div>
                        </div>

                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título del catálogo</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150"
                                   placeholder="Cascos certificados para todos los estilos" value="<?= e($categoriaEditar['titulo'] ?? '') ?>">
                            <div class="form-text">Encabezado de la página de la categoría. Si lo dejas vacío se usa el nombre.</div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="255"
                                      placeholder="Protección certificada para tus viajes."><?= e($categoriaEditar['descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-5">
                                <label for="orden" class="form-label">Orden</label>
                                <input type="number" class="form-control" id="orden" name="orden" min="0" max="99"
                                       value="<?= (int) ($categoriaEditar['orden'] ?? 0) ?>">
                            </div>
                            <div class="col-7">
                                <label for="imagen" class="form-label">Imagen <?= $categoriaEditar ? '(opcional)' : '' ?></label>
                                <input type="file" class="form-control" id="imagen" name="imagen"
                                       accept="image/jpeg,image/png,image/webp">
                            </div>
                        </div>

                        <?php if (!empty($categoriaEditar['imagen'])): ?>
                        <div class="mt-3 d-flex align-items-center gap-2">
                            <img src="<?= e(asset($categoriaEditar['imagen'])) ?>" class="miniatura" alt="">
                            <span class="small text-muted">Imagen actual (se conserva si no subes otra).</span>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-naranja w-100 mt-4">
                            <i class="fa-solid fa-floppy-disk me-2"></i><?= $categoriaEditar ? 'Guardar cambios' : 'Crear categoría' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Listado -->
        <div class="col-lg-7">
            <div class="card card-panel">
                <div class="card-header">Categorías de la tienda (<?= count($categorias) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th></th><th>Nombre</th><th>URL</th><th class="text-center">Orden</th><th class="text-center">Productos</th><th class="text-end">Acciones</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><img class="miniatura" src="<?= e(asset($cat['imagen'])) ?>" alt=""></td>
                                    <td>
                                        <?= e($cat['nombre']) ?>
                                        <div class="small text-muted"><?= e($cat['titulo']) ?></div>
                                    </td>
                                    <td class="small text-muted">?categoria=<?= e($cat['slug']) ?></td>
                                    <td class="text-center"><?= (int) $cat['orden'] ?></td>
                                    <td class="text-center">
                                        <?= (int) $cat['total_productos'] ?>
                                        <div class="small text-success"><?= (int) $cat['productos_activos'] ?> visibles</div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" target="_blank" title="Ver en la tienda"
                                           href="<?= e(url('catalogo.php?categoria=' . urlencode($cat['slug']))) ?>"><i class="fa-solid fa-eye"></i></a>
                                        <a class="btn btn-sm btn-outline-dark" title="Editar"
                                           href="<?= e(url('admin/index.php?seccion=categorias&editar=' . (int) $cat['id'])) ?>#form-categoria"><i class="fa-solid fa-pen"></i></a>
                                        <?php if ((int) $cat['total_productos'] === 0): ?>
                                        <form method="post" action="<?= e(url('admin/index.php?seccion=categorias')) ?>" class="d-inline form-eliminar-categoria" data-nombre="<?= e($cat['nombre']) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="accion" value="eliminar_categoria">
                                            <input type="hidden" name="categoria_id" value="<?= (int) $cat['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-danger" disabled title="Tiene productos: no se puede eliminar"><i class="fa-solid fa-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <p class="small text-muted mt-3">
                <i class="fa-solid fa-circle-info me-1"></i>
                Al crear una categoría aparece de inmediato en el inicio, en el catálogo
                (<code>catalogo.php?categoria=...</code>) y en el formulario de productos del vendedor.
                Solo se pueden eliminar las categorías que no tengan productos.
            </p>
        </div>
    </div>
<?php elseif ($seccion === 'cambios'): ?>
    <!-- =========================== CAMBIOS DE PERFIL (v4) =========================== -->
    <div class="card card-panel">
        <div class="card-header">Cambios de perfil pendientes de aprobación</div>
        <div class="card-body">
            <?php if (!$solicitudes): ?>
                <p class="text-muted mb-0">No hay solicitudes pendientes.</p>
            <?php endif; ?>

            <?php foreach ($solicitudes as $sol): ?>
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                    <strong><?= e($sol['razon_social']) ?> <span class="text-muted fw-normal small">NIT <?= e($sol['nit']) ?></span></strong>
                    <span class="small text-muted">Solicitado: <?= e(fecha_legible($sol['fecha_solicitud'])) ?></span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm mb-3">
                        <thead class="table-light"><tr><th>Campo</th><th>Valor aprobado actual</th><th>Valor solicitado</th></tr></thead>
                        <tbody>
                        <?php foreach (Vendedor::CAMPOS_EDITABLES as $campo => $etiqueta):
                            $cambio = (string) $sol[$campo] !== (string) $sol['actual_' . $campo]; ?>
                            <tr class="<?= $cambio ? 'table-warning' : '' ?>">
                                <td><?= e($etiqueta) ?></td>
                                <td><?= e($sol['actual_' . $campo] ?: '—') ?></td>
                                <td><?= $cambio ? '<strong>' . e($sol[$campo] ?: '—') . '</strong>' : e($sol[$campo] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" action="<?= e(url('admin/index.php?seccion=cambios')) ?>" class="d-flex gap-2 flex-wrap align-items-start">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="revisar_cambio_perfil">
                    <input type="hidden" name="solicitud_id" value="<?= (int) $sol['id'] ?>">
                    <input type="text" name="observaciones" class="form-control form-control-sm" style="max-width:360px" maxlength="255" placeholder="Motivo (obligatorio para rechazar)">
                    <button name="decision" value="aprobar" class="btn btn-sm btn-success"><i class="fa-solid fa-check me-1"></i>Aprobar</button>
                    <button name="decision" value="rechazar" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark me-1"></i>Rechazar</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php else: ?>
    <!-- =========================== PEDIDOS =========================== -->
    <div class="card card-panel">
        <div class="card-header">Últimos pedidos</div>
        <div class="card-body p-0">
            <?php if (!$pedidos): ?><p class="text-muted p-4 mb-0">Todavía no hay pedidos.</p><?php endif; ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <?php if ($pedidos): ?>
                    <thead class="table-light"><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Productos</th><th>Estado por vendedor</th><th class="text-end">Total</th><th class="text-center">Estado general</th></tr></thead>
                    <?php endif; ?>
                    <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?= (int) $pedido['id'] ?></td>
                            <td class="text-nowrap small"><?= e(fecha_legible($pedido['fecha'])) ?></td>
                            <td class="small"><?= e($pedido['nombre_cliente']) ?><br><span class="text-muted"><?= e($pedido['correo_cliente']) ?></span></td>
                            <td class="small">
                                <?php foreach ($pedido['productos'] as $item): ?>
                                    <?= (int) $item['cantidad'] ?> × <?= e($item['producto_nombre']) ?>
                                    <?php if ($item['producto_estado'] !== 'activo'): ?><span class="badge bg-secondary">inactivo</span><?php endif; ?><br>
                                <?php endforeach; ?>
                            </td>
                            <td class="small">
                                <?php foreach ($pedido['vendedores'] as $parte): ?>
                                    <div class="text-nowrap"><?= e($parte['razon_social']) ?>: <?= estado_pedido_badge($parte['estado']) ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end"><?= e(precio($pedido['total'])) ?></td>
                            <td class="text-center"><?= estado_pedido_badge($pedido['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<footer class="bg-light text-center py-4 mt-4">© <?= date('Y') ?> MotosX. Todos los derechos reservados.</footer>
<script>
document.querySelectorAll('.form-eliminar-categoria').forEach(form => {
    form.addEventListener('submit', function (e) {
        if (this.dataset.confirmado) return;
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar ' + this.dataset.nombre + '?',
            text: 'La categoría dejará de aparecer en la tienda.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(r => { if (r.isConfirmed) { this.dataset.confirmado = '1'; this.submit(); } });
    });
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
