<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(["ok" => false, "mensaje" => "Producto no válido."]);
    exit;
}

try {
    $stmt = $conexion->prepare(
        "SELECT id, nombre, descripcion, precio, imagen,
                vendedor_nombre, vendedor_ubicacion, caracteristicas
         FROM productos
         WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        echo json_encode(["ok" => false, "mensaje" => "Producto no encontrado."]);
        exit;
    }

    // Galería de imágenes (hasta 5), con la imagen principal como respaldo
    $stmtImg = $conexion->prepare(
        "SELECT url_imagen FROM producto_imagenes
         WHERE producto_id = :id
         ORDER BY orden ASC
         LIMIT 5"
    );
    $stmtImg->execute([':id' => $id]);
    $imagenes = array_column($stmtImg->fetchAll(), 'url_imagen');

    if (empty($imagenes)) {
        $imagenes = [$producto['imagen']];
    }

    // Reseñas del producto
    $stmtResenas = $conexion->prepare(
        "SELECT nombre_usuario, calificacion, comentario, fecha
         FROM resenas
         WHERE producto_id = :id
         ORDER BY fecha DESC"
    );
    $stmtResenas->execute([':id' => $id]);
    $resenas = $stmtResenas->fetchAll();

    $totalResenas = count($resenas);
    $promedio = 0;

    if ($totalResenas > 0) {
        $suma = array_sum(array_column($resenas, 'calificacion'));
        $promedio = round($suma / $totalResenas, 1);
    }

    $caracteristicas = $producto['caracteristicas']
        ? array_map('trim', explode('|', $producto['caracteristicas']))
        : [];

    echo json_encode([
        "ok" => true,
        "producto" => [
            "id"                    => (int)$producto['id'],
            "nombre"                => $producto['nombre'],
            "descripcion"           => $producto['descripcion'],
            "precio"                => $producto['precio'],
            "imagen"                => $producto['imagen'],
            "imagenes"              => $imagenes,
            "vendedor_nombre"       => $producto['vendedor_nombre'],
            "vendedor_ubicacion"    => $producto['vendedor_ubicacion'],
            "caracteristicas"       => $caracteristicas,
            "promedio_calificacion" => $promedio,
            "total_resenas"         => $totalResenas,
            "resenas"               => $resenas
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error al obtener el producto: " . $e->getMessage()]);
}
