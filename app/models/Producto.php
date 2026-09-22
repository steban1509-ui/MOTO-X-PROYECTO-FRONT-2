<?php
final class Producto
{
    public const ACTIVO   = 'activo';
    public const INACTIVO = 'inactivo';
    public const ESTADOS  = [self::ACTIVO, self::INACTIVO];

    // CATÁLOGO PÚBLICO  (solo productos activos)
    public static function catalogoPorCategoria(int $categoriaId): array
    {
        return Database::consulta(
            'SELECT id, nombre, descripcion, precio, imagen, stock, vendedor_nombre
             FROM vw_catalogo_publico
             WHERE categoria_id = :categoria_id
             ORDER BY id',
            [':categoria_id' => $categoriaId]
        )->fetchAll();
    }

    public static function publicoPorId(int $id): ?array
    {
        $fila = Database::consulta('SELECT * FROM vw_catalogo_publico WHERE id = :id', [':id' => $id])->fetch();
        return $fila ?: null;
    }

    /** Productos activos por id (para validar un carrito). Indexados por id. */
    public static function publicosPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $filas = Database::consulta(
            "SELECT id, vendedor_id, nombre, precio, imagen, stock FROM vw_catalogo_publico WHERE id IN ($marcadores)",
            $ids
        )->fetchAll();

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(int) $fila['id']] = $fila;
        }
        return $resultado;
    }

    /**
     * Productos ALEATORIOS para el carrusel del inicio
     */
    public static function aleatoriosParaCarrusel(int $cantidad = 8): array
    {
        $candidatos = Database::consulta(
            "SELECT id, nombre, descripcion, precio, imagen, stock, categoria_slug
             FROM vw_catalogo_publico
             WHERE imagen IS NOT NULL AND imagen <> ''
             ORDER BY fecha_creacion DESC, id DESC
             LIMIT 200"
        )->fetchAll();

        shuffle($candidatos);
        return array_slice($candidatos, 0, max(1, $cantidad));
    }

    // PANEL DEL VENDEDOR 

    public static function delVendedor(int $vendedorId): array
    {
        return Database::consulta(
            'SELECT p.id, p.nombre, p.precio, p.stock, p.estado, p.imagen, p.fecha_creacion,
                    c.nombre AS categoria,
                    (SELECT COUNT(*) FROM pedido_detalle d WHERE d.producto_id = p.id) AS veces_vendido
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.vendedor_id = :vendedor_id
             ORDER BY p.estado, p.fecha_creacion DESC, p.id DESC',
            [':vendedor_id' => $vendedorId]
        )->fetchAll();
    }

    /** Devuelve el producto solo si pertenece al vendedor (control de propiedad). */
    public static function delVendedorPorId(int $productoId, int $vendedorId): ?array
    {
        $fila = Database::consulta(
            'SELECT * FROM productos WHERE id = :id AND vendedor_id = :vendedor_id',
            [':id' => $productoId, ':vendedor_id' => $vendedorId]
        )->fetch();
        return $fila ?: null;
    }

    public static function crear(int $vendedorId, array $datos): int
    {
        Database::consulta(
            'INSERT INTO productos (categoria_id, vendedor_id, nombre, descripcion, caracteristicas, precio, stock, estado, fecha_creacion)
             VALUES (:categoria_id, :vendedor_id, :nombre, :descripcion, :caracteristicas, :precio, :stock, :estado, :fecha)',
            [
                ':categoria_id'    => $datos['categoria_id'],
                ':vendedor_id'     => $vendedorId,
                ':nombre'          => $datos['nombre'],
                ':descripcion'     => $datos['descripcion'],
                ':caracteristicas' => $datos['caracteristicas'],
                ':precio'          => $datos['precio'],
                ':stock'           => $datos['stock'],
                ':estado'          => $datos['estado'],
                ':fecha'           => date('Y-m-d H:i:s'),
            ]
        );
        return (int) Database::conexion()->lastInsertId();
    }

    public static function actualizar(int $productoId, int $vendedorId, array $datos): void
    {
        Database::consulta(
            'UPDATE productos
             SET categoria_id = :categoria_id, nombre = :nombre, descripcion = :descripcion,
                 caracteristicas = :caracteristicas, precio = :precio, stock = :stock, estado = :estado
             WHERE id = :id AND vendedor_id = :vendedor_id',
            [
                ':categoria_id'    => $datos['categoria_id'],
                ':nombre'          => $datos['nombre'],
                ':descripcion'     => $datos['descripcion'],
                ':caracteristicas' => $datos['caracteristicas'],
                ':precio'          => $datos['precio'],
                ':stock'           => $datos['stock'],
                ':estado'          => $datos['estado'],
                ':id'              => $productoId,
                ':vendedor_id'     => $vendedorId,
            ]
        );
    }

    /**
     * Cambia el estado Activo/Inactivo.
     * Si $vendedorId es null lo hace el administrador (sin control de propiedad).
     */
    public static function cambiarEstado(int $productoId, string $estado, ?int $vendedorId = null): bool
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return false;
        }
        $sql = 'UPDATE productos SET estado = :estado WHERE id = :id';
        $parametros = [':estado' => $estado, ':id' => $productoId];

        if ($vendedorId !== null) {
            $sql .= ' AND vendedor_id = :vendedor_id';
            $parametros[':vendedor_id'] = $vendedorId;
        }

        $stmt = Database::consulta($sql, $parametros);
        // rowCount() es 0 si ya tenía ese estado; se confirma que el producto existe
        return $stmt->rowCount() > 0 || (bool) Database::consulta(
            'SELECT 1 FROM productos WHERE id = :id AND estado = :estado' . ($vendedorId !== null ? ' AND vendedor_id = :vendedor_id' : ''),
            $parametros
        )->fetchColumn();
    }

    public static function conteoPorEstado(?int $vendedorId = null): array
    {
        $sql = 'SELECT estado, COUNT(*) AS total FROM productos';
        $parametros = [];
        if ($vendedorId !== null) {
            $sql .= ' WHERE vendedor_id = :vendedor_id';
            $parametros[':vendedor_id'] = $vendedorId;
        }
        $sql .= ' GROUP BY estado';

        $conteo = [self::ACTIVO => 0, self::INACTIVO => 0];
        foreach (Database::consulta($sql, $parametros)->fetchAll() as $fila) {
            $conteo[$fila['estado']] = (int) $fila['total'];
        }
        return $conteo;
    }

    // =================================================================
    // IMÁGENES
    // =================================================================

    public static function imagenes(int $productoId): array
    {
        return Database::consulta(
            'SELECT id, url_imagen, orden FROM producto_imagenes WHERE producto_id = :id ORDER BY orden, id',
            [':id' => $productoId]
        )->fetchAll();
    }

    public static function agregarImagen(int $productoId, string $url, int $orden): void
    {
        Database::consulta(
            'INSERT INTO producto_imagenes (producto_id, url_imagen, orden) VALUES (:producto_id, :url, :orden)',
            [':producto_id' => $productoId, ':url' => $url, ':orden' => $orden]
        );
    }

    /** Elimina la imagen si pertenece al producto. Devuelve su URL relativa o null. */
    public static function eliminarImagen(int $imagenId, int $productoId): ?string
    {
        $url = Database::consulta(
            'SELECT url_imagen FROM producto_imagenes WHERE id = :id AND producto_id = :producto_id',
            [':id' => $imagenId, ':producto_id' => $productoId]
        )->fetchColumn();

        if ($url === false) {
            return null;
        }
        Database::consulta('DELETE FROM producto_imagenes WHERE id = :id', [':id' => $imagenId]);
        return $url;
    }

    public static function siguienteOrden(int $productoId): int
    {
        return 1 + (int) Database::consulta(
            'SELECT COALESCE(MAX(orden), 0) FROM producto_imagenes WHERE producto_id = :id',
            [':id' => $productoId]
        )->fetchColumn();
    }

    /** La primera imagen de la galería se copia como imagen principal del producto. */
    public static function sincronizarImagenPrincipal(int $productoId): void
    {
        $primera = Database::consulta(
            'SELECT url_imagen FROM producto_imagenes WHERE producto_id = :id ORDER BY orden, id LIMIT 1',
            [':id' => $productoId]
        )->fetchColumn();

        Database::consulta('UPDATE productos SET imagen = :imagen WHERE id = :id', [
            ':imagen' => $primera ?: null,
            ':id'     => $productoId,
        ]);
    }

    // =================================================================
    // PANEL DEL ADMINISTRADOR  (todos los productos, cualquier estado)
    // =================================================================

    public static function listarParaAdmin(?string $estado = null, ?int $categoriaId = null): array
    {
        $sql = 'SELECT p.id, p.nombre, p.precio, p.stock, p.estado, p.imagen, p.fecha_creacion,
                       c.nombre AS categoria, v.razon_social AS vendedor, v.estado_verificacion
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                INNER JOIN vendedores v ON v.id = p.vendedor_id
                WHERE 1 = 1';
        $parametros = [];

        if ($estado !== null && in_array($estado, self::ESTADOS, true)) {
            $sql .= ' AND p.estado = :estado';
            $parametros[':estado'] = $estado;
        }
        if ($categoriaId) {
            $sql .= ' AND p.categoria_id = :categoria_id';
            $parametros[':categoria_id'] = $categoriaId;
        }
        $sql .= ' ORDER BY p.id DESC';

        return Database::consulta($sql, $parametros)->fetchAll();
    }

    // =================================================================
    // INVENTARIO
    // =================================================================

    /** Descuenta stock de forma atómica. false si no hay suficiente. */
    public static function descontarStock(int $productoId, int $cantidad): bool
    {
        return Database::consulta(
            'UPDATE productos SET stock = stock - :cantidad WHERE id = :id AND stock >= :minimo',
            [':cantidad' => $cantidad, ':id' => $productoId, ':minimo' => $cantidad]
        )->rowCount() > 0;
    }

    public static function devolverStock(int $productoId, int $cantidad): void
    {
        Database::consulta(
            'UPDATE productos SET stock = stock + :cantidad WHERE id = :id',
            [':cantidad' => $cantidad, ':id' => $productoId]
        );
    }
}
