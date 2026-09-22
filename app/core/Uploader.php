<?php
/**
 * Validación y guardado seguro de archivos subidos.
 *
 * - El tipo se valida por el CONTENIDO real del archivo (finfo), no por la extensión.
 * - El nombre final es aleatorio (nunca se usa el nombre que envía el usuario).
 */
final class Uploader
{
    public const IMAGENES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public const DOCUMENTOS = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    ];

    /**
     * Convierte $_FILES['campo'] de un input "multiple" en una lista de archivos.
     * Omite los campos vacíos.
     */
    public static function normalizarMultiples(?array $campo): array
    {
        if (!$campo || !isset($campo['name'])) {
            return [];
        }
        if (!is_array($campo['name'])) {
            return $campo['error'] === UPLOAD_ERR_NO_FILE ? [] : [$campo];
        }

        $archivos = [];
        foreach ($campo['name'] as $i => $nombre) {
            if ($campo['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $archivos[] = [
                'name'     => $nombre,
                'type'     => $campo['type'][$i],
                'tmp_name' => $campo['tmp_name'][$i],
                'error'    => $campo['error'][$i],
                'size'     => $campo['size'][$i],
            ];
        }
        return $archivos;
    }

    /** Devuelve un mensaje de error o null si el archivo es válido. */
    public static function validar(?array $archivo, array $tiposPermitidos, int $maxBytes, bool $obligatorio = true): ?string
    {
        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $obligatorio ? 'Debes adjuntar este archivo.' : null;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return self::mensajeErrorPhp((int) $archivo['error']);
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            return 'El archivo no es válido.';
        }

        if ($archivo['size'] > $maxBytes) {
            return 'El archivo "' . $archivo['name'] . '" supera el máximo de ' . round($maxBytes / 1048576, 1) . ' MB.';
        }

        $mime = self::mimeReal($archivo['tmp_name']);
        if (!isset($tiposPermitidos[$mime])) {
            $extensiones = strtoupper(implode(', ', array_unique($tiposPermitidos)));
            return 'El archivo "' . $archivo['name'] . '" no es de un tipo permitido (' . $extensiones . ').';
        }

        if (strpos($mime, 'image/') === 0 && @getimagesize($archivo['tmp_name']) === false) {
            return 'La imagen "' . $archivo['name'] . '" está dañada.';
        }

        return null;
    }

    /**
     * Mueve el archivo a $directorio con un nombre aleatorio.
     * Devuelve el nombre generado (sin la ruta).
     */
    public static function guardar(array $archivo, string $directorio, array $tiposPermitidos): string
    {
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta de destino.');
        }

        if (!is_writable($directorio)) {
            throw new RuntimeException(
                'Apache no tiene permiso de escritura en la carpeta ' . $directorio
                . '. Dale permisos de escritura (en macOS: sudo chmod -R 777 "' . $directorio . '").'
            );
        }

        $mime = self::mimeReal($archivo['tmp_name']);
        if (!isset($tiposPermitidos[$mime])) {
            throw new RuntimeException('Tipo de archivo no permitido.');
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $tiposPermitidos[$mime];

        if (!@move_uploaded_file($archivo['tmp_name'], $directorio . '/' . $nombre)) {
            throw new RuntimeException('No se pudo guardar el archivo en ' . $directorio . '. Revisa los permisos de la carpeta.');
        }

        return $nombre;
    }

    public static function eliminar(string $rutaAbsoluta): void
    {
        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }
    }

    public static function mimeReal(string $rutaTemporal): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);
    }

    private static function mensajeErrorPhp(int $codigo): string
    {
        switch ($codigo) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo supera el tamaño permitido por el servidor.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió de forma incompleta. Intenta de nuevo.';
            default:
                return 'No se pudo subir el archivo (código ' . $codigo . ').';
        }
    }
}
