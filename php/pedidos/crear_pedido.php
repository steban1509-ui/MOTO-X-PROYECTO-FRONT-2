<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

$data = json_decode(file_get_contents("php://input"), true);

$productos = $data['productos'] ?? [];
$total     = $data['total'] ?? 0;
$nombre    = trim($data['nombre'] ?? ($_SESSION['usuario_nombre'] ?? ''));
$correo    = trim($data['correo'] ?? ($_SESSION['usuario_correo'] ?? ''));
$usuarioId = $_SESSION['usuario_id'] ?? null;

if (empty($productos) || !$nombre || !$correo) {
    echo json_encode(["ok" => false, "mensaje" => "Faltan datos para procesar el pedido."]);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "mensaje" => "El correo electrónico no es válido."]);
    exit;
}

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare(
        "INSERT INTO pedidos (usuario_id, nombre_cliente, correo_cliente, total)
         VALUES (:usuario_id, :nombre, :correo, :total)"
    );

    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':nombre'     => $nombre,
        ':correo'     => $correo,
        ':total'      => $total
    ]);

    $pedidoId = $conexion->lastInsertId();

    $stmtDetalle = $conexion->prepare(
        "INSERT INTO pedido_detalle (pedido_id, producto_nombre, precio, cantidad, imagen)
         VALUES (:pedido_id, :nombre, :precio, :cantidad, :imagen)"
    );

    foreach ($productos as $producto) {
        $stmtDetalle->execute([
            ':pedido_id' => $pedidoId,
            ':nombre'    => $producto['nombre'] ?? '',
            ':precio'    => $producto['precio'] ?? 0,
            ':cantidad'  => $producto['cantidad'] ?? 1,
            ':imagen'    => $producto['imagen'] ?? ''
        ]);
    }

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" => "Pedido registrado correctamente.",
        "pedido_id" => $pedidoId
    ]);

} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(["ok" => false, "mensaje" => "Error al procesar el pedido: " . $e->getMessage()]);
}
