<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["ok" => false, "mensaje" => "Debes iniciar sesión para ver tus pedidos."]);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

try {
    $stmt = $conexion->prepare(
        "SELECT id, total, estado, fecha
         FROM pedidos
         WHERE usuario_id = :usuario_id
         ORDER BY fecha DESC"
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    $pedidos = $stmt->fetchAll();

    $stmtDetalle = $conexion->prepare(
        "SELECT id, producto_nombre, precio, cantidad, imagen
         FROM pedido_detalle
         WHERE pedido_id = :pedido_id"
    );

    foreach ($pedidos as &$pedido) {

        $stmtDetalle->execute([':pedido_id' => $pedido['id']]);
        $pedido['productos'] = $stmtDetalle->fetchAll();

        $horas = (strtotime('now') - strtotime($pedido['fecha'])) / 3600;

        $pedido['editable'] = ($horas <= 24) && $pedido['estado'] !== 'cancelado';
        $pedido['horas_restantes'] = $pedido['editable'] ? round(24 - $horas, 1) : 0;
    }
    unset($pedido);

    echo json_encode(["ok" => true, "pedidos" => $pedidos]);

} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error al obtener los pedidos: " . $e->getMessage()]);
}
