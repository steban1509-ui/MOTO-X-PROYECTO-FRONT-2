<?php
/**
 * Modelo Vendedor (datos comerciales + documentos legales).
 */
final class Vendedor
{
    public const DOCUMENTOS = [
        'camara_comercio'      => 'Certificado de Cámara de Comercio',
        'rut'                  => 'RUT',
        'estados_financieros'  => 'Estados financieros',
        'cedula_representante' => 'Cédula del representante legal',
    ];

    public const ESTADOS = ['pendiente', 'aprobado', 'rechazado', 'suspendido'];

    public static function porUsuario(int $usuarioId): ?array
    {
        $fila = Database::consulta(
            'SELECT v.*, u.correo, u.nombre AS usuario_nombre, u.telefono AS usuario_telefono
             FROM vendedores v
             INNER JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.usuario_id = :usuario_id',
            [':usuario_id' => $usuarioId]
        )->fetch();
        return $fila ?: null;
    }

    public static function porId(int $id): ?array
    {
        $fila = Database::consulta('SELECT * FROM vendedores WHERE id = :id', [':id' => $id])->fetch();
        return $fila ?: null;
    }

    public static function existeNit(string $nit): bool
    {
        return (bool) Database::consulta('SELECT 1 FROM vendedores WHERE nit = :nit', [':nit' => $nit])->fetchColumn();
    }

    public static function crear(int $usuarioId, array $datos): int
    {
        Database::consulta(
            'INSERT INTO vendedores (usuario_id, razon_social, nit, ciudad, direccion, telefono_comercial, correo_comercial,
                                     representante_legal, cedula_representante, estado_verificacion, fecha_registro)
             VALUES (:usuario_id, :razon_social, :nit, :ciudad, :direccion, :telefono_comercial, :correo_comercial,
                     :representante_legal, :cedula_representante, :estado, :fecha)',
            [
                ':usuario_id'           => $usuarioId,
                ':razon_social'         => $datos['razon_social'],
                ':nit'                  => $datos['nit'],
                ':ciudad'               => $datos['ciudad'],
                ':direccion'            => $datos['direccion'] ?: null,
                ':telefono_comercial'   => $datos['telefono_comercial'] ?: null,
                ':correo_comercial'     => ($datos['correo_comercial'] ?? '') ?: null,
                ':representante_legal'  => $datos['representante_legal'],
                ':cedula_representante' => $datos['cedula_representante'],
                ':estado'               => VENDEDOR_REQUIERE_APROBACION ? 'pendiente' : 'aprobado',
                ':fecha'                => date('Y-m-d H:i:s'),
            ]
        );
        return (int) Database::conexion()->lastInsertId();
    }

    public static function guardarDocumento(int $vendedorId, string $tipo, string $ruta, string $nombreOriginal, string $mime, int $bytes): void
    {
        // Un documento por tipo: si ya existía se reemplaza
        Database::consulta('DELETE FROM vendedor_documentos WHERE vendedor_id = :v AND tipo = :t', [':v' => $vendedorId, ':t' => $tipo]);
        Database::consulta(
            'INSERT INTO vendedor_documentos (vendedor_id, tipo, ruta_archivo, nombre_original, mime_type, tamano_bytes, fecha_subida)
             VALUES (:vendedor_id, :tipo, :ruta, :nombre, :mime, :bytes, :fecha)',
            [
                ':vendedor_id' => $vendedorId,
                ':tipo'        => $tipo,
                ':ruta'        => $ruta,
                ':nombre'      => mb_substr($nombreOriginal, 0, 255),
                ':mime'        => $mime,
                ':bytes'       => $bytes,
                ':fecha'       => date('Y-m-d H:i:s'),
            ]
        );
    }

    /** Documentos del vendedor indexados por tipo. */
    public static function documentos(int $vendedorId): array
    {
        $filas = Database::consulta(
            'SELECT tipo, nombre_original, mime_type, tamano_bytes, fecha_subida
             FROM vendedor_documentos WHERE vendedor_id = :v',
            [':v' => $vendedorId]
        )->fetchAll();
        return array_column($filas, null, 'tipo');
    }

    public static function documento(int $vendedorId, string $tipo): ?array
    {
        $fila = Database::consulta(
            'SELECT * FROM vendedor_documentos WHERE vendedor_id = :v AND tipo = :t',
            [':v' => $vendedorId, ':t' => $tipo]
        )->fetch();
        return $fila ?: null;
    }

    public static function puedePublicar(array $vendedor): bool
    {
        return !VENDEDOR_REQUIERE_APROBACION || $vendedor['estado_verificacion'] === 'aprobado';
    }

    public static function listarParaAdmin(?string $estado = null): array
    {
        $sql = 'SELECT v.*, u.correo, u.telefono,
                       (SELECT COUNT(*) FROM vendedor_documentos d WHERE d.vendedor_id = v.id) AS total_documentos,
                       (SELECT COUNT(*) FROM productos p WHERE p.vendedor_id = v.id) AS total_productos
                FROM vendedores v
                INNER JOIN usuarios u ON u.id = v.usuario_id';
        $parametros = [];
        if ($estado !== null && in_array($estado, self::ESTADOS, true)) {
            $sql .= ' WHERE v.estado_verificacion = :estado';
            $parametros[':estado'] = $estado;
        }
        $sql .= " ORDER BY CASE v.estado_verificacion WHEN 'pendiente' THEN 0 ELSE 1 END, v.fecha_registro DESC";

        return Database::consulta($sql, $parametros)->fetchAll();
    }

    public static function cambiarEstado(int $vendedorId, string $estado, ?string $observaciones, int $adminId): bool
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return false;
        }
        return Database::consulta(
            'UPDATE vendedores
             SET estado_verificacion = :estado, observaciones = :obs, fecha_verificacion = :fecha, verificado_por = :admin
             WHERE id = :id',
            [
                ':estado' => $estado,
                ':obs'    => $observaciones ?: null,
                ':fecha'  => date('Y-m-d H:i:s'),
                ':admin'  => $adminId,
                ':id'     => $vendedorId,
            ]
        )->rowCount() > 0;
    }

    public static function contarPendientes(): int
    {
        return (int) Database::consulta("SELECT COUNT(*) FROM vendedores WHERE estado_verificacion = 'pendiente'")->fetchColumn();
    }
    // =================================================================
    // CAMBIOS DE PERFIL CON APROBACIÓN DEL ADMINISTRADOR (v4)
    // =================================================================

    /** Campos que el vendedor puede editar (con aprobación). */
    public const CAMPOS_EDITABLES = [
        'ciudad'             => 'Ciudad',
        'direccion'          => 'Dirección',
        'telefono_comercial' => 'Teléfono',
        'correo_comercial'   => 'Correo electrónico',
    ];

    public static function solicitudPendiente(int $vendedorId): ?array
    {
        $fila = Database::consulta(
            "SELECT * FROM vendedor_cambios_perfil
             WHERE vendedor_id = :vendedor_id AND estado = 'pendiente'
             ORDER BY id DESC LIMIT 1",
            [':vendedor_id' => $vendedorId]
        )->fetch();
        return $fila ?: null;
    }

    /** Última solicitud revisada (para avisar al vendedor si fue rechazada). */
    public static function ultimaSolicitudRevisada(int $vendedorId): ?array
    {
        $fila = Database::consulta(
            "SELECT * FROM vendedor_cambios_perfil
             WHERE vendedor_id = :vendedor_id AND estado <> 'pendiente'
             ORDER BY fecha_revision DESC, id DESC LIMIT 1",
            [':vendedor_id' => $vendedorId]
        )->fetch();
        return $fila ?: null;
    }

    /**
     * Registra la solicitud de cambio. NO modifica la tabla vendedores.
     * Si ya había una pendiente, se reemplaza por la nueva (solo una a la vez).
     */
    public static function solicitarCambioPerfil(int $vendedorId, array $datos): void
    {
        $parametros = [
            ':ciudad'    => $datos['ciudad'],
            ':direccion' => $datos['direccion'] ?: null,
            ':telefono'  => $datos['telefono_comercial'] ?: null,
            ':correo'    => $datos['correo_comercial'] ?: null,
            ':fecha'     => date('Y-m-d H:i:s'),
        ];

        $pendiente = self::solicitudPendiente($vendedorId);

        if ($pendiente) {
            Database::consulta(
                'UPDATE vendedor_cambios_perfil
                 SET ciudad = :ciudad, direccion = :direccion, telefono_comercial = :telefono,
                     correo_comercial = :correo, fecha_solicitud = :fecha
                 WHERE id = :id',
                $parametros + [':id' => $pendiente['id']]
            );
            return;
        }

        Database::consulta(
            'INSERT INTO vendedor_cambios_perfil (vendedor_id, ciudad, direccion, telefono_comercial, correo_comercial, estado, fecha_solicitud)
             VALUES (:vendedor_id, :ciudad, :direccion, :telefono, :correo, :estado, :fecha)',
            $parametros + [':vendedor_id' => $vendedorId, ':estado' => 'pendiente']
        );
    }

    public static function cancelarSolicitud(int $vendedorId): bool
    {
        return Database::consulta(
            "DELETE FROM vendedor_cambios_perfil WHERE vendedor_id = :vendedor_id AND estado = 'pendiente'",
            [':vendedor_id' => $vendedorId]
        )->rowCount() > 0;
    }

    /** Solicitudes pendientes con los valores actuales al lado (panel admin). */
    public static function solicitudesPendientes(): array
    {
        return Database::consulta(
            "SELECT c.*, v.razon_social, v.nit,
                    v.ciudad             AS actual_ciudad,
                    v.direccion          AS actual_direccion,
                    v.telefono_comercial AS actual_telefono_comercial,
                    v.correo_comercial   AS actual_correo_comercial
             FROM vendedor_cambios_perfil c
             INNER JOIN vendedores v ON v.id = c.vendedor_id
             WHERE c.estado = 'pendiente'
             ORDER BY c.fecha_solicitud"
        )->fetchAll();
    }

    public static function contarSolicitudesPendientes(): int
    {
        return (int) Database::consulta("SELECT COUNT(*) FROM vendedor_cambios_perfil WHERE estado = 'pendiente'")->fetchColumn();
    }

    /** Aprobar: copia los datos solicitados a la tabla vendedores (en una transacción). */
    public static function aprobarSolicitud(int $solicitudId, int $adminId): bool
    {
        return Database::transaccion(function () use ($solicitudId, $adminId) {
            $solicitud = Database::consulta(
                "SELECT * FROM vendedor_cambios_perfil WHERE id = :id AND estado = 'pendiente'",
                [':id' => $solicitudId]
            )->fetch();

            if (!$solicitud) {
                return false;
            }

            Database::consulta(
                'UPDATE vendedores
                 SET ciudad = :ciudad, direccion = :direccion, telefono_comercial = :telefono, correo_comercial = :correo
                 WHERE id = :vendedor_id',
                [
                    ':ciudad'      => $solicitud['ciudad'],
                    ':direccion'   => $solicitud['direccion'],
                    ':telefono'    => $solicitud['telefono_comercial'],
                    ':correo'      => $solicitud['correo_comercial'],
                    ':vendedor_id' => $solicitud['vendedor_id'],
                ]
            );

            self::cerrarSolicitud($solicitudId, 'aprobado', null, $adminId);
            return true;
        });
    }

    public static function rechazarSolicitud(int $solicitudId, int $adminId, string $observaciones): bool
    {
        return self::cerrarSolicitud($solicitudId, 'rechazado', $observaciones, $adminId);
    }

    private static function cerrarSolicitud(int $solicitudId, string $estado, ?string $observaciones, int $adminId): bool
    {
        return Database::consulta(
            "UPDATE vendedor_cambios_perfil
             SET estado = :estado, observaciones = :obs, fecha_revision = :fecha, revisado_por = :admin
             WHERE id = :id AND estado = 'pendiente'",
            [
                ':estado' => $estado,
                ':obs'    => $observaciones ?: null,
                ':fecha'  => date('Y-m-d H:i:s'),
                ':admin'  => $adminId,
                ':id'     => $solicitudId,
            ]
        )->rowCount() > 0;
    }
}
