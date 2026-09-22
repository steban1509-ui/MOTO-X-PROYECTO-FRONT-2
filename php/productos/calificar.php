<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["ok" => false, "mensaje" => "Debes iniciar sesión para dejar una reseña."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$productoId   = isset($data['producto_id']) ? (int)$data['producto_id'] : 0;
$calificacion = isset($data['calificacion']) ? (int)$data['calificacion'] : 0;
$comentario   = trim($data['comentario'] ?? '');

if ($productoId <= 0 || $calificacion < 1 || $calificacion > 5) {
    echo json_encode(["ok" => false, "mensaje" => "Selecciona una calificación de 1 a 5 estrellas."]);
    exit;
}

try {
    // Un usuario solo puede tener una reseña por producto;
    // si ya calificó antes, se actualiza su reseña.
    $stmt = $conexion->prepare(
        "INSERT INTO resenas (producto_id, usuario_id, nombre_usuario, calificacion, comentario)
         VALUES (:producto_id, :usuario_id, :nombre_usuario, :calificacion, :comentario)
         ON DUPLICATE KEY UPDATE
            calificacion = VALUES(calificacion),
            comentario   = VALUES(comentario),
            fecha        = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        ':producto_id'    => $productoId,
        ':usuario_id'     => $_SESSION['usuario_id'],
        ':nombre_usuario' => $_SESSION['usuario_nombre'],
        ':calificacion'   => $calificacion,
        ':comentario'     => $comentario
    ]);

    echo json_encode(["ok" => true, "mensaje" => "¡Gracias por tu reseña!"]);

} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error al guardar la reseña: " . $e->getMessage()]);
}
