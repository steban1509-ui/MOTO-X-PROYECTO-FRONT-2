<?php
/**
 * LOGIN (antes login.html + php/auth/login.php)
 * El formulario se envía a este mismo archivo. Tras validar las credenciales
 * se leen los roles del usuario (tabla usuario_roles) y se redirige a:
 *   Administrador → admin/index.php
 *   Vendedor      → vendedor/perfil.php
 *   Comprador     → index.php (tienda)
 */
require __DIR__ . '/../app/bootstrap.php';

if (Auth::check()) {
    redirect(Auth::rutaInicio());
}


$correo = '';

// v4: página a la que se vuelve después del login (p. ej. el carrito). Solo rutas internas.
$volver = ruta_volver_segura($_GET['volver'] ?? $_POST['volver'] ?? null);

if (es_post()) {
    verificar_csrf();

    $correo   = trim((string) ($_POST['correo'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $error    = null;

    if ($correo === '' || $password === '') {
        $error = 'Ingresa tu correo y contraseña.';
    } else {
        $usuario = Usuario::buscarPorCorreo($correo);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $error = 'Correo o contraseña incorrectos.';
        } elseif ($usuario['estado'] !== 'activo') {
            $error = 'Tu cuenta está bloqueada. Comunícate con el administrador.';
        } else {
            $roles = Usuario::roles((int) $usuario['id']);

            if (!$roles) {
                $error = 'Tu cuenta no tiene un rol asignado. Comunícate con el administrador.';
            } else {
                if (password_needs_rehash($usuario['password'], PASSWORD_DEFAULT)) {
                    Usuario::actualizarPassword((int) $usuario['id'], $password);
                }

                Auth::login($usuario, $roles);
                Usuario::registrarAcceso((int) $usuario['id']);

                flash('success', '¡Bienvenido!', 'Bienvenido de nuevo, ' . $usuario['nombre'] . '.');
                // ← redirección según el rol. Un comprador puede volver a la página de la que venía;
                //   vendedores y administradores SIEMPRE van a su panel.
                $esCompradorPuro = in_array(Auth::COMPRADOR, $roles, true)
                    && !in_array(Auth::VENDEDOR, $roles, true) && !in_array(Auth::ADMIN, $roles, true);
                redirect($esCompradorPuro && $volver ? $volver : Auth::rutaInicio($roles));
            }
        }
    }

    flash('error', 'No se pudo iniciar sesión', $error);
}

// ---------------- Vista ----------------
$titulo  = 'Login | MotosX';
$estilos = ['css/login.css'];
require VIEW_PATH . '/layouts/head.php';
?>
<div class="container-fluid">
    <div class="row vh-100">

        <!-- IZQUIERDA -->
        <div class="col-lg-6 hero p-0">
            <a href="<?= e(url('index.php')) ?>" class="btn btn-dark btn-home">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <div class="overlay">
                <div class="hero-content">
                    <h1>Bienvenido a MotosX</h1>
                    <p>Inicia sesión y continúa comprando los mejores repuestos compatibles para tu motocicleta.</p>
                </div>
            </div>
        </div>

        <!-- DERECHA -->
        <div class="col-lg-6 d-flex justify-content-center align-items-center">
            <div class="login-box">
                <h1 class="text-center fw-bold mb-2">MotosX</h1>
                <h2 class="text-center mb-4 fw-bold">Iniciar Sesión</h2>

                <form id="formLogin" method="post" action="<?= e(url('login.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($volver): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>

                    <div class="mb-3 mt-3">
                        <label for="correo" class="form-label">Correo:</label>
                        <input type="email" class="form-control" id="correo" name="correo"
                               placeholder="Escriba su correo" value="<?= e($correo) ?>" required autocomplete="email">
                        <div class="valid-feedback">Válido.</div>
                        <div class="text-center invalid-feedback">Por Favor introduzca su Correo Electrónico</div>
                    </div>

                    <div class="mb-3">
                        <label for="pwd" class="form-label">Contraseña:</label>
                        <input type="password" class="form-control" id="pwd" name="password"
                               placeholder="Introduzca su Contraseña" required autocomplete="current-password">
                        <div class="valid-feedback">Válido.</div>
                        <div class="text-center invalid-feedback">Por Favor introduzca su Contraseña.</div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-register w-100" id="btnLogin">Iniciar sesion</button>
                    </div>

                    <div class="text-center mt-4">
                        ¿No tienes una cuenta?
                        <a href="<?= e(url('registro.php')) ?>">Crea sesión aquí</a>
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
// Validación visual de Bootstrap antes de enviar al servidor
document.getElementById('formLogin').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        this.classList.add('was-validated');
        return;
    }
    const boton = document.getElementById('btnLogin');
    boton.disabled = true;
    boton.textContent = 'Ingresando...';
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
