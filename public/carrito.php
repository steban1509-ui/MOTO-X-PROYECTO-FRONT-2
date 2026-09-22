<?php
/**
 * CARRITO
 * - Visitantes y compradores pueden usarlo; vendedores y administradores NO (v4).
 * - Finalizar la compra exige sesión de COMPRADOR.
 * - Al cargar, el carrito se sincroniza con el stock real (api/stock.php) y no
 *   permite aumentar cantidades por encima del stock disponible.
 * - El servidor vuelve a validar el stock al crear el pedido (api/crear_pedido.php).
 */
require __DIR__ . '/../app/bootstrap.php';

Auth::bloquearStaff();

$titulo        = 'Carrito - MotosX';
$estilos       = ['css/carrito.css'];
$usarBootstrap = false; // carrito.css tiene su propio .container
require VIEW_PATH . '/layouts/head.php';
?>
<div class="container">

    <div class="left">
        <div class="overlay">
            <h1>Tu compra está casi lista</h1>
            <p>Revisa tus productos seleccionados y continúa con el proceso de compra.</p>
        </div>
    </div>

    <div class="right">

        <a href="<?= e(url('index.php')) ?>" class="btn-volver">← Inicio</a>

        <h1 class="titulo">MI CARRITO 🛒</h1>

        <div class="productos-scroll">
            <div id="lista-carrito"></div>
        </div>

        <div class="total">
            <h2 id="total">Total: $0</h2>

            <div class="acciones">
                <a href="<?= e(url('index.php')) ?>" class="btn-seguir">← Seguir comprando</a>
                <button class="btn-vaciar" type="button" onclick="confirmarVaciarCarrito()">Vaciar carrito</button>
                <button class="btn-comprar" type="button" id="btnComprar" onclick="finalizarCompra()">Finalizar compra</button>
            </div>
        </div>

        <footer>
            <p>© <?= date('Y') ?> MotosX. Todos los derechos reservados.</p>
        </footer>
    </div>
</div>

<script src="<?= e(asset('js/carrito.js')) ?>"></script>
<script>
let carrito = leerCarrito();
const lista = document.getElementById('lista-carrito');
let total = 0;

function mostrarCarrito() {
    lista.innerHTML = '';
    total = 0;

    if (carrito.length === 0) {
        lista.innerHTML = '<div class="producto"><h2>Tu carrito está vacío</h2></div>';
        document.getElementById('total').textContent = 'Total: $0';
        return;
    }

    carrito.forEach((producto, indice) => {
        total += producto.precio * producto.cantidad;
        const stock = Number(producto.stock) || 0;
        const tope  = producto.cantidad >= stock;

        // Se escapan los textos: el nombre del producto lo escribe un vendedor
        lista.innerHTML += `
        <div class="producto">
            <img src="${escaparHtml(producto.imagen)}" alt="${escaparHtml(producto.nombre)}" class="producto-img">
            <div class="info">
                <h2>${escaparHtml(producto.nombre)}</h2>
                <p class="precio">${formatoPrecio(producto.precio)}</p>
                <p class="stock-disponible ${stock <= 5 ? 'stock-bajo' : ''}">Stock disponible: ${stock}</p>
                <div class="cantidad">
                    <button class="btn-cantidad" onclick="disminuirCantidad(${indice})">-</button>
                    <span>${Number(producto.cantidad)}</span>
                    <button class="btn-cantidad" onclick="aumentarCantidad(${indice})" ${tope ? 'disabled title="Llegaste al stock disponible"' : ''}>+</button>
                </div>
                <button class="btn-eliminar" onclick="eliminarProducto(${indice})">🗑️ Eliminar</button>
            </div>
        </div>`;
    });

    document.getElementById('total').textContent = 'Total: ' + formatoPrecio(total);
}

function aumentarCantidad(indice) {
    const item = carrito[indice];
    if (item.cantidad >= Number(item.stock)) {
        alertaStock(item.nombre, Number(item.stock), item.cantidad);
        return;
    }
    item.cantidad++;
    guardarCarrito(carrito);
    mostrarCarrito();
}

function disminuirCantidad(indice) {
    if (carrito[indice].cantidad > 1) {
        carrito[indice].cantidad--;
    } else {
        carrito.splice(indice, 1);
    }
    guardarCarrito(carrito);
    mostrarCarrito();
}

function eliminarProducto(indice) {
    carrito.splice(indice, 1);
    guardarCarrito(carrito);
    mostrarCarrito();
}

function vaciarCarrito() {
    localStorage.removeItem('carrito');
    carrito = [];
    mostrarCarrito();
}

function confirmarVaciarCarrito() {
    if (carrito.length === 0) {
        Swal.fire({ icon: 'info', title: 'Tu carrito ya está vacío', confirmButtonColor: '#212529' });
        return;
    }
    Swal.fire({
        icon: 'warning',
        title: '¿Vaciar el carrito?',
        text: 'Se eliminarán todos los productos seleccionados.',
        showCancelButton: true,
        confirmButtonText: 'Sí, vaciar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((resultado) => {
        if (resultado.isConfirmed) {
            vaciarCarrito();
            Swal.fire({ icon: 'success', title: 'Carrito vaciado', confirmButtonColor: '#212529' });
        }
    });
}

/** Sincroniza con el servidor y avisa si algo cambió. Devuelve true si no hubo cambios. */
async function refrescarStock() {
    try {
        const resultado = await sincronizarStockCarrito();
        carrito = resultado.carrito;
        mostrarCarrito();
        if (resultado.avisos.length) {
            await Swal.fire({
                icon: 'warning',
                title: 'Actualizamos tu carrito',
                html: resultado.avisos.map(a => '• ' + escaparHtml(a)).join('<br>'),
                confirmButtonColor: '#212529'
            });
            return false;
        }
    } catch (e) {
        // Sin conexión: se deja el carrito como está; el servidor validará al comprar
    }
    return true;
}

async function finalizarCompra() {
    if (carrito.length === 0) {
        Swal.fire({ icon: 'info', title: 'Tu carrito está vacío', text: 'Añade productos antes de finalizar la compra.', confirmButtonColor: '#212529' });
        return;
    }

    // v4: solo compradores con sesión pueden finalizar la compra
    if (!MOTOSX.esComprador) {
        const volver = encodeURIComponent('carrito.php');
        Swal.fire({
            icon: 'info',
            title: 'Inicia sesión para comprar',
            html: 'Necesitas una cuenta de comprador para finalizar tu pedido.<br><br>' +
                  '¿No tienes cuenta? <a href="' + MOTOSX.baseUrl + '/registro.php">Regístrate aquí</a>',
            showCancelButton: true,
            confirmButtonText: 'Iniciar sesión',
            cancelButtonText: 'Seguir viendo',
            confirmButtonColor: '#212529'
        }).then(r => {
            if (r.isConfirmed) window.location.href = MOTOSX.baseUrl + '/login.php?volver=' + volver;
        });
        return;
    }

    // Revalidar stock antes de enviar: si algo cambió, el usuario revisa de nuevo
    if (!(await refrescarStock()) || carrito.length === 0) {
        return;
    }

    const confirmacion = await Swal.fire({
        icon: 'question',
        title: 'Confirmar compra',
        text: 'Total a pagar: ' + formatoPrecio(total),
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#212529'
    });
    if (confirmacion.isConfirmed) {
        enviarPedido();
    }
}

function enviarPedido() {
    const boton = document.getElementById('btnComprar');
    boton.disabled = true;

    fetch(MOTOSX.baseUrl + '/api/crear_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': MOTOSX.csrf },
        body: JSON.stringify({ items: carrito.map(p => ({ id: p.id, cantidad: p.cantidad })) })
    })
    .then(res => res.json())
    .then(data => {
        boton.disabled = false;

        if (data.ok) {
            Swal.fire({
                icon: 'success',
                title: '¡Compra realizada!',
                html: 'Pedido <strong>#' + data.pedido_id + '</strong> registrado por ' + formatoPrecio(data.total) +
                      '.<br>El vendedor debe aprobarlo; puedes seguirlo en <a href="' + MOTOSX.baseUrl + '/perfil.php">Mis compras</a>.',
                confirmButtonColor: '#212529'
            }).then(() => vaciarCarrito());
            return;
        }

        // Productos que el vendedor ocultó mientras estaban en el carrito
        if (Array.isArray(data.no_disponibles) && data.no_disponibles.length) {
            carrito = carrito.filter(p => !data.no_disponibles.includes(Number(p.id)));
        }

        // Stock insuficiente: se ajusta el carrito al stock real
        if (Array.isArray(data.sin_stock) && data.sin_stock.length) {
            data.sin_stock.forEach(info => {
                const item = carrito.find(p => Number(p.id) === Number(info.id));
                if (!item) return;
                item.stock = info.disponible;
                item.cantidad = Math.min(item.cantidad, info.disponible);
            });
            carrito = carrito.filter(p => p.cantidad > 0);

            guardarCarrito(carrito);
            mostrarCarrito();

            Swal.fire({
                icon: 'error',
                title: 'Stock insuficiente',
                html: 'No se pudo procesar tu orden porque algunos productos no tienen unidades suficientes:<br><br>' +
                      data.sin_stock.map(p => '• <strong>' + escaparHtml(p.nombre) + '</strong>: pediste ' + p.solicitado + ', disponibles ' + p.disponible).join('<br>') +
                      '<br><br>Ajustamos tu carrito al stock disponible. Revísalo y vuelve a intentarlo.',
                confirmButtonColor: '#212529'
            });
            return;
        }

        guardarCarrito(carrito);
        mostrarCarrito();
        Swal.fire({ icon: 'error', title: 'No se pudo procesar la compra', text: data.mensaje, confirmButtonColor: '#212529' });
    })
    .catch(() => {
        boton.disabled = false;
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo contactar al servidor. Intenta de nuevo.', confirmButtonColor: '#212529' });
    });
}

mostrarCarrito();
document.addEventListener('DOMContentLoaded', refrescarStock);
</script>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
