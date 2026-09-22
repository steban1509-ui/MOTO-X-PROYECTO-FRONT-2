<?php
/**
 * POST api/crear_pedido.php   { items: [{id, cantidad}] }
 *
 * Exclusivo de COMPRADORES con sesión iniciada (v4).
 * Precios, disponibilidad y stock se re-validan en el servidor (Pedido::crear).
 * Si una cantidad supera el stock actual, la orden se rechaza completa y se
 * devuelve "sin_stock" con el detalle para que el carrito se ajuste.
 */
require __DIR__ . '/../../app/bootstrap.php';

if (!es_post()) {
    json_response(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
verificar_csrf();
Auth::requerirComprador();   // 401 visitante · 403 vendedor/administrador

$datos   = leer_json();
$usuario = Auth::usuario();
$items   = is_array($datos['items'] ?? null) ? $datos['items'] : [];

try {
    $pedido = Pedido::crear((int) $usuario['id'], $usuario['nombre'], $usuario['correo'], $items);

    json_response([
        'ok'        => true,
        'mensaje'   => 'Pedido registrado correctamente.',
        'pedido_id' => $pedido['id'],
        'total'     => $pedido['total'],
    ]);
} catch (PedidoException $e) {
    json_response([
        'ok'             => false,
        'mensaje'        => $e->getMessage(),
        'no_disponibles' => $e->productosNoDisponibles(),
        'sin_stock'      => $e->productosSinStock(),
    ], 422);
}
