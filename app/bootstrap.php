<?php


if (is_file(__DIR__ . '/config/config.local.php')) {
    require __DIR__ . '/config/config.local.php';
}
require __DIR__ . '/config/config.php';
require __DIR__ . '/core/helpers.php';

date_default_timezone_set(APP_TIMEZONE);


spl_autoload_register(function (string $clase): void {
    foreach (['core', 'models'] as $carpeta) {
        $archivo = APP_PATH . "/{$carpeta}/{$clase}.php";
        if (is_file($archivo)) {
            require $archivo;
            return;
        }
    }
});

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    $mensaje = APP_DEBUG ? $e->getMessage() : 'Ocurrió un error interno. Intenta de nuevo más tarde.';

    if (es_peticion_api()) {
        json_response(['ok' => false, 'mensaje' => $mensaje], 500);
    }

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error | MotosX</title></head>'
       . '<body style="font-family:Arial;padding:40px;background:#f5f5f5">'
       . '<h1>Algo salió mal</h1><p>' . e($mensaje) . '</p>'
       . '<p><a href="' . e(url('index.php')) . '">Volver al inicio</a></p></body></html>';
});


if (session_status() === PHP_SESSION_NONE) {
    session_name('MOTOSXSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
