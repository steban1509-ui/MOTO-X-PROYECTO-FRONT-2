<?php
/**
 * Funciones de ayuda globales (vistas, URLs, respuestas, CSRF, mensajes flash).
 */

// ---------------------------------------------------------------
// Escape de salida (protección XSS). Úsalo SIEMPRE al imprimir datos.
// ---------------------------------------------------------------
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------
// URLs
// ---------------------------------------------------------------

/** Ruta base pública, p. ej. '' o '/motosx/public'. Se autodetecta. */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (BASE_URL !== '') {
        return $base = rtrim(BASE_URL, '/');
    }

    $scriptUrl  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dirUrl     = rtrim(str_replace('\\', '/', dirname($scriptUrl)), '/');
    $dirArchivo = str_replace('\\', '/', (string) realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $publico    = str_replace('\\', '/', (string) realpath(PUBLIC_PATH));

    // Si el script está en una subcarpeta de /public (vendedor/, admin/, api/)
    // se le quita esa parte para llegar a la raíz pública.
    $subcarpeta = ($publico !== '' && strpos($dirArchivo, $publico) === 0)
        ? substr($dirArchivo, strlen($publico))
        : '';

    if ($subcarpeta !== '' && substr($dirUrl, -strlen($subcarpeta)) === $subcarpeta) {
        $dirUrl = substr($dirUrl, 0, -strlen($subcarpeta));
    }

    return $base = rtrim($dirUrl, '/');
}

/** URL a una página del sitio: url('vendedor/perfil.php') */
function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

/** URL a un recurso estático (imágenes, css, js) codificando espacios y tildes. */
function asset(?string $ruta): string
{
    $ruta = (string) $ruta;
    if ($ruta === '') {
        $ruta = 'img/logo/moto_x_logo.jpg';
    }
    if (preg_match('#^https?://#i', $ruta)) {
        return $ruta;
    }
    $partes = array_map('rawurlencode', explode('/', ltrim($ruta, '/')));
    $urlFinal = base_url() . '/' . implode('/', $partes);

    // Evita que el navegador use una versión vieja de CSS/JS guardada en caché:
    // se agrega ?v=<fecha de modificación>, que cambia cada vez que se edita el archivo.
    $archivo = PUBLIC_PATH . '/' . ltrim($ruta, '/');
    if (preg_match('/\.(css|js)$/i', $ruta) && is_file($archivo)) {
        $urlFinal .= '?v=' . filemtime($archivo);
    }

    return $urlFinal;
}

function redirect(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

/** Redirige a la misma URL que se está visitando (patrón PRG). */
function redirect_actual(): void
{
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? url('index.php')));
    exit;
}

// ---------------------------------------------------------------
// Petición / respuesta
// ---------------------------------------------------------------
function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function es_peticion_api(): bool
{
    return strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false;
}

function json_response(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lee el cuerpo JSON de una petición fetch(). */
function leer_json(): array
{
    $datos = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($datos) ? $datos : [];
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Detiene la petición si el token CSRF no es válido. */
function verificar_csrf(): void
{
    // Si el formulario supera post_max_size, PHP vacía $_POST y $_FILES.
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !es_peticion_api()) {
        flash('error', 'Archivos demasiado grandes', 'El envío supera el tamaño máximo permitido por el servidor.');
        redirect_actual();
    }

    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        if (es_peticion_api()) {
            json_response(['ok' => false, 'mensaje' => 'Tu sesión expiró. Recarga la página e intenta de nuevo.'], 419);
        }
        flash('error', 'Sesión expirada', 'Recarga la página e intenta de nuevo.');
        redirect_actual();
    }
}

// ---------------------------------------------------------------
// Mensajes flash (se muestran con SweetAlert2 en la siguiente vista)
// ---------------------------------------------------------------
function flash(string $tipo, string $titulo, string $mensaje = ''): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'titulo' => $titulo, 'mensaje' => $mensaje];
}

function flashes(): array
{
    $mensajes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $mensajes;
}

// ---------------------------------------------------------------
// Formato
// ---------------------------------------------------------------
function precio($valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}

function fecha_legible(?string $fecha): string
{
    return $fecha ? date('d/m/Y h:i a', strtotime($fecha)) : '—';
}

/**
 * Convierte el precio escrito por el usuario a número.
 * Acepta: 45000 · 45.000 · 1.250.000 · 45000,50 · 45000.50
 */
function normalizar_precio(string $texto): ?float
{
    $texto = str_replace([' ', '$'], '', $texto);

    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $texto)) {   // 1.250.000,50
        $texto = str_replace(['.', ','], ['', '.'], $texto);
    } elseif (preg_match('/^\d+,\d{1,2}$/', $texto)) {              // 45000,50
        $texto = str_replace(',', '.', $texto);
    }

    return preg_match('/^\d+(\.\d{1,2})?$/', $texto) ? (float) $texto : null;
}

/** Convierte "a|b|c" (formato guardado) en lista. */
function lista_caracteristicas(?string $texto): array
{
    return array_values(array_filter(array_map('trim', explode('|', (string) $texto)), 'strlen'));
}

/** Renderiza una vista parcial pasándole variables. */
function vista(string $nombre, array $datos = []): void
{
    extract($datos, EXTR_SKIP);
    require VIEW_PATH . '/' . $nombre . '.php';
}

// ---------------------------------------------------------------
// Estados de pedido (v4)
// ---------------------------------------------------------------

/** Texto visible de cada estado de pedido / venta. */
function estado_pedido_texto(string $estado): string
{
    $textos = [
        'pendiente'  => 'Pendiente',
        'aprobado'   => 'Aprobado',
        'rechazado'  => 'Rechazada',
        'completado' => 'Completada',
        'cancelado'  => 'Cancelado',
    ];
    return $textos[$estado] ?? ucfirst($estado);
}

/** Badge de Bootstrap listo para imprimir. */
function estado_pedido_badge(string $estado): string
{
    $clases = [
        'pendiente'  => 'bg-warning text-dark',
        'aprobado'   => 'bg-primary',
        'rechazado'  => 'bg-danger',
        'completado' => 'bg-success',
        'cancelado'  => 'bg-secondary',
    ];
    return '<span class="badge ' . ($clases[$estado] ?? 'bg-secondary') . '">' . e(estado_pedido_texto($estado)) . '</span>';
}

/**
 * Valida una ruta interna para "volver" después del login.
 * Solo acepta nombres de página relativos (evita redirecciones a otros sitios).
 */
function ruta_volver_segura(?string $ruta): ?string
{
    $ruta = (string) $ruta;
    return preg_match('#^[a-z0-9_/]+\.php(\?[a-z0-9_=&-]*)?$#i', $ruta) && strpos($ruta, '..') === false
        ? $ruta
        : null;
}
