<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

$data = json_decode(file_get_contents("php://input"), true);

$nombre    = trim($data['nombre'] ?? '');
$correo    = trim($data['correo'] ?? '');
$telefono  = trim($data['telefono'] ?? '');
$password  = $data['password'] ?? '';
$password2 = $data['password2'] ?? '';

if ($nombre === '' || $correo === '' || $telefono === '' || $password === '') {
    echo json_encode(["ok" => false, "mensaje" => "Todos los campos son obligatorios."]);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "mensaje" => "El correo electrónico no es válido."]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(["ok" => false, "mensaje" => "La contraseña debe tener al menos 6 caracteres."]);
    exit;
}

if ($password !== $password2) {
    echo json_encode(["ok" => false, "mensaje" => "Las contraseñas no coinciden."]);
    exit;
}

try {
    // Verificar que el correo no exista ya
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE correo = :correo");
    $stmt->execute([':correo' => $correo]);

    if ($stmt->fetch()) {
        echo json_encode(["ok" => false, "mensaje" => "Ese correo ya está registrado."]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conexion->prepare(
        "INSERT INTO usuarios (nombre, correo, telefono, password)
         VALUES (:nombre, :correo, :telefono, :password)"
    );

    $stmt->execute([
        ':nombre'   => $nombre,
        ':correo'   => $correo,
        ':telefono' => $telefono,
        ':password' => $hash
    ]);

    echo json_encode([
        "ok" => true,
        "mensaje" => "Tu cuenta se creó correctamente. Ahora puedes iniciar sesión."
    ]);

} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error al registrar el usuario: " . $e->getMessage()]);
}
