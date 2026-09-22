<?php
final class Usuario
{
    public static function buscarPorCorreo(string $correo): ?array
    {
        $fila = Database::consulta(
            'SELECT id, nombre, correo, telefono, password, estado FROM usuarios WHERE correo = :correo',
            [':correo' => $correo]
        )->fetch();
        return $fila ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $fila = Database::consulta(
            'SELECT id, nombre, correo, telefono, estado, fecha_registro FROM usuarios WHERE id = :id',
            [':id' => $id]
        )->fetch();
        return $fila ?: null;
    }

    public static function existeCorreo(string $correo): bool
    {
        return (bool) Database::consulta('SELECT 1 FROM usuarios WHERE correo = :correo', [':correo' => $correo])->fetchColumn();
    }

    /** Crea el usuario (sin roles). Devuelve el id. */
    public static function crear(string $nombre, string $correo, string $telefono, string $password): int
    {
        Database::consulta(
            'INSERT INTO usuarios (nombre, correo, telefono, password) VALUES (:nombre, :correo, :telefono, :password)',
            [
                ':nombre'   => $nombre,
                ':correo'   => $correo,
                ':telefono' => $telefono,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
            ]
        );
        return (int) Database::conexion()->lastInsertId();
    }

    /** Claves de los roles del usuario: ['comprador', 'vendedor', ...] */
    public static function roles(int $usuarioId): array
    {
        return Database::consulta(
            'SELECT r.clave
             FROM usuario_roles ur
             INNER JOIN roles r ON r.id = ur.rol_id
             WHERE ur.usuario_id = :usuario_id
             ORDER BY r.id',
            [':usuario_id' => $usuarioId]
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Agrega un rol al usuario (si ya lo tiene no hace nada). */
    public static function asignarRol(int $usuarioId, string $claveRol): void
    {
        if (in_array($claveRol, self::roles($usuarioId), true)) {
            return;
        }
        $stmt = Database::consulta(
            'INSERT INTO usuario_roles (usuario_id, rol_id)
             SELECT :usuario_id, id FROM roles WHERE clave = :clave',
            [':usuario_id' => $usuarioId, ':clave' => $claveRol]
        );
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("El rol '{$claveRol}' no existe en la tabla roles.");
        }
    }

    public static function registrarAcceso(int $usuarioId): void
    {
        Database::consulta('UPDATE usuarios SET ultimo_acceso = :fecha WHERE id = :id', [
            ':fecha' => date('Y-m-d H:i:s'),
            ':id'    => $usuarioId,
        ]);
    }

    public static function actualizarPassword(int $usuarioId, string $password): void
    {
        Database::consulta('UPDATE usuarios SET password = :password WHERE id = :id', [
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':id'       => $usuarioId,
        ]);
    }

    /** Cantidad de usuarios por rol (para el panel del administrador). */
    public static function conteoPorRol(): array
    {
        return Database::consulta(
            'SELECT r.nombre, COUNT(ur.usuario_id) AS total
             FROM roles r
             LEFT JOIN usuario_roles ur ON ur.rol_id = r.id
             GROUP BY r.id, r.nombre
             ORDER BY r.id'
        )->fetchAll();
    }
}
