<?php
/**
 * Cierre común del <body>: configuración JS, Bootstrap, scripts de la página
 * y mensajes flash con SweetAlert2.
 *   $scripts array  ['js/carrito.js', ...]
 */
$usuarioSesion = Auth::usuario();
$configJs = [
    'baseUrl'  => base_url(),
    'csrf'     => csrf_token(),
    'logueado' => $usuarioSesion !== null,
    // v4: visitantes y compradores pueden usar el carrito; vendedores y admins no
    'puedeComprar' => Auth::puedeComprar(),
    'esComprador'  => Auth::esComprador(),
    'usuario'  => $usuarioSesion ? ['nombre' => $usuarioSesion['nombre'], 'correo' => $usuarioSesion['correo']] : null,
];
?>
<script>
    window.MOTOSX = <?= json_encode($configJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if ($usarBootstrap ?? true): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<?php foreach (($scripts ?? []) as $archivoJs): ?>
<script src="<?= e(asset($archivoJs)) ?>"></script>
<?php endforeach; ?>
<?php require VIEW_PATH . '/partials/flash.php'; ?>
</body>
</html>
