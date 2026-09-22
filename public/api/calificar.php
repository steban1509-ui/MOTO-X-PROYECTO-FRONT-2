<?php
/**
 * POST api/calificar.php   { producto_id, calificacion, comentario }
 */
require __DIR__ . '/../../app/bootstrap.php';

if (!es_post()) {
    json_response(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
verificar_csrf();

// v4: solo los compradores dejan reseñas (vendedores y admins reciben 403)
Auth::requerirComprador();

$datos        = leer_json();
$productoId   = (int) ($datos['producto_id'] ?? 0);
$calificacion = (int) ($datos['calificacion'] ?? 0);
$comentario   = mb_substr(trim((string) ($datos['comentario'] ?? '')), 0, 1000);

if ($calificacion < 1 || $calificacion > 5) {
    json_response(['ok' => false, 'mensaje' => 'Selecciona una calificación de 1 a 5 estrellas.'], 422);
}
if (!Producto::publicoPorId($productoId)) {
    json_response(['ok' => false, 'mensaje' => 'Este producto ya no está disponible.'], 404);
}

$usuario = Auth::usuario();
Resena::guardar($productoId, (int) $usuario['id'], $usuario['nombre'], $calificacion, $comentario);

json_response(['ok' => true, 'mensaje' => '¡Gracias por tu reseña!']);
