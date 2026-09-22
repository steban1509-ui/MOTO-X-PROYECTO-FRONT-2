<?php
/**
 * Modelo Pedido (v4).
 *
 * Un pedido del comprador se divide en "partes" por vendedor (tabla pedidos_vendedor).
 * Cada vendedor aprueba, rechaza o completa SU parte. El estado general del pedido
 * (pedidos.estado) se recalcula a partir de esas partes.
 *
 *   pendiente ──► aprobado ──► completado   (venta realizada → Historial de ventas)
 *       │            │
 *       └────────────┴──► rechazado          (el stock se devuelve)
 *
 * STOCK: se descuenta al crear el pedido (reserva), se devuelve si el vendedor
 * rechaza o si el comprador reduce cantidades, y se vuelve a descontar si un
 * pedido rechazado se reactiva.
 */
final class Pedido
{
    public const PENDIENTE  = 'pendiente';
    public const APROBADO   = 'aprobado';
    public const RECHAZADO  = 'rechazado';
    public const COMPLETADO = 'completado';
    public const CANCELADO  = 'cancelado';

    /** Estados que puede tener la parte de un vendedor. */
    public const ESTADOS_VENDEDOR = [self::PENDIENTE, self::APROBADO, self::RECHAZADO, self::COMPLETADO];

    /** Estados que se muestran en el módulo "Pedidos pendientes". */
    public const ESTADOS_GESTION = [self::PENDIENTE, self::APROBADO, self::RECHAZADO];

    // =================================================================
    // CREAR PEDIDO (comprador)
    // =================================================================

    /**
     * Crea el pedido a partir del carrito.
     * Precios, disponibilidad y STOCK se validan SIEMPRE contra la base de datos.
     *
     * @param array $items [['id' => 5, 'cantidad' => 2], ...]
     * @throws PedidoException
     */
    public static function crear(int $usuarioId, string $nombre, string $correo, array $items): array
    {
        $cantidades = [];
        foreach ($items as $item) {
            $id       = (int) ($item['id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            if ($id > 0 && $cantidad > 0) {
                $cantidades[$id] = ($cantidades[$id] ?? 0) + $cantidad;
            }
        }
        if (!$cantidades) {
            throw new PedidoException('Tu carrito está vacío.');
        }

        return Database::transaccion(function () use ($usuarioId, $nombre, $correo, $cantidades) {
            $productos = Producto::publicosPorIds(array_keys($cantidades));

            // 1. Productos que ya no están activos
            $noDisponibles = array_values(array_diff(array_keys($cantidades), array_keys($productos)));
            if ($noDisponibles) {
                throw new PedidoException(
                    'Algunos productos de tu carrito ya no están disponibles y fueron retirados.',
                    $noDisponibles
                );
            }

            // 2. Re-validación estricta de stock (antes de escribir nada)
            $sinStock = [];
            foreach ($cantidades as $id => $cantidad) {
                if ($cantidad > (int) $productos[$id]['stock']) {
                    $sinStock[] = self::infoSinStock($productos[$id], $cantidad);
                }
            }
            if ($sinStock) {
                throw new PedidoException(self::mensajeSinStock($sinStock), [], $sinStock);
            }

            // 3. Cabecera del pedido
            $total = 0.0;
            foreach ($cantidades as $id => $cantidad) {
                $total += (float) $productos[$id]['precio'] * $cantidad;
            }

            $ahora = date('Y-m-d H:i:s');
            Database::consulta(
                'INSERT INTO pedidos (usuario_id, nombre_cliente, correo_cliente, total, estado, fecha)
                 VALUES (:usuario_id, :nombre, :correo, :total, :estado, :fecha)',
                [
                    ':usuario_id' => $usuarioId,
                    ':nombre'     => $nombre,
                    ':correo'     => $correo,
                    ':total'      => $total,
                    ':estado'     => self::PENDIENTE,
                    ':fecha'      => $ahora,
                ]
            );
            $pedidoId = (int) Database::conexion()->lastInsertId();

            // 4. Líneas del pedido + descuento de stock
            $subtotales = [];
            foreach ($cantidades as $id => $cantidad) {
                $producto = $productos[$id];

                // UPDATE ... WHERE stock >= cantidad: protege contra compras simultáneas
                if (!Producto::descontarStock($id, $cantidad)) {
                    $actual = Producto::publicosPorIds([$id])[$id] ?? ['id' => $id, 'nombre' => $producto['nombre'], 'stock' => 0];
                    $info   = [self::infoSinStock($actual, $cantidad)];
                    throw new PedidoException(self::mensajeSinStock($info), [], $info);
                }

                Database::consulta(
                    'INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
                     VALUES (:pedido_id, :producto_id, :vendedor_id, :nombre, :precio, :cantidad, :imagen)',
                    [
                        ':pedido_id'   => $pedidoId,
                        ':producto_id' => $id,
                        ':vendedor_id' => $producto['vendedor_id'],
                        ':nombre'      => $producto['nombre'],
                        ':precio'      => $producto['precio'],
                        ':cantidad'    => $cantidad,
                        ':imagen'      => $producto['imagen'],
                    ]
                );

                $vendedorId = (int) $producto['vendedor_id'];
                $subtotales[$vendedorId] = ($subtotales[$vendedorId] ?? 0) + (float) $producto['precio'] * $cantidad;
            }

            // 5. Una "parte" del pedido por vendedor, en estado pendiente
            foreach ($subtotales as $vendedorId => $subtotal) {
                Database::consulta(
                    'INSERT INTO pedidos_vendedor (pedido_id, vendedor_id, subtotal, estado, fecha_actualizacion)
                     VALUES (:pedido_id, :vendedor_id, :subtotal, :estado, :fecha)',
                    [
                        ':pedido_id'   => $pedidoId,
                        ':vendedor_id' => $vendedorId,
                        ':subtotal'    => $subtotal,
                        ':estado'      => self::PENDIENTE,
                        ':fecha'       => $ahora,
                    ]
                );
            }

            return ['id' => $pedidoId, 'total' => $total];
        });
    }

    private static function infoSinStock(array $producto, int $solicitado): array
    {
        return [
            'id'         => (int) $producto['id'],
            'nombre'     => $producto['nombre'],
            'solicitado' => $solicitado,
            'disponible' => max(0, (int) $producto['stock']),
        ];
    }

    private static function mensajeSinStock(array $sinStock): string
    {
        $lineas = array_map(
            fn ($p) => '"' . $p['nombre'] . '": pediste ' . $p['solicitado'] . ', disponibles ' . $p['disponible'],
            $sinStock
        );
        return 'Stock insuficiente. ' . implode(' · ', $lineas) . '.';
    }

    // =================================================================
    // COMPRADOR: historial y edición
    // =================================================================

    /**
     * HISTORIAL DE COMPRAS DEL USUARIO
     * LEFT JOIN a productos SIN filtrar por estado: los productos que el
     * vendedor desactivó siguen apareciendo en las compras del usuario.
     */
    public static function historialUsuario(int $usuarioId): array
    {
        $pedidos = Database::consulta(
            'SELECT id, total, estado, fecha FROM pedidos WHERE usuario_id = :usuario_id ORDER BY fecha DESC, id DESC',
            [':usuario_id' => $usuarioId]
        )->fetchAll();

        return self::adjuntarDetalle($pedidos);
    }

    /** Últimos pedidos de toda la tienda (panel admin). */
    public static function recientes(int $limite = 50): array
    {
        $pedidos = Database::consulta(
            'SELECT id, usuario_id, nombre_cliente, correo_cliente, total, estado, fecha
             FROM pedidos ORDER BY fecha DESC, id DESC LIMIT ' . max(1, $limite)
        )->fetchAll();

        return self::adjuntarDetalle($pedidos);
    }

    private static function adjuntarDetalle(array $pedidos): array
    {
        if (!$pedidos) {
            return [];
        }

        $ids        = array_map('intval', array_column($pedidos, 'id'));
        $marcadores = implode(',', array_fill(0, count($ids), '?'));

        $detalles = Database::consulta(
            "SELECT d.id, d.pedido_id, d.producto_id, d.vendedor_id, d.producto_nombre, d.precio, d.cantidad, d.imagen,
                    p.estado  AS producto_estado,
                    pv.estado AS estado_vendedor
             FROM pedido_detalle d
             LEFT JOIN productos p         ON p.id = d.producto_id
             LEFT JOIN pedidos_vendedor pv ON pv.pedido_id = d.pedido_id AND pv.vendedor_id = d.vendedor_id
             WHERE d.pedido_id IN ($marcadores)
             ORDER BY d.id",
            $ids
        )->fetchAll();

        $partes = Database::consulta(
            "SELECT pv.pedido_id, pv.vendedor_id, pv.subtotal, pv.estado, v.razon_social
             FROM pedidos_vendedor pv
             INNER JOIN vendedores v ON v.id = pv.vendedor_id
             WHERE pv.pedido_id IN ($marcadores)
             ORDER BY v.razon_social",
            $ids
        )->fetchAll();

        $detallePorPedido = [];
        foreach ($detalles as $detalle) {
            $detallePorPedido[(int) $detalle['pedido_id']][] = $detalle;
        }
        $partesPorPedido = [];
        foreach ($partes as $parte) {
            $partesPorPedido[(int) $parte['pedido_id']][] = $parte;
        }

        foreach ($pedidos as &$pedido) {
            $pedido['productos']       = $detallePorPedido[(int) $pedido['id']] ?? [];
            $pedido['vendedores']      = $partesPorPedido[(int) $pedido['id']] ?? [];
            $pedido['editable']        = self::esEditable($pedido);
            $pedido['horas_restantes'] = $pedido['editable'] ? self::horasRestantes($pedido) : 0;
        }
        unset($pedido);

        return $pedidos;
    }

    /** El comprador solo puede reducir un pedido pendiente dentro de las primeras 24 h. */
    public static function esEditable(array $pedido): bool
    {
        return $pedido['estado'] === self::PENDIENTE && self::horasRestantes($pedido) > 0;
    }

    public static function horasRestantes(array $pedido): float
    {
        $horas = (time() - strtotime($pedido['fecha'])) / 3600;
        return max(0, round(HORAS_EDICION_PEDIDO - $horas, 1));
    }

    /**
     * Permite BAJAR cantidades (o quitar productos) dentro de las primeras 24 h.
     * Solo afecta líneas cuyo vendedor aún no ha gestionado el pedido (pendiente).
     *
     * @param array $cantidades [detalle_id => nueva_cantidad]
     */
    public static function reducirCantidades(int $pedidoId, int $usuarioId, array $cantidades): float
    {
        return Database::transaccion(function () use ($pedidoId, $usuarioId, $cantidades) {
            $pedido = Database::consulta(
                'SELECT id, fecha, estado FROM pedidos WHERE id = :id AND usuario_id = :usuario_id',
                [':id' => $pedidoId, ':usuario_id' => $usuarioId]
            )->fetch();

            if (!$pedido) {
                throw new PedidoException('Pedido no encontrado.');
            }
            if (!self::esEditable($pedido)) {
                throw new PedidoException('Este pedido ya no se puede modificar (pasaron más de ' . HORAS_EDICION_PEDIDO . ' horas o ya fue gestionado por el vendedor).');
            }

            $detalles = Database::consulta(
                'SELECT d.id, d.producto_id, d.cantidad, pv.estado AS estado_vendedor
                 FROM pedido_detalle d
                 LEFT JOIN pedidos_vendedor pv ON pv.pedido_id = d.pedido_id AND pv.vendedor_id = d.vendedor_id
                 WHERE d.pedido_id = :pedido_id',
                [':pedido_id' => $pedidoId]
            )->fetchAll();

            foreach ($detalles as $detalle) {
                $detalleId = (int) $detalle['id'];
                if (!array_key_exists($detalleId, $cantidades) || $detalle['estado_vendedor'] !== self::PENDIENTE) {
                    continue;
                }
                $actual = (int) $detalle['cantidad'];
                $nueva  = max(0, min($actual, (int) $cantidades[$detalleId])); // solo se puede bajar

                if ($nueva === $actual) {
                    continue;
                }
                if ($nueva === 0) {
                    Database::consulta('DELETE FROM pedido_detalle WHERE id = :id', [':id' => $detalleId]);
                } else {
                    Database::consulta('UPDATE pedido_detalle SET cantidad = :cantidad WHERE id = :id', [':cantidad' => $nueva, ':id' => $detalleId]);
                }
                if ($detalle['producto_id']) {
                    Producto::devolverStock((int) $detalle['producto_id'], $actual - $nueva);
                }
            }

            self::recalcularSubtotales($pedidoId);

            $total = (float) Database::consulta(
                'SELECT COALESCE(SUM(precio * cantidad), 0) FROM pedido_detalle WHERE pedido_id = :pedido_id',
                [':pedido_id' => $pedidoId]
            )->fetchColumn();

            Database::consulta('UPDATE pedidos SET total = :total WHERE id = :id', [':total' => $total, ':id' => $pedidoId]);

            if ($total <= 0) {
                Database::consulta('UPDATE pedidos SET estado = :estado WHERE id = :id', [':estado' => self::CANCELADO, ':id' => $pedidoId]);
            } else {
                self::recalcularEstadoGeneral($pedidoId);
            }

            return $total;
        });
    }

    /** Recalcula el subtotal de cada vendedor y borra las partes que quedaron sin productos. */
    private static function recalcularSubtotales(int $pedidoId): void
    {
        $partes = Database::consulta(
            'SELECT id, vendedor_id FROM pedidos_vendedor WHERE pedido_id = :pedido_id',
            [':pedido_id' => $pedidoId]
        )->fetchAll();

        foreach ($partes as $parte) {
            $subtotal = Database::consulta(
                'SELECT COUNT(*) AS lineas, COALESCE(SUM(precio * cantidad), 0) AS subtotal
                 FROM pedido_detalle WHERE pedido_id = :pedido_id AND vendedor_id = :vendedor_id',
                [':pedido_id' => $pedidoId, ':vendedor_id' => $parte['vendedor_id']]
            )->fetch();

            if ((int) $subtotal['lineas'] === 0) {
                Database::consulta('DELETE FROM pedidos_vendedor WHERE id = :id', [':id' => $parte['id']]);
            } else {
                Database::consulta('UPDATE pedidos_vendedor SET subtotal = :subtotal WHERE id = :id', [
                    ':subtotal' => $subtotal['subtotal'],
                    ':id'       => $parte['id'],
                ]);
            }
        }
    }

    // =================================================================
    // VENDEDOR: pedidos pendientes, historial de ventas y cambio de estado
    // =================================================================

    /**
     * Pedidos que el vendedor debe gestionar (Pendiente / Aprobado / Rechazada).
     * $estado permite filtrar uno solo.
     */
    public static function pedidosDelVendedor(int $vendedorId, ?string $estado = null): array
    {
        $estados = in_array($estado, self::ESTADOS_GESTION, true) ? [$estado] : self::ESTADOS_GESTION;
        return self::partesDelVendedor($vendedorId, $estados, "CASE pv.estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 ELSE 2 END, p.fecha DESC");
    }

    /** HISTORIAL DE VENTAS: filtro estricto, SOLO ventas completadas. */
    public static function ventasCompletadas(int $vendedorId): array
    {
        return self::partesDelVendedor($vendedorId, [self::COMPLETADO], 'COALESCE(pv.fecha_actualizacion, p.fecha) DESC');
    }

    public static function contarPendientesVendedor(int $vendedorId): int
    {
        return (int) Database::consulta(
            "SELECT COUNT(*)
             FROM pedidos_vendedor pv
             INNER JOIN pedidos p ON p.id = pv.pedido_id
             WHERE pv.vendedor_id = :vendedor_id AND pv.estado = 'pendiente' AND p.estado <> 'cancelado'",
            [':vendedor_id' => $vendedorId]
        )->fetchColumn();
    }

    /** Cantidad de pedidos del vendedor por estado (para los filtros). */
    public static function conteoVendedorPorEstado(int $vendedorId): array
    {
        $conteo = array_fill_keys(self::ESTADOS_VENDEDOR, 0);
        $filas  = Database::consulta(
            "SELECT pv.estado, COUNT(*) AS total
             FROM pedidos_vendedor pv
             INNER JOIN pedidos p ON p.id = pv.pedido_id
             WHERE pv.vendedor_id = :vendedor_id AND p.estado <> 'cancelado'
             GROUP BY pv.estado",
            [':vendedor_id' => $vendedorId]
        )->fetchAll();
        foreach ($filas as $fila) {
            $conteo[$fila['estado']] = (int) $fila['total'];
        }
        return $conteo;
    }

    private static function partesDelVendedor(int $vendedorId, array $estados, string $orden): array
    {
        $marcadores = implode(',', array_fill(0, count($estados), '?'));

        $partes = Database::consulta(
            "SELECT pv.id, pv.pedido_id, pv.subtotal, pv.estado, pv.fecha_actualizacion,
                    p.fecha, p.nombre_cliente, p.correo_cliente
             FROM pedidos_vendedor pv
             INNER JOIN pedidos p ON p.id = pv.pedido_id
             WHERE pv.vendedor_id = ?
               AND pv.estado IN ($marcadores)
               AND p.estado <> 'cancelado'
             ORDER BY $orden",
            array_merge([$vendedorId], $estados)
        )->fetchAll();

        if (!$partes) {
            return [];
        }

        $idsPedidos = array_map('intval', array_column($partes, 'pedido_id'));
        $marcas     = implode(',', array_fill(0, count($idsPedidos), '?'));
        $items      = Database::consulta(
            "SELECT d.pedido_id, d.producto_id, d.producto_nombre, d.precio, d.cantidad, d.imagen,
                    pr.stock AS stock_actual
             FROM pedido_detalle d
             LEFT JOIN productos pr ON pr.id = d.producto_id
             WHERE d.vendedor_id = ? AND d.pedido_id IN ($marcas)
             ORDER BY d.id",
            array_merge([$vendedorId], $idsPedidos)
        )->fetchAll();

        $itemsPorPedido = [];
        foreach ($items as $item) {
            $itemsPorPedido[(int) $item['pedido_id']][] = $item;
        }
        foreach ($partes as &$parte) {
            $parte['productos'] = $itemsPorPedido[(int) $parte['pedido_id']] ?? [];
            $parte['opciones']  = self::transicionesPermitidas($parte['estado']);
        }
        unset($parte);

        return $partes;
    }

    /** Estados a los que se puede pasar desde el estado actual. */
    public static function transicionesPermitidas(string $actual): array
    {
        switch ($actual) {
            case self::PENDIENTE:
                return [self::PENDIENTE, self::APROBADO, self::RECHAZADO];
            case self::APROBADO:
                return [self::APROBADO, self::PENDIENTE, self::RECHAZADO, self::COMPLETADO];
            case self::RECHAZADO:
                return [self::RECHAZADO, self::PENDIENTE, self::APROBADO];
            default:
                return []; // completado: estado final
        }
    }

    /**
     * El vendedor cambia el estado de SU parte del pedido.
     * Maneja el stock: rechazar devuelve unidades; reactivar un rechazado las vuelve a descontar.
     *
     * @throws PedidoException
     */
    public static function cambiarEstadoVendedor(int $pedidoId, int $vendedorId, string $nuevoEstado): void
    {
        if (!in_array($nuevoEstado, self::ESTADOS_VENDEDOR, true)) {
            throw new PedidoException('Estado no válido.');
        }

        Database::transaccion(function () use ($pedidoId, $vendedorId, $nuevoEstado) {
            $parte = Database::consulta(
                'SELECT pv.id, pv.estado, p.estado AS estado_pedido
                 FROM pedidos_vendedor pv
                 INNER JOIN pedidos p ON p.id = pv.pedido_id
                 WHERE pv.pedido_id = :pedido_id AND pv.vendedor_id = :vendedor_id',
                [':pedido_id' => $pedidoId, ':vendedor_id' => $vendedorId]
            )->fetch();

            if (!$parte) {
                throw new PedidoException('El pedido no existe o no contiene productos tuyos.');
            }
            $actual = $parte['estado'];

            if ($actual === $nuevoEstado) {
                return;
            }
            if ($parte['estado_pedido'] === self::CANCELADO) {
                throw new PedidoException('El comprador canceló este pedido.');
            }
            if (!in_array($nuevoEstado, self::transicionesPermitidas($actual), true)) {
                throw new PedidoException($actual === self::COMPLETADO
                    ? 'Una venta completada ya no se puede modificar.'
                    : 'Solo puedes marcar como completada una venta que ya fue aprobada.');
            }

            $items = Database::consulta(
                'SELECT producto_id, producto_nombre, cantidad FROM pedido_detalle
                 WHERE pedido_id = :pedido_id AND vendedor_id = :vendedor_id AND producto_id IS NOT NULL',
                [':pedido_id' => $pedidoId, ':vendedor_id' => $vendedorId]
            )->fetchAll();

            if ($nuevoEstado === self::RECHAZADO) {
                foreach ($items as $item) {
                    Producto::devolverStock((int) $item['producto_id'], (int) $item['cantidad']);
                }
            } elseif ($actual === self::RECHAZADO) {
                foreach ($items as $item) {
                    if (!Producto::descontarStock((int) $item['producto_id'], (int) $item['cantidad'])) {
                        throw new PedidoException('No hay stock suficiente de "' . $item['producto_nombre'] . '" para reactivar este pedido.');
                    }
                }
            }

            Database::consulta(
                'UPDATE pedidos_vendedor SET estado = :estado, fecha_actualizacion = :fecha WHERE id = :id',
                [':estado' => $nuevoEstado, ':fecha' => date('Y-m-d H:i:s'), ':id' => $parte['id']]
            );

            self::recalcularEstadoGeneral($pedidoId);
        });
    }

    /**
     * Estado general del pedido a partir de las partes de cada vendedor:
     *   - todas rechazadas                      → rechazado
     *   - todas completadas o rechazadas        → completado
     *   - alguna aprobada o completada          → aprobado
     *   - en otro caso                          → pendiente
     */
    public static function recalcularEstadoGeneral(int $pedidoId): void
    {
        $estados = Database::consulta(
            'SELECT estado FROM pedidos_vendedor WHERE pedido_id = :pedido_id',
            [':pedido_id' => $pedidoId]
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!$estados) {
            return;
        }

        $cuenta = array_count_values($estados);
        $total  = count($estados);

        if (($cuenta[self::RECHAZADO] ?? 0) === $total) {
            $general = self::RECHAZADO;
        } elseif (($cuenta[self::COMPLETADO] ?? 0) + ($cuenta[self::RECHAZADO] ?? 0) === $total) {
            $general = self::COMPLETADO;
        } elseif (($cuenta[self::APROBADO] ?? 0) + ($cuenta[self::COMPLETADO] ?? 0) > 0) {
            $general = self::APROBADO;
        } else {
            $general = self::PENDIENTE;
        }

        Database::consulta(
            "UPDATE pedidos SET estado = :estado WHERE id = :id AND estado <> 'cancelado'",
            [':estado' => $general, ':id' => $pedidoId]
        );
    }

    public static function contar(): int
    {
        return (int) Database::consulta('SELECT COUNT(*) FROM pedidos')->fetchColumn();
    }
}
