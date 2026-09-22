<?php
/**
 * REGISTRO EXCLUSIVO DE VENDEDOR
 * Solicita los datos de la empresa y los documentos legales:
 *   - Certificado de Cámara de Comercio
 *   - RUT
 *   - Estados financieros
 *   - Cédula del representante legal
 *
 * Casos (v4, perfiles exclusivos):
 *   a) Visitante nuevo → crea usuario + rol vendedor + datos de vendedor.
 *   b) Cuenta con rol vendedor sin datos de empresa (migrada de v2) → completa el registro.
 *   Compradores y administradores NO pueden convertirse en vendedores con la misma cuenta.
 */
require __DIR__ . '/../app/bootstrap.php';

$usuarioActual = Auth::usuario();

if ($usuarioActual) {
    if (Vendedor::porUsuario((int) $usuarioActual['id'])) {
        redirect('vendedor/perfil.php');
    }
    Auth::refrescar();
    if (!Auth::tieneRol(Auth::VENDEDOR)) {
        flash('info', 'Cuenta no válida para vender', 'Las cuentas de comprador y administrador no pueden ser vendedoras. Cierra sesión y registra tu tienda con otro correo.');
        redirect(Auth::rutaInicio());
    }
    $usuarioActual = Auth::usuario();
}

$necesitaCuenta = $usuarioActual === null;

$datos = [
    'nombre' => '', 'correo' => '', 'telefono' => '',
    'razon_social' => '', 'nit' => '', 'ciudad' => '', 'direccion' => '', 'telefono_comercial' => '',
    'representante_legal' => '', 'cedula_representante' => '',
];
$errores = [];

if (es_post()) {
    verificar_csrf();

    foreach (array_keys($datos) as $campo) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $password  = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    // ---------- 1. Datos de la cuenta (solo visitantes) ----------
    if ($necesitaCuenta) {
        if (!preg_match('/^[\p{L} ]{3,150}$/u', $datos['nombre'])) {
            $errores['nombre'] = 'Escribe el nombre completo de la persona de contacto.';
        }
        if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'El correo electrónico no es válido.';
        } elseif (Usuario::existeCorreo($datos['correo'])) {
            $errores['correo'] = 'Ese correo ya está registrado. Inicia sesión y vuelve a esta página para convertir tu cuenta en vendedor.';
        }
        if (!preg_match('/^[0-9+\s-]{7,20}$/', $datos['telefono'])) {
            $errores['telefono'] = 'Escribe un teléfono válido.';
        }
        if (strlen($password) < 8) {
            $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $password2) {
            $errores['password2'] = 'Las contraseñas no coinciden.';
        }
    }

    // ---------- 2. Datos de la empresa ----------
    if (mb_strlen($datos['razon_social']) < 3 || mb_strlen($datos['razon_social']) > 150) {
        $errores['razon_social'] = 'Escribe la razón social o nombre de la tienda.';
    }
    if (!preg_match('/^\d{6,12}(-\d)?$/', $datos['nit'])) {
        $errores['nit'] = 'NIT inválido. Formato: 900123456-7';
    } elseif (Vendedor::existeNit($datos['nit'])) {
        $errores['nit'] = 'Ya existe un vendedor registrado con ese NIT.';
    }
    if (mb_strlen($datos['ciudad']) < 3 || mb_strlen($datos['ciudad']) > 100) {
        $errores['ciudad'] = 'Escribe la ciudad.';
    }
    if (mb_strlen($datos['direccion']) > 200) {
        $errores['direccion'] = 'La dirección es demasiado larga.';
    }
    if ($datos['telefono_comercial'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $datos['telefono_comercial'])) {
        $errores['telefono_comercial'] = 'Teléfono comercial inválido.';
    }
    if (!preg_match('/^[\p{L} ]{3,150}$/u', $datos['representante_legal'])) {
        $errores['representante_legal'] = 'Escribe el nombre del representante legal.';
    }
    if (!preg_match('/^\d{5,15}$/', $datos['cedula_representante'])) {
        $errores['cedula_representante'] = 'Número de cédula inválido (solo números).';
    }
    if (empty($_POST['acepta_terminos'])) {
        $errores['acepta_terminos'] = 'Debes autorizar el tratamiento de datos.';
    }

    // ---------- 3. Documentos ----------
    $archivos = [];
    foreach (array_keys(Vendedor::DOCUMENTOS) as $tipo) {
        $archivo = isset($_FILES['documentos']['name'][$tipo]) ? [
            'name'     => $_FILES['documentos']['name'][$tipo],
            'type'     => $_FILES['documentos']['type'][$tipo],
            'tmp_name' => $_FILES['documentos']['tmp_name'][$tipo],
            'error'    => $_FILES['documentos']['error'][$tipo],
            'size'     => $_FILES['documentos']['size'][$tipo],
        ] : null;

        $errorArchivo = Uploader::validar($archivo, Uploader::DOCUMENTOS, MAX_DOCUMENTO_BYTES);
        if ($errorArchivo) {
            $errores['doc_' . $tipo] = $errorArchivo;
        } else {
            $archivos[$tipo] = $archivo;
        }
    }

    // ---------- 4. Guardar todo en una transacción ----------
    if (!$errores) {
        $archivosMovidos = [];
        try {
            $pdo = Database::conexion();
            $pdo->beginTransaction();

            $usuarioId = $necesitaCuenta
                ? Usuario::crear($datos['nombre'], $datos['correo'], $datos['telefono'], $password)
                : (int) $usuarioActual['id'];

            Usuario::asignarRol($usuarioId, Auth::VENDEDOR);
            $datos['correo_comercial'] = $necesitaCuenta ? $datos['correo'] : $usuarioActual['correo'];
            $vendedorId = Vendedor::crear($usuarioId, $datos);

            $carpeta = DOCS_VENDEDORES_DIR . '/' . $vendedorId;
            foreach ($archivos as $tipo => $archivo) {
                $mime   = Uploader::mimeReal($archivo['tmp_name']);
                $nombre = Uploader::guardar($archivo, $carpeta, Uploader::DOCUMENTOS);
                $archivosMovidos[] = $carpeta . '/' . $nombre;

                Vendedor::guardarDocumento($vendedorId, $tipo, $vendedorId . '/' . $nombre, $archivo['name'], $mime, (int) $archivo['size']);
            }

            $pdo->commit();
        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($archivosMovidos as $ruta) {
                Uploader::eliminar($ruta);
            }
            error_log((string) $ex);
            $errores['general'] = APP_DEBUG ? $ex->getMessage() : 'No pudimos completar el registro. Intenta de nuevo.';
        }

        if (!$errores) {
            $mensaje = VENDEDOR_REQUIERE_APROBACION
                ? 'Recibimos tus documentos. Un administrador los revisará y te habilitará para publicar productos.'
                : 'Ya puedes publicar tus productos.';

            if ($necesitaCuenta) {
                flash('success', '¡Registro de vendedor enviado!', $mensaje . ' Inicia sesión para ver tu perfil.');
                redirect('login.php');
            }

            Auth::refrescar();
            flash('success', '¡Registro completado!', $mensaje);
            redirect('vendedor/perfil.php');
        }
    }

    flash('error', 'Revisa el formulario', reset($errores));
}

$claseCampo = function (string $campo) use ($errores): string {
    return isset($errores[$campo]) ? ' is-invalid' : '';
};
$errorCampo = function (string $campo) use ($errores): string {
    return isset($errores[$campo]) ? '<div class="invalid-feedback d-block">' . e($errores[$campo]) . '</div>' : '';
};

// ---------------- Vista ----------------
$titulo  = 'Registro de vendedor | MotosX';
$estilos = ['css/registro.css'];
require VIEW_PATH . '/layouts/head.php';
?>
<div class="container-fluid">
<div class="row min-vh-100">

    <div class="col-lg-5 hero p-0">
        <a href="<?= e(url('index.php')) ?>" class="btn btn-dark btn-home">
            <i class="fa-solid fa-arrow-left"></i> Inicio
        </a>
        <div class="overlay">
            <div class="hero-content">
                <h1>Vende en MotosX</h1>
                <p>Registra tu tienda, adjunta tus documentos legales y empieza a vender repuestos a miles de motociclistas.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-7 d-flex justify-content-center py-5">
        <div class="register-box register-box-amplio">
            <h1 class="text-center fw-bold mb-2">MotosX</h1>
            <h2 class="text-center fw-bold mb-2">Registro de Vendedor</h2>
            <p class="text-center text-muted mb-4">Todos los campos marcados con * son obligatorios.</p>

            <form id="formVendedor" method="post" action="<?= e(url('registro_vendedor.php')) ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <?php if ($necesitaCuenta): ?>
                <!-- 1. Cuenta -->
                <h5 class="seccion-form"><i class="fa-solid fa-user me-2"></i>1. Datos de acceso</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label">Nombre de contacto *</label>
                        <input type="text" class="form-control<?= $claseCampo('nombre') ?>" id="nombre" name="nombre" value="<?= e($datos['nombre']) ?>" required>
                        <?= $errorCampo('nombre') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label">Teléfono *</label>
                        <input type="tel" class="form-control<?= $claseCampo('telefono') ?>" id="telefono" name="telefono" value="<?= e($datos['telefono']) ?>" required>
                        <?= $errorCampo('telefono') ?>
                    </div>
                    <div class="col-12">
                        <label for="correo" class="form-label">Correo *</label>
                        <input type="email" class="form-control<?= $claseCampo('correo') ?>" id="correo" name="correo" value="<?= e($datos['correo']) ?>" required>
                        <?= $errorCampo('correo') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="pwd" class="form-label">Contraseña *</label>
                        <input type="password" class="form-control<?= $claseCampo('password') ?>" id="pwd" name="password" minlength="8" required autocomplete="new-password">
                        <?= $errorCampo('password') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="pwd2" class="form-label">Confirmar contraseña *</label>
                        <input type="password" class="form-control<?= $claseCampo('password2') ?>" id="pwd2" name="password2" minlength="8" required autocomplete="new-password">
                        <?= $errorCampo('password2') ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    Completa los datos de la empresa para la cuenta de vendedor <strong><?= e($usuarioActual['correo']) ?></strong>.
                </div>
                <?php endif; ?>

                <!-- 2. Empresa -->
                <h5 class="seccion-form mt-4"><i class="fa-solid fa-store me-2"></i><?= $necesitaCuenta ? '2' : '1' ?>. Datos de la empresa</h5>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="razon_social" class="form-label">Razón social / nombre de la tienda *</label>
                        <input type="text" class="form-control<?= $claseCampo('razon_social') ?>" id="razon_social" name="razon_social" maxlength="150" value="<?= e($datos['razon_social']) ?>" required>
                        <?= $errorCampo('razon_social') ?>
                    </div>
                    <div class="col-md-5">
                        <label for="nit" class="form-label">NIT *</label>
                        <input type="text" class="form-control<?= $claseCampo('nit') ?>" id="nit" name="nit" placeholder="900123456-7" pattern="\d{6,12}(-\d)?" value="<?= e($datos['nit']) ?>" required>
                        <?= $errorCampo('nit') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="ciudad" class="form-label">Ciudad *</label>
                        <input type="text" class="form-control<?= $claseCampo('ciudad') ?>" id="ciudad" name="ciudad" placeholder="Bogotá, Colombia" value="<?= e($datos['ciudad']) ?>" required>
                        <?= $errorCampo('ciudad') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="telefono_comercial" class="form-label">Teléfono comercial</label>
                        <input type="tel" class="form-control<?= $claseCampo('telefono_comercial') ?>" id="telefono_comercial" name="telefono_comercial" value="<?= e($datos['telefono_comercial']) ?>">
                        <?= $errorCampo('telefono_comercial') ?>
                    </div>
                    <div class="col-12">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control<?= $claseCampo('direccion') ?>" id="direccion" name="direccion" maxlength="200" value="<?= e($datos['direccion']) ?>">
                        <?= $errorCampo('direccion') ?>
                    </div>
                    <div class="col-md-7">
                        <label for="representante_legal" class="form-label">Representante legal *</label>
                        <input type="text" class="form-control<?= $claseCampo('representante_legal') ?>" id="representante_legal" name="representante_legal" value="<?= e($datos['representante_legal']) ?>" required>
                        <?= $errorCampo('representante_legal') ?>
                    </div>
                    <div class="col-md-5">
                        <label for="cedula_representante" class="form-label">Cédula del representante *</label>
                        <input type="text" inputmode="numeric" class="form-control<?= $claseCampo('cedula_representante') ?>" id="cedula_representante" name="cedula_representante" pattern="\d{5,15}" value="<?= e($datos['cedula_representante']) ?>" required>
                        <?= $errorCampo('cedula_representante') ?>
                    </div>
                </div>

                <!-- 3. Documentos -->
                <h5 class="seccion-form mt-4"><i class="fa-solid fa-file-shield me-2"></i><?= $necesitaCuenta ? '3' : '2' ?>. Documentos legales</h5>
                <p class="text-muted small">Formatos permitidos: PDF, JPG o PNG. Máximo <?= MAX_DOCUMENTO_BYTES / 1048576 ?> MB por archivo.</p>
                <div class="row g-3">
                    <?php foreach (Vendedor::DOCUMENTOS as $tipo => $etiqueta): ?>
                    <div class="col-md-6">
                        <label for="doc_<?= e($tipo) ?>" class="form-label"><?= e($etiqueta) ?> *</label>
                        <input type="file" class="form-control input-archivo<?= $claseCampo('doc_' . $tipo) ?>" id="doc_<?= e($tipo) ?>"
                               name="documentos[<?= e($tipo) ?>]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                        <?= $errorCampo('doc_' . $tipo) ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-check mt-4">
                    <input class="form-check-input<?= $claseCampo('acepta_terminos') ?>" type="checkbox" value="1" id="acepta_terminos" name="acepta_terminos" required>
                    <label class="form-check-label" for="acepta_terminos">
                        Declaro que la información es verídica y autorizo a MotosX el tratamiento de mis datos y documentos para verificar mi empresa.
                    </label>
                    <?= $errorCampo('acepta_terminos') ?>
                </div>

                <?= $errorCampo('general') ?>

                <button type="submit" class="btn btn-register w-100 mt-4" id="btnVendedor">Enviar registro de vendedor</button>

                <div class="text-center mt-4">
                    ¿Ya tienes una cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesión aquí</a>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<script>
const MAX_DOC_BYTES = <?= MAX_DOCUMENTO_BYTES ?>;

document.getElementById('formVendedor').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        this.classList.add('was-validated');
        Swal.fire({ icon: 'warning', title: 'Faltan datos', text: 'Completa todos los campos obligatorios y adjunta los 4 documentos.', confirmButtonColor: '#212529' });
        return;
    }

    const pwd = document.getElementById('pwd');
    if (pwd && pwd.value !== document.getElementById('pwd2').value) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Las contraseñas no coinciden', confirmButtonColor: '#212529' });
        return;
    }

    for (const input of this.querySelectorAll('input[type=file]')) {
        if (input.files[0] && input.files[0].size > MAX_DOC_BYTES) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Archivo muy pesado', text: input.files[0].name + ' supera el tamaño máximo.', confirmButtonColor: '#212529' });
            return;
        }
    }

    const boton = document.getElementById('btnVendedor');
    boton.disabled = true;
    boton.textContent = 'Enviando documentos...';
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
