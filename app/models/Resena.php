<?php
/**
 * Modelo Reseña (calificación 1 a 5 + comentario).
 */
final class Resena
{
    public static function deProducto(int $productoId): array
    {
        return Database::consulta(
            'SELECT nombre_usuario, calificacion, comentario, fecha
             FROM resenas WHERE producto_id = :id ORDER BY fecha DESC',
            [':id' => $productoId]
        )->fetchAll();
    }

    /** Un usuario tiene una sola reseña por producto: si ya existe se actualiza. */
    public static function guardar(int $productoId, int $usuarioId, string $nombreUsuario, int $calificacion, string $comentario): void
    {
        $existente = Database::consulta(
            'SELECT id FROM resenas WHERE producto_id = :p AND usuario_id = :u',
            [':p' => $productoId, ':u' => $usuarioId]
        )->fetchColumn();

        $parametros = [
            ':calificacion' => $calificacion,
            ':comentario'   => $comentario,
            ':fecha'        => date('Y-m-d H:i:s'),
        ];

        if ($existente) {
            Database::consulta(
                'UPDATE resenas SET calificacion = :calificacion, comentario = :comentario, fecha = :fecha WHERE id = :id',
                $parametros + [':id' => $existente]
            );
            return;
        }

        Database::consulta(
            'INSERT INTO resenas (producto_id, usuario_id, nombre_usuario, calificacion, comentario, fecha)
             VALUES (:producto_id, :usuario_id, :nombre_usuario, :calificacion, :comentario, :fecha)',
            $parametros + [':producto_id' => $productoId, ':usuario_id' => $usuarioId, ':nombre_usuario' => $nombreUsuario]
        );
    }
}
