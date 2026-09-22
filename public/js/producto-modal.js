/**
 * Modal "Ver producto": carga el detalle desde api/producto_detalle.php
 * y permite dejar una reseña api/calificar.php.
 */
let productoActual = null;
let calificacionSeleccionada = 0;

function alertaError(titulo, texto) {
    Swal.fire({ icon: 'error', title: titulo, text: texto, confirmButtonColor: '#212529' });
}

function verProducto(id) {
    fetch(MOTOSX.baseUrl + '/api/producto_detalle.php?id=' + encodeURIComponent(id))
    .then(res => res.json())
    .then(data => {
        if (!data.ok) {
            alertaError('No se pudo cargar el producto', data.mensaje);
            return;
        }

        productoActual = data.producto;
        calificacionSeleccionada = 0;

        document.getElementById('modalProductoNombre').textContent = productoActual.nombre;
        document.getElementById('modalProductoDescripcion').textContent = productoActual.descripcion || '';
        document.getElementById('modalProductoPrecio').textContent = formatoPrecio(productoActual.precio);

        //  stock disponible
        const etiquetaStock = document.getElementById('modalProductoStock');
        etiquetaStock.textContent = productoActual.stock > 0 ? 'Stock disponible: ' + productoActual.stock : 'Agotado';
        etiquetaStock.className = 'stock-disponible mb-3' + (productoActual.stock <= 0 ? ' stock-agotado' : (productoActual.stock <= 5 ? ' stock-bajo' : ''));
        document.getElementById('modalProductoPagina').href = MOTOSX.baseUrl + '/producto.php?id=' + productoActual.id;
        // para que los vendedores y administradores no dejen reseñas del los productos
        document.getElementById('modalProductoZonaResena').style.display = MOTOSX.puedeComprar ? '' : 'none';
        document.getElementById('modalProductoVendedor').textContent = productoActual.vendedor_nombre || 'MotosX';
        document.getElementById('modalProductoUbicacion').textContent = productoActual.vendedor_ubicacion || 'Colombia';

        // Características del producto
        const listaCaract = document.getElementById('modalProductoCaracteristicas');
        listaCaract.innerHTML = '';
        (productoActual.caracteristicas || []).forEach(c => {
            const li = document.createElement('li');
            li.textContent = c;
            listaCaract.appendChild(li);
        });

        // Galería solo dejamos 5 imagenes 
        const carrusel = document.getElementById('modalProductoImagenes');
        carrusel.innerHTML = '';
        const imagenes = (productoActual.imagenes || []).slice(0, 5);

        imagenes.forEach((url, i) => {
            const div = document.createElement('div');
            div.className = 'carousel-item' + (i === 0 ? ' active' : '');
            const img = document.createElement('img');
            img.src = url;
            img.alt = productoActual.nombre;
            img.className = 'd-block w-100';
            img.style.maxHeight = '350px';
            img.style.objectFit = 'contain';
            div.appendChild(img);
            carrusel.appendChild(div);
        });
        document.getElementById('modalProductoControles').style.display = imagenes.length > 1 ? 'block' : 'none';

        // Calificación promedio
        document.getElementById('modalProductoPromedio').textContent =
            productoActual.total_resenas > 0
                ? `⭐ ${productoActual.promedio_calificacion} (${productoActual.total_resenas} reseña${productoActual.total_resenas === 1 ? '' : 's'})`
                : 'Sin calificaciones todavía';

        // Reseñas
        const contenedorResenas = document.getElementById('modalProductoResenas');
        contenedorResenas.innerHTML = '';

        if (productoActual.resenas.length === 0) {
            contenedorResenas.innerHTML = "<p class='text-muted small'>Este producto todavía no tiene reseñas.</p>";
        } else {
            productoActual.resenas.forEach(r => {
                const div = document.createElement('div');
                div.className = 'border-bottom pb-2 mb-2';

                const autor = document.createElement('strong');
                autor.textContent = r.nombre_usuario;

                const estrellas = document.createTextNode(' ' + '⭐'.repeat(r.calificacion) + '☆'.repeat(5 - r.calificacion));

                const comentario = document.createElement('small');
                comentario.className = 'text-muted d-block';
                comentario.textContent = r.comentario || '';

                div.append(autor, estrellas, comentario);
                contenedorResenas.appendChild(div);
            });
        }

        dibujarEstrellasSeleccion();
        document.getElementById('modalProductoComentario').value = '';

        const btnAgregar = document.getElementById('modalProductoAgregar');
        btnAgregar.style.display = MOTOSX.puedeComprar ? '' : 'none';
        btnAgregar.disabled = productoActual.stock <= 0;
        btnAgregar.textContent = productoActual.stock <= 0 ? 'Agotado' : 'Añadir al carrito';
        btnAgregar.onclick = function (event) {
            agregarCarrito(productoActual.id, productoActual.nombre, Number(productoActual.precio), productoActual.imagen, event, productoActual.stock, 1);
        };

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProducto')).show();
    })
    .catch(() => alertaError('Error de conexión', 'No se pudo cargar la información del producto.'));
}

function dibujarEstrellasSeleccion() {
    const contenedor = document.getElementById('modalProductoEstrellas');
    contenedor.innerHTML = '';

    for (let i = 1; i <= 5; i++) {
        const estrella = document.createElement('span');
        estrella.textContent = i <= calificacionSeleccionada ? '⭐' : '☆';
        estrella.style.cursor = 'pointer';
        estrella.onclick = function () {
            calificacionSeleccionada = i;
            dibujarEstrellasSeleccion();
        };
        contenedor.appendChild(estrella);
    }
}

function enviarResena() {
    if (!productoActual) return;

    if (!MOTOSX.logueado) {
        Swal.fire({
            icon: 'info',
            title: 'Inicia sesión',
            text: 'Debes iniciar sesión para dejar una reseña.',
            showCancelButton: true,
            confirmButtonText: 'Ir al login',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#212529'
        }).then(r => { if (r.isConfirmed) window.location.href = MOTOSX.baseUrl + '/login.php'; });
        return;
    }

    if (calificacionSeleccionada < 1) {
        Swal.fire({ icon: 'warning', title: 'Selecciona una calificación', text: 'Elige de 1 a 5 estrellas antes de enviar tu reseña.', confirmButtonColor: '#212529' });
        return;
    }

    fetch(MOTOSX.baseUrl + '/api/calificar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': MOTOSX.csrf },
        body: JSON.stringify({
            producto_id: productoActual.id,
            calificacion: calificacionSeleccionada,
            comentario: document.getElementById('modalProductoComentario').value.trim()
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            Swal.fire({ icon: 'success', title: '¡Gracias!', text: data.mensaje, timer: 1500, showConfirmButton: false });
            verProducto(productoActual.id);
        } else {
            alertaError('No se pudo enviar tu reseña', data.mensaje);
        }
    })
    .catch(() => alertaError('Error de conexión', 'No se pudo enviar la reseña. Intenta de nuevo.'));
}
