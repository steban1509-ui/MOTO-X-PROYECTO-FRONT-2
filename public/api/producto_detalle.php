<?php
/**
 * GET api/producto_detalle.php?id=5
 * Detalle para el modal "Ver producto". Solo responde productos ACTIVOS.
 */
require __DIR__ . '/../../app/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$producto = $id > 0 ? Producto::publicoPorId($id) : null;

if (!$producto) {
    json_response(['ok' => false, 'mensaje' => 'Este producto no existe o ya no está disponible.'], 404);
}

$imagenes = array_map(
    fn ($fila) => asset($fila['url_imagen']),
    array_slice(Producto::imagenes($id), 0, MAX_IMAGENES_PRODUCTO)
);
if (!$imagenes) {
    $imagenes = [asset($producto['imagen'])];
}

$resenas  = Resena::deProducto($id);
$total    = count($resenas);
$promedio = $total ? round(array_sum(array_column($resenas, 'calificacion')) / $total, 1) : 0;

json_response([
    'ok' => true,
    'producto' => [
        'id'                    => (int) $producto['id'],
        'nombre'                => $producto['nombre'],
        'descripcion'           => $producto['descripcion'],
        'precio'                => (float) $producto['precio'],
        'stock'                 => (int) $producto['stock'],
        'imagen'                => asset($producto['imagen']),
        'imagenes'              => $imagenes,
        'vendedor_nombre'       => $producto['vendedor_nombre'],
        'vendedor_ubicacion'    => $producto['vendedor_ubicacion'],
        'caracteristicas'       => lista_caracteristicas($producto['caracteristicas']),
        'promedio_calificacion' => $promedio,
        'total_resenas'         => $total,
        'resenas'               => array_map(fn ($r) => array_merge($r, ['calificacion' => (int) $r['calificacion']]), $resenas),
    ],
]);
