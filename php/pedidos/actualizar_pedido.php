<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(["ok" => false, "mensaje" => "Debes iniciar sesión."]);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$data = json_decode(file_get_contents("php://input"), true);

$pedidoId = isset($data['pedido_id']) ? (int)$data['pedido_id'] : 0;
$items    = $data['items'] ?? []; // [{ detalle_id, cantidad }]

if ($pedidoId <= 0 || empty($items)) {
    echo json_encode(["ok" => false, "mensaje" => "Datos incompletos para actualizar el pedido."]);
    exit;
}

try {
    // El pedido debe pertenecer al usuario logueado
    $stmt = $conexion->prepare(
        "SELECT id, fecha, estado FROM pedidos WHERE id = :id AND usuario_id = :usuario_id"
    );
    $stmt->execute([':id' => $pedidoId, ':usuario_id' => $usuarioId]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        echo json_encode(["ok" => false, "mensaje" => "Pedido no encontrado."]);
        exit;
    }

    // Regla de negocio: solo se puede modificar dentro de las primeras 24 horas.
    // Esta validación se hace aquí en el servidor, no solo en el front-end.
    $horas = (strtotime('now') - strtotime($pedido['fecha'])) / 3600;

    if ($horas > 24) {
        echo json_encode(["ok" => false, "mensaje" => "Ya pasaron más de 24 horas, este pedido ya no se puede modificar."]);
        exit;
    }

    if ($pedido['estado'] === 'cancelado') {
        echo json_encode(["ok" => false, "mensaje" => "Este pedido está cancelado y no se puede modificar."]);
        exit;
    }

    $conexion->beginTransaction();

    $stmtUpdate = $conexion->prepare(
        "UPDATE pedido_detalle SET cantidad = :cantidad WHERE id = :id AND pedido_id = :pedido_id"
    );
    $stmtDelete = $conexion->prepare(
        "DELETE FROM pedido_detalle WHERE id = :id AND pedido_id = :pedido_id"
    );

    foreach ($items as $item) {

        $detalleId = (int)($item['detalle_id'] ?? 0);
        $cantidad  = (int)($item['cantidad'] ?? 0);

        if ($detalleId <= 0) {
            continue;
        }

        if ($cantidad <= 0) {
            $stmtDelete->execute([':id' => $detalleId, ':pedido_id' => $pedidoId]);
        } else {
            $stmtUpdate->execute([':cantidad' => $cantidad, ':id' => $detalleId, ':pedido_id' => $pedidoId]);
        }
    }

    // Recalcular el total del pedido con lo que haya quedado
    $stmtTotal = $conexion->prepare(
        "SELECT COALESCE(SUM(precio * cantidad), 0) AS nuevo_total
         FROM pedido_detalle
         WHERE pedido_id = :pedido_id"
    );
    $stmtTotal->execute([':pedido_id' => $pedidoId]);
    $nuevoTotal = $stmtTotal->fetch()['nuevo_total'];

    $nuevoEstado = ($nuevoTotal == 0) ? 'cancelado' : $pedido['estado'];

    $stmtActualizar = $conexion->prepare(
        "UPDATE pedidos SET total = :total, estado = :estado WHERE id = :id"
    );
    $stmtActualizar->execute([
        ':total'  => $nuevoTotal,
        ':estado' => $nuevoEstado,
        ':id'     => $pedidoId
    ]);

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" => "Pedido actualizado correctamente.",
        "nuevo_total" => $nuevoTotal
    ]);

} catch (PDOException $e) {
    $conexion->rollBack();
    echo json_encode(["ok" => false, "mensaje" => "Error al actualizar el pedido: " . $e->getMessage()]);
}
