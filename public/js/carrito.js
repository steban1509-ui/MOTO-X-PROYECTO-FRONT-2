/**
 * Carrito de compras en localStorage.
 *
 * v4 — control de stock en el navegador:
 *  - Cada producto guarda su "stock" disponible.
 *  - No se puede agregar ni aumentar por encima de ese stock.
 *  - sincronizarStockCarrito() consulta api/stock.php para actualizar stock y
 *    precios reales (el servidor vuelve a validar todo al finalizar la compra).
 */

function leerCarrito() {
    try {
        const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
        return Array.isArray(carrito) ? carrito.filter(p => p && Number(p.id) > 0) : [];
    } catch (e) {
        return [];
    }
}

function guardarCarrito(carrito) {
    localStorage.setItem('carrito', JSON.stringify(carrito));
}

function escaparHtml(texto) {
    return String(texto ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatoPrecio(valor) {
    return '$' + Number(valor).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

function alertaStock(nombre, stock, enCarrito) {
    let texto;
    if (stock <= 0) {
        texto = "\"" + nombre + "\" está agotado.";
    } else {
        texto = "Solo hay " + stock + (stock === 1 ? " unidad disponible" : " unidades disponibles") + " de \"" + nombre + "\"";
        texto += enCarrito > 0 ? " y ya tienes " + enCarrito + " en tu carrito." : ".";
    }
    Swal.fire({ icon: "warning", title: "Stock insuficiente", text: texto, confirmButtonColor: "#212529" });
}

/** Usado por la Card de PHP: lee los datos desde los atributos data-* del botón. */
function agregarCarritoDesdeBoton(boton, event) {
    agregarCarrito(
        Number(boton.dataset.id),
        boton.dataset.nombre,
        Number(boton.dataset.precio),
        boton.dataset.imagen,
        event,
        Number(boton.dataset.stock),
        1
    );
}

/**
 * Agrega unidades al carrito respetando el stock.
 * Devuelve true si se agregó.
 */
function agregarCarrito(id, nombre, precio, imagen, event, stock, cantidad = 1) {
    if (window.MOTOSX && !MOTOSX.puedeComprar) {
        Swal.fire({ icon: 'info', title: 'Función exclusiva de compradores', text: 'Las cuentas de vendedor y administrador no pueden comprar.', confirmButtonColor: '#212529' });
        return false;
    }

    stock    = Number.isFinite(Number(stock)) ? Math.max(0, Number(stock)) : 0;
    cantidad = Math.max(1, Number(cantidad) || 1);

    const carrito   = leerCarrito();
    const existente = carrito.find(producto => Number(producto.id) === Number(id));
    const enCarrito = existente ? Number(existente.cantidad) : 0;

    if (enCarrito + cantidad > stock) {
        alertaStock(nombre, stock, enCarrito);
        return false;
    }

    if (existente) {
        existente.cantidad = enCarrito + cantidad;
        existente.stock    = stock;
        existente.precio   = precio;
    } else {
        carrito.push({ id: Number(id), nombre, precio, imagen, stock, cantidad });
    }

    guardarCarrito(carrito);
    actualizarContadorCarrito();

    if (event) {
        animarAlCarrito(event, imagen);
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: nombre + ' añadido al carrito',
            showConfirmButton: false,
            timer: 1600,
            timerProgressBar: true
        });
    }
    return true;
}

/**
 * Pide al servidor el stock y precio actuales de lo que hay en el carrito.
 * Ajusta cantidades, quita productos agotados/ocultos y devuelve los avisos.
 */
async function sincronizarStockCarrito() {
    const carrito = leerCarrito();
    if (!carrito.length || !window.MOTOSX) return { carrito, avisos: [] };

    const ids = carrito.map(p => p.id).join(',');
    const respuesta = await fetch(MOTOSX.baseUrl + '/api/stock.php?ids=' + encodeURIComponent(ids));
    const datos = await respuesta.json();
    if (!datos.ok) return { carrito, avisos: [] };

    const avisos = [];
    const actualizado = [];

    carrito.forEach(item => {
        const info = datos.productos[item.id];

        if (!info) {
            avisos.push('"' + item.nombre + '" ya no está disponible y se retiró del carrito.');
            return;
        }
        if (info.stock <= 0) {
            avisos.push('"' + info.nombre + '" está agotado y se retiró del carrito.');
            return;
        }
        if (item.cantidad > info.stock) {
            avisos.push('"' + info.nombre + '": solo hay ' + info.stock + ' disponibles, se ajustó la cantidad.');
            item.cantidad = info.stock;
        }
        item.stock  = info.stock;
        item.precio = info.precio;
        item.nombre = info.nombre;
        actualizado.push(item);
    });

    guardarCarrito(actualizado);
    actualizarContadorCarrito();
    return { carrito: actualizado, avisos };
}

function animarAlCarrito(event, imagen) {
    const carrito = document.getElementById('icono-carrito');
    if (!carrito || !event.target) return;

    const img = document.createElement('img');
    img.src = imagen;
    img.classList.add('producto-volando');
    document.body.appendChild(img);

    const inicio = event.target.getBoundingClientRect();
    const destino = carrito.getBoundingClientRect();

    img.style.left = inicio.left + 'px';
    img.style.top = inicio.top + 'px';

    setTimeout(() => {
        img.style.left = destino.left + 'px';
        img.style.top = destino.top + 'px';
        img.style.width = '20px';
        img.style.height = '20px';
        img.style.opacity = '0.2';
    }, 10);

    setTimeout(() => img.remove(), 1000);
}

function actualizarContadorCarrito() {
    const contador = document.getElementById('contador-carrito');
    if (!contador) return;

    const totalProductos = leerCarrito().reduce((suma, p) => suma + Number(p.cantidad), 0);
    contador.textContent = totalProductos;
    contador.style.display = totalProductos <= 0 ? 'none' : 'flex';
}

document.addEventListener('DOMContentLoaded', actualizarContadorCarrito);
