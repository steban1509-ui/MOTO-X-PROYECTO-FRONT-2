<?php
require __DIR__ . '/../../app/bootstrap.php';

$ids = array_slice(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')))), 0, 100);

$productos = [];
foreach (Producto::publicosPorIds($ids) as $id => $producto) {
    $productos[$id] = [
        'id'     => $id,
        'nombre' => $producto['nombre'],
        'precio' => (float) $producto['precio'],
        'stock'  => max(0, (int) $producto['stock']),
    ];
}

json_response(['ok' => true, 'productos' => (object) $productos]);
