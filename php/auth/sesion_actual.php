<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['usuario_id'])) {
    echo json_encode([
        "ok" => true,
        "logueado" => true,
        "nombre" => $_SESSION['usuario_nombre'],
        "correo" => $_SESSION['usuario_correo']
    ]);
} else {
    echo json_encode([
        "ok" => true,
        "logueado" => false
    ]);
}
