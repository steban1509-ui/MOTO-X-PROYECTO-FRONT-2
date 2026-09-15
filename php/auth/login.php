<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

$data = json_decode(file_get_contents("php://input"), true);

$correo   = trim($data['correo'] ?? '');
$password = $data['password'] ?? '';

if ($correo === '' || $password === '') {
    echo json_encode(["ok" => false, "mensaje" => "Ingresa tu correo y contraseña."]);
    exit;
}

try {
    $stmt = $conexion->prepare(
        "SELECT id, nombre, correo, password, tipo_usuario
         FROM usuarios
         WHERE correo = :correo"
    );
    $stmt->execute([':correo' => $correo]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password'])) {
        echo json_encode(["ok" => false, "mensaje" => "Correo o contraseña incorrectos."]);
        exit;
    }

    // Guardar la sesión del usuario
    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_correo'] = $usuario['correo'];
    $_SESSION['tipo_usuario']   = $usuario['tipo_usuario'];

    echo json_encode([
        "ok" => true,
        "mensaje" => "Bienvenido de nuevo, " . $usuario['nombre'] . ".",
        "nombre" => $usuario['nombre']
    ]);

} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error al iniciar sesión: " . $e->getMessage()]);
}
