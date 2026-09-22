<?php
/** Muestra los mensajes flash pendientes con SweetAlert2 (uno detrás de otro). */
$mensajesFlash = flashes();
if ($mensajesFlash): ?>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const mensajes = <?= json_encode($mensajesFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    for (const m of mensajes) {
        await Swal.fire({
            icon: m.tipo,
            title: m.titulo,
            text: m.mensaje,
            confirmButtonColor: '#212529'
        });
    }
});
</script>
<?php endif; ?>
