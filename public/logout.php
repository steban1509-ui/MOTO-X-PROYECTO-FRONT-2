<?php
/** Cierra la sesión (solo por POST con token CSRF). */
require __DIR__ . '/../app/bootstrap.php';

if (es_post()) {
    verificar_csrf();
    Auth::logout();

    // La sesión anterior se destruyó: se abre una nueva para el mensaje flash
    session_start();
    session_regenerate_id(true);
    flash('success', 'Sesión cerrada', 'Vuelve pronto.');
}

redirect('index.php');
