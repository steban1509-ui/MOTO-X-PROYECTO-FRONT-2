<?php
/**
 * REGISTRO DE COMPRADOR (antes registro.html + php/auth/registro.php)
 * Crea el usuario y le asigna el rol "comprador" en usuario_roles.
 */
require __DIR__ . '/../app/bootstrap.php';

if (Auth::check()) {
    redirect(Auth::rutaInicio());
}

$datos   = ['nombre' => '', 'correo' => '', 'telefono' => ''];
$errores = [];

if (es_post()) {
    verificar_csrf();

    $datos = [
        'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
        'correo'   => trim((string) ($_POST['correo'] ?? '')),
        'telefono' => trim((string) ($_POST['telefono'] ?? '')),
    ];
    $password  = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if (!preg_match('/^[\p{L} ]{3,150}$/u', $datos['nombre'])) {
        $errores['nombre'] = 'Escribe tu nombre completo (solo letras).';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'El correo electrónico no es válido.';
    } elseif (Usuario::existeCorreo($datos['correo'])) {
        $errores['correo'] = 'Ese correo ya está registrado.';
    }
    if (!preg_match('/^[0-9+\s-]{7,20}$/', $datos['telefono'])) {
        $errores['telefono'] = 'Escribe un número de teléfono válido.';
    }
    if (strlen($password) < 6) {
        $errores['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $errores['password2'] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        Database::transaccion(function () use ($datos, $password) {
            $usuarioId = Usuario::crear($datos['nombre'], $datos['correo'], $datos['telefono'], $password);
            Usuario::asignarRol($usuarioId, Auth::COMPRADOR);
        });

        flash('success', '¡Cuenta creada!', 'Tu cuenta se creó correctamente. Ahora puedes iniciar sesión.');
        redirect('login.php');
    }

    flash('error', 'No se pudo crear la cuenta', reset($errores));
}

/** Clase de Bootstrap para marcar un campo con error después de enviar. */
$claseCampo = function (string $campo) use ($errores): string {
    if (!es_post()) {
        return '';
    }
    return isset($errores[$campo]) ? ' is-invalid' : ' is-valid';
};

// ---------------- Vista ----------------
$titulo  = 'Registro | MotosX';
$estilos = ['css/registro.css'];
require VIEW_PATH . '/layouts/head.php';
?>
<div class="container-fluid">
<div class="row min-vh-100">

    <!-- Imagen -->
    <div class="col-lg-6 hero p-0">
        <a href="<?= e(url('index.php')) ?>" class="btn btn-dark btn-home">
            <i class="fa-solid fa-arrow-left"></i> Inicio
        </a>
        <div class="overlay">
            <div class="hero-content">
                <h1>Únete a MotosX</h1>
                <p>Crea tu cuenta y comienza a comprar repuestos compatibles para tu motocicleta.</p>
            </div>
        </div>
    </div>

    <!-- Formulario -->
    <div class="col-lg-6 d-flex align-items-center justify-content-center">
        <div class="register-box">
            <h1 class="text-center fw-bold mb-2">MotosX</h1>
            <h2 class="text-center fw-bold mb-4">Crear Cuenta</h2>

            <form id="formRegistro" method="post" action="<?= e(url('registro.php')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3 mt-3">
                    <label for="nombre" class="form-label">Nombre completo:</label>
                    <input type="text" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]+" class="form-control<?= $claseCampo('nombre') ?>" id="nombre"
                           name="nombre" placeholder="Escriba nombre completo" value="<?= e($datos['nombre']) ?>" required>
                    <div class="text-center invalid-feedback"><?= e($errores['nombre'] ?? 'Por Favor introduzca su Nombre completo') ?></div>
                </div>

                <div class="mb-3 mt-3">
                    <label for="correo" class="form-label">Correo:</label>
                    <input type="email" class="form-control<?= $claseCampo('correo') ?>" id="correo" name="correo"
                           placeholder="Escriba su correo" value="<?= e($datos['correo']) ?>" required>
                    <div class="text-center invalid-feedback"><?= e($errores['correo'] ?? 'Por Favor introduzca su Correo Electrónico') ?></div>
                </div>

                <div class="mb-3 mt-3">
                    <label for="telefono" class="form-label">Número de Teléfono:</label>
                    <input type="tel" class="form-control<?= $claseCampo('telefono') ?>" id="telefono" name="telefono"
                           placeholder="Escriba su Número de Teléfono" value="<?= e($datos['telefono']) ?>" required>
                    <div class="text-center invalid-feedback"><?= e($errores['telefono'] ?? 'Por Favor introduzca su Número de Teléfono') ?></div>
                </div>

                <div class="mb-3 mt-3">
                    <label for="pwd" class="form-label">Contraseña:</label>
                    <input type="password" class="form-control<?= isset($errores['password']) ? ' is-invalid' : '' ?>" id="pwd" name="password"
                           placeholder="Escriba su Contraseña" minlength="6" required autocomplete="new-password">
                    <div class="text-center invalid-feedback"><?= e($errores['password'] ?? 'Mínimo 6 caracteres.') ?></div>
                </div>

                <div class="mb-3">
                    <label for="pwd2" class="form-label">Confirmar Contraseña:</label>
                    <input type="password" class="form-control<?= isset($errores['password2']) ? ' is-invalid' : '' ?>" id="pwd2" name="password2"
                           placeholder="Introduzca su Contraseña" minlength="6" required autocomplete="new-password">
                    <div class="text-center invalid-feedback"><?= e($errores['password2'] ?? 'Por Favor introduzca la misma Contraseña.') ?></div>
                </div>

                <div>
                    <button type="submit" class="btn btn-register w-100" id="btnRegistro">Crea sesión aquí</button>
                </div>

                <div class="text-center mt-4">
                    ¿Ya tienes una cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesión aquí</a>
                </div>
                <div class="text-center mt-2">
                    ¿Eres vendedor? <a href="<?= e(url('registro_vendedor.php')) ?>">Regístrate aquí.</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<script>
document.getElementById('formRegistro').addEventListener('submit', function (e) {
    const pwd  = document.getElementById('pwd').value;
    const pwd2 = document.getElementById('pwd2').value;

    if (!this.checkValidity()) {
        e.preventDefault();
        this.classList.add('was-validated');
        return;
    }
    if (pwd !== pwd2) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Las contraseñas no coinciden',
            text: 'Verifica que ambas contraseñas sean iguales.',
            confirmButtonColor: '#212529'
        });
        return;
    }
    const boton = document.getElementById('btnRegistro');
    boton.disabled = true;
    boton.textContent = 'Creando cuenta...';
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
