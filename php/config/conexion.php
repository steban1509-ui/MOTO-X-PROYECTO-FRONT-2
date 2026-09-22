<?php
/**
 * Conexión a la base de datos MotosX (MySQL) usando PDO.
 * Ajusta estos datos según tu servidor (XAMPP, WAMP, hosting, etc).
 */

$host     = "localhost";
$db_name  = "motox_db";
$username = "root";
$password = "";

try {
    $conexion = new PDO(
        "mysql:host={$host};dbname={$db_name};charset=utf8mb4",
        $username,
        $password
    );

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo conectar a la base de datos: " . $e->getMessage()
    ]);
    exit;
}
