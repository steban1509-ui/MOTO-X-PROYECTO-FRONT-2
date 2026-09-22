<?php
/**
 * =====================================================================
 *  PERFIL DEL VENDEDOR  (controlador + vista en el mismo archivo)
 * =====================================================================
 *  - Ciudad, Dirección, Teléfono y Correo: editables, pero el cambio NO
 *    se aplica en vivo. Se guarda en vendedor_cambios_perfil como
 *    "Pendiente de aprobación" hasta que un administrador lo revise.
 *  - Razón social, NIT, representante legal y documentos: SOLO LECTURA.
 *
 *  Acciones (POST "accion"):
 *    - solicitar_cambio    → crea / reemplaza la solicitud pendiente
 *    - cancelar_solicitud  → retira la solicitud pendiente
 * =====================================================================
 */
require __DIR__ . '/../../app/bootstrap.php';

Auth::requerirRol(Auth::VENDEDOR);

$vendedor = Vendedor::porUsuario((int) Auth::id());

if (!$vendedor) {
    flash('info', 'Completa tu registro', 'Antes de continuar debes enviar los datos y documentos de tu empresa.');
    redirect('registro_vendedor.php');
}

$vendedorId = (int) $vendedor['id'];
$campos     = array_keys(Vendedor::CAMPOS_EDITABLES);

// Valores aprobados (los que ve el público)
$actuales = [
    'ciudad'             => (string) $vendedor['ciudad'],
    'direccion'          => (string) $vendedor['direccion'],
    'telefono_comercial' => (string) $vendedor['telefono_comercial'],
    'correo_comercial'   => (string) ($vendedor['correo_comercial'] ?: $vendedor['correo']),
];

$errores = [];

// =====================================================================
//  PROCESAMIENTO
// =====================================================================
if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'cancelar_solicitud') {
        Vendedor::cancelarSolicitud($vendedorId)
            ? flash('success', 'Solicitud retirada', 'Se descartaron los cambios pendientes.')
            : flash('info', 'Sin cambios pendientes');
        redirect('vendedor/perfil.php');
    }

    if ($accion === 'solicitar_cambio') {
        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        if (mb_strlen($datos['ciudad']) < 3 || mb_strlen($datos['ciudad']) > 100) {
            $errores['ciudad'] = 'Escribe la ciudad (3 a 100 caracteres).';
        }
        if (mb_strlen($datos['direccion']) > 200) {
            $errores['direccion'] = 'La dirección no puede superar 200 caracteres.';
        }
        if ($datos['telefono_comercial'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $datos['telefono_comercial'])) {
            $errores['telefono_comercial'] = 'Escribe un teléfono válido (7 a 20 dígitos).';
        }
        if (!filter_var($datos['correo_comercial'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo_comercial']) > 150) {
            $errores['correo_comercial'] = 'Escribe un correo electrónico válido.';
        }

        if (!$errores) {
            if ($datos === $actuales) {
                // Volvió a dejar los datos aprobados: no hay nada que revisar
                Vendedor::cancelarSolicitud($vendedorId);
                flash('info', 'Sin cambios', 'Los datos son iguales a los aprobados actualmente.');
            } else {
                Vendedor::solicitarCambioPerfil($vendedorId, $datos);
                flash('success', 'Cambios enviados', 'Tus datos quedaron "Pendientes de aprobación". Se publicarán cuando un administrador los apruebe.');
            }
            redirect('vendedor/perfil.php');
        }

        flash('error', 'Revisa el formulario', reset($errores));
    }
}

// =====================================================================
//  DATOS PARA LA VISTA
// =====================================================================
$solicitud  = Vendedor::solicitudPendiente($vendedorId);
$revisada   = Vendedor::ultimaSolicitudRevisada($vendedorId);
$documentos = Vendedor::documentos($vendedorId);

// Qué se muestra en el formulario: lo enviado (si hubo error), lo pendiente, o lo aprobado
$form = $actuales;
if ($solicitud) {
    foreach ($campos as $campo) {
        $form[$campo] = (string) $solicitud[$campo];
    }
}
if ($errores) {
    foreach ($campos as $campo) {
        $form[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
}

$badgeVerificacion = [
    'pendiente'  => ['bg-warning text-dark', 'Cuenta en verificación'],
    'aprobado'   => ['bg-success', 'Cuenta aprobada'],
    'rechazado'  => ['bg-danger', 'Cuenta rechazada'],
    'suspendido' => ['bg-secondary', 'Cuenta suspendida'],
][$vendedor['estado_verificacion']];

$claseCampo = fn (string $c): string => isset($errores[$c]) ? ' is-invalid' : '';
$errorCampo = fn (string $c): string => isset($errores[$c]) ? '<div class="invalid-feedback d-block">' . e($errores[$c]) . '</div>' : '';

$titulo  = 'Perfil del vendedor | MotosX';
$estilos = ['css/panel.css'];
require VIEW_PATH . '/layouts/head.php';
vista('partials/navbar');
?>
<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="panel-titulo mb-0">Perfil del Vendedor <small>· <?= e($vendedor['razon_social']) ?></small></h2>
        <span class="badge fs-6 <?= e($badgeVerificacion[0]) ?>"><?= e($badgeVerificacion[1]) ?></span>
    </div>

    <?php vista('partials/nav_vendedor', ['seccionVendedor' => 'perfil', 'pendientesNav' => Pedido::contarPendientesVendedor($vendedorId)]); ?>

    <?php if ($vendedor['estado_verificacion'] !== 'aprobado'): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-circle-info me-2"></i>
        <?= $vendedor['estado_verificacion'] === 'pendiente'
            ? 'Tus documentos están en revisión. Cuando un administrador apruebe tu cuenta podrás publicar productos.'
            : 'Tu cuenta está ' . e($vendedor['estado_verificacion']) . '. Tus productos no se muestran en la tienda.' ?>
        <?php if ($vendedor['observaciones']): ?><br>Motivo: <?= e($vendedor['observaciones']) ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ======================= DATOS EDITABLES ======================= -->
        <div class="col-lg-7">
            <div class="card card-panel h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fa-solid fa-building me-2"></i>Datos de contacto de la empresa</span>
                    <?php if ($solicitud): ?>
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pendiente de aprobación</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">

                    <?php if ($solicitud): ?>
                    <div class="alert alert-warning small">
                        Enviaste cambios el <?= e(fecha_legible($solicitud['fecha_solicitud'])) ?>.
                        Mientras un administrador los revisa, la tienda sigue mostrando los datos aprobados.
                        Puedes corregir la solicitud y volver a enviarla.
                    </div>
                    <?php elseif ($revisada && $revisada['estado'] === 'rechazado'): ?>
                    <div class="alert alert-danger small">
                        Tu última solicitud de cambio fue <strong>rechazada</strong>
                        (<?= e(fecha_legible($revisada['fecha_revision'])) ?>).
                        <?php if ($revisada['observaciones']): ?>Motivo: <?= e($revisada['observaciones']) ?><?php endif; ?>
                    </div>
                    <?php elseif ($revisada && $revisada['estado'] === 'aprobado'): ?>
                    <div class="alert alert-success small">
                        Tus últimos cambios fueron aprobados el <?= e(fecha_legible($revisada['fecha_revision'])) ?>.
                    </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('vendedor/perfil.php')) ?>" id="formPerfil" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="solicitar_cambio">

                        <div class="row g-3">
                            <?php
                            $tiposInput = ['ciudad' => 'text', 'direccion' => 'text', 'telefono_comercial' => 'tel', 'correo_comercial' => 'email'];
                            $anchos     = ['ciudad' => 'col-md-6', 'telefono_comercial' => 'col-md-6', 'direccion' => 'col-12', 'correo_comercial' => 'col-12'];
                            foreach (['ciudad', 'telefono_comercial', 'direccion', 'correo_comercial'] as $campo): ?>
                            <div class="<?= $anchos[$campo] ?>">
                                <label for="<?= $campo ?>" class="form-label"><?= e(Vendedor::CAMPOS_EDITABLES[$campo]) ?><?= in_array($campo, ['ciudad', 'correo_comercial'], true) ? ' *' : '' ?></label>
                                <input type="<?= $tiposInput[$campo] ?>" class="form-control<?= $claseCampo($campo) ?><?= $solicitud && (string) $solicitud[$campo] !== $actuales[$campo] ? ' campo-pendiente' : '' ?>"
                                       id="<?= $campo ?>" name="<?= $campo ?>" value="<?= e($form[$campo]) ?>"
                                       <?= in_array($campo, ['ciudad', 'correo_comercial'], true) ? 'required' : '' ?>>
                                <?= $errorCampo($campo) ?>
                                <?php if ($solicitud && (string) $solicitud[$campo] !== $actuales[$campo]): ?>
                                    <div class="form-text">Valor aprobado actual: <strong><?= e($actuales[$campo] ?: '—') ?></strong></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 flex-wrap">
                            <?php if ($solicitud): ?>
                            <button type="submit" form="formCancelar" class="btn btn-outline-secondary">Retirar solicitud</button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-naranja">
                                <i class="fa-solid fa-paper-plane me-2"></i><?= $solicitud ? 'Actualizar solicitud' : 'Enviar a aprobación' ?>
                            </button>
                        </div>
                    </form>

                    <form method="post" action="<?= e(url('vendedor/perfil.php')) ?>" id="formCancelar" class="d-none">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="cancelar_solicitud">
                    </form>
                </div>
            </div>
        </div>

        <!-- ======================= SOLO LECTURA ======================= -->
        <div class="col-lg-5">
            <div class="card card-panel mb-4">
                <div class="card-header"><i class="fa-solid fa-lock me-2"></i>Datos legales <small class="text-muted fw-normal">(solo lectura)</small></div>
                <div class="card-body">
                    <?php foreach ([
                        'Razón social'            => $vendedor['razon_social'],
                        'NIT'                     => $vendedor['nit'],
                        'Representante legal'     => $vendedor['representante_legal'],
                        'Cédula del representante'=> $vendedor['cedula_representante'],
                    ] as $etiqueta => $valor): ?>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1"><?= e($etiqueta) ?></label>
                        <input type="text" class="form-control form-control-sm" value="<?= e($valor) ?>" readonly disabled>
                    </div>
                    <?php endforeach; ?>
                    <p class="small text-muted mb-0 mt-3">Para modificar estos datos comunícate con el administrador de MotosX.</p>
                </div>
            </div>

            <div class="card card-panel">
                <div class="card-header"><i class="fa-solid fa-file-shield me-2"></i>Documentos legales <small class="text-muted fw-normal">(solo lectura)</small></div>
                <div class="card-body">
                    <?php foreach (Vendedor::DOCUMENTOS as $tipo => $etiqueta): $doc = $documentos[$tipo] ?? null; ?>
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1"><?= e($etiqueta) ?></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fa-solid <?= $doc ? 'fa-file-circle-check text-success' : 'fa-file-circle-xmark text-danger' ?>"></i></span>
                            <input type="text" class="form-control" value="<?= e($doc ? $doc['nombre_original'] : 'No adjuntado') ?>" readonly disabled>
                            <?php if ($doc): ?>
                            <a class="btn btn-outline-dark" target="_blank" href="<?= e(url('documento.php?vendedor=' . $vendedorId . '&tipo=' . $tipo)) ?>">Ver</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-light text-center py-4 mt-4">© <?= date('Y') ?> MotosX. Todos los derechos reservados.</footer>

<script>
document.getElementById('formPerfil').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        this.classList.add('was-validated');
        Swal.fire({ icon: 'warning', title: 'Revisa los datos', text: 'Ciudad y correo electrónico son obligatorios.', confirmButtonColor: '#212529' });
    }
});
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
