<?php
/**
 * Descarga segura de documentos legales de vendedores.
 *   documento.php?vendedor=3&tipo=rut
 *
 * Los archivos están en storage/ (fuera de /public), así que nadie puede
 * abrirlos con una URL directa. Solo pueden verlos:
 *   - un administrador
 *   - el propio vendedor dueño del documento
 */
require __DIR__ . '/../app/bootstrap.php';

Auth::requerirLogin();
Auth::refrescar();
Auth::requerirLogin();

$vendedorId = (int) ($_GET['vendedor'] ?? 0);
$tipo       = (string) ($_GET['tipo'] ?? '');

$vendedor = Vendedor::porId($vendedorId);
$esDueno  = $vendedor && (int) $vendedor['usuario_id'] === Auth::id();

if (!$vendedor || !isset(Vendedor::DOCUMENTOS[$tipo]) || !(Auth::tieneRol(Auth::ADMIN) || $esDueno)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$documento = Vendedor::documento($vendedorId, $tipo);
$base      = realpath(DOCS_VENDEDORES_DIR);
$ruta      = $documento ? realpath(DOCS_VENDEDORES_DIR . '/' . $documento['ruta_archivo']) : false;

// Evita salir de la carpeta de documentos (path traversal)
if (!$documento || !$ruta || !$base || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($ruta)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$extension     = pathinfo($ruta, PATHINFO_EXTENSION);
$nombreDescarga = $tipo . '_' . preg_replace('/[^A-Za-z0-9-]/', '', $vendedor['nit']) . '.' . $extension;

header('Content-Type: ' . $documento['mime_type']);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $nombreDescarga . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($ruta);
