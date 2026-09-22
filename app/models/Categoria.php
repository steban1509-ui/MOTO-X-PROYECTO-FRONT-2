<?php

final class Categoria
{
    public static function todas(): array
    {
        return Database::consulta('SELECT id, slug, nombre, titulo, descripcion, imagen FROM categorias ORDER BY orden, nombre')->fetchAll();
    }

    public static function porSlug(string $slug): ?array
    {
        $fila = Database::consulta(
            'SELECT id, slug, nombre, titulo, descripcion, imagen FROM categorias WHERE slug = :slug',
            [':slug' => $slug]
        )->fetch();
        return $fila ?: null;
    }

    public static function porId(int $id): ?array
    {
        $fila = Database::consulta('SELECT * FROM categorias WHERE id = :id', [':id' => $id])->fetch();
        return $fila ?: null;
    }

    /** Lista para el panel del administrador, con la cantidad de productos de cada una. */
    public static function listarParaAdmin(): array
    {
        return Database::consulta(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS total_productos,
                    (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.estado = 'activo') AS productos_activos
             FROM categorias c
             ORDER BY c.orden, c.nombre"
        )->fetchAll();
    }

    public static function existeSlug(string $slug, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT 1 FROM categorias WHERE slug = :slug';
        $parametros = [':slug' => $slug];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $exceptoId;
        }
        return (bool) Database::consulta($sql, $parametros)->fetchColumn();
    }

    public static function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT 1 FROM categorias WHERE nombre = :nombre';
        $parametros = [':nombre' => $nombre];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $exceptoId;
        }
        return (bool) Database::consulta($sql, $parametros)->fetchColumn();
    }

    /**
     * Convierte "Cascos y Protección" en "cascos-y-proteccion" (se usa en la URL).
     * Si el slug ya existe le agrega -2, -3, ...
     */
    public static function generarSlug(string $texto, ?int $exceptoId = null): string
    {
        $slug = strtolower(trim($texto));
        $slug = strtr($slug, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
        ]);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'categoria';
        }
        $slug = mb_substr($slug, 0, 45);

        $base = $slug;
        $i    = 2;
        while (self::existeSlug($slug, $exceptoId)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function crear(array $datos): int
    {
        Database::consulta(
            'INSERT INTO categorias (slug, nombre, titulo, descripcion, imagen, orden)
             VALUES (:slug, :nombre, :titulo, :descripcion, :imagen, :orden)',
            [
                ':slug'        => $datos['slug'],
                ':nombre'      => $datos['nombre'],
                ':titulo'      => $datos['titulo'],
                ':descripcion' => $datos['descripcion'] ?: null,
                ':imagen'      => $datos['imagen'] ?: null,
                ':orden'       => $datos['orden'],
            ]
        );
        return (int) Database::conexion()->lastInsertId();
    }

    /** Si $datos['imagen'] es null se conserva la imagen actual. */
    public static function actualizar(int $id, array $datos): void
    {
        $sql = 'UPDATE categorias
                SET slug = :slug, nombre = :nombre, titulo = :titulo,
                    descripcion = :descripcion, orden = :orden';
        $parametros = [
            ':slug'        => $datos['slug'],
            ':nombre'      => $datos['nombre'],
            ':titulo'      => $datos['titulo'],
            ':descripcion' => $datos['descripcion'] ?: null,
            ':orden'       => $datos['orden'],
            ':id'          => $id,
        ];

        if (!empty($datos['imagen'])) {
            $sql .= ', imagen = :imagen';
            $parametros[':imagen'] = $datos['imagen'];
        }

        $sql .= ' WHERE id = :id';
        Database::consulta($sql, $parametros);
    }

    public static function contarProductos(int $id): int
    {
        return (int) Database::consulta(
            'SELECT COUNT(*) FROM productos WHERE categoria_id = :id',
            [':id' => $id]
        )->fetchColumn();
    }

    /** Solo se puede eliminar una categoría vacía (sin productos). */
    public static function eliminar(int $id): bool
    {
        if (self::contarProductos($id) > 0) {
            return false;
        }
        return Database::consulta('DELETE FROM categorias WHERE id = :id', [':id' => $id])->rowCount() > 0;
    }
}
