let productoActual = null;
let calificacionSeleccionada = 0;

function verProducto(id){

    fetch("php/productos/detalle.php?id=" + id)
    .then(res => res.json())
    .then(data => {

        if(!data.ok){
            Swal.fire({
                icon: "error",
                title: "No se pudo cargar el producto",
                text: data.mensaje,
                confirmButtonColor: "#212529"
            });
            return;
        }

        productoActual = data.producto;
        calificacionSeleccionada = 0;

        document.getElementById("modalProductoNombre").textContent = productoActual.nombre;
        document.getElementById("modalProductoDescripcion").textContent = productoActual.descripcion;
        document.getElementById("modalProductoPrecio").textContent =
            "$" + Number(productoActual.precio).toLocaleString("es-CO");
        document.getElementById("modalProductoVendedor").textContent =
            productoActual.vendedor_nombre || "MotosX";
        document.getElementById("modalProductoUbicacion").textContent =
            productoActual.vendedor_ubicacion || "Colombia";

        // Características
        const listaCaract = document.getElementById("modalProductoCaracteristicas");
        listaCaract.innerHTML = "";

        (productoActual.caracteristicas || []).forEach(c => {
            const li = document.createElement("li");
            li.textContent = c;
            listaCaract.appendChild(li);
        });

        // Galería (hasta 5 imágenes)
        const carrusel = document.getElementById("modalProductoImagenes");
        carrusel.innerHTML = "";

        const imagenes = (productoActual.imagenes || []).slice(0, 5);

        imagenes.forEach((img, i) => {
            const div = document.createElement("div");
            div.className = "carousel-item" + (i === 0 ? " active" : "");
            div.innerHTML =
                `<img src="${img}" class="d-block w-100" style="max-height:350px;object-fit:contain;">`;
            carrusel.appendChild(div);
        });

        const controles = document.getElementById("modalProductoControles");
        controles.style.display = imagenes.length > 1 ? "block" : "none";

        // Calificación promedio
        document.getElementById("modalProductoPromedio").textContent =
            productoActual.total_resenas > 0
                ? `⭐ ${productoActual.promedio_calificacion} (${productoActual.total_resenas} reseña${productoActual.total_resenas === 1 ? "" : "s"})`
                : "Sin calificaciones todavía";

        // Lista de reseñas
        const contenedorResenas = document.getElementById("modalProductoResenas");
        contenedorResenas.innerHTML = "";

        if(productoActual.resenas.length === 0){
            contenedorResenas.innerHTML =
                "<p class='text-muted small'>Este producto todavía no tiene reseñas.</p>";
        }else{
            productoActual.resenas.forEach(r => {
                const estrellas = "⭐".repeat(r.calificacion) + "☆".repeat(5 - r.calificacion);
                const div = document.createElement("div");
                div.className = "border-bottom pb-2 mb-2";
                div.innerHTML =
                    `<strong>${r.nombre_usuario}</strong> ${estrellas}<br>
                     <small class="text-muted">${r.comentario ? r.comentario : ""}</small>`;
                contenedorResenas.appendChild(div);
            });
        }

        dibujarEstrellasSeleccion();
        document.getElementById("modalProductoComentario").value = "";

        // Botón añadir al carrito dentro del modal
        const btnAgregar = document.getElementById("modalProductoAgregar");
        btnAgregar.onclick = function(event){
            agregarCarrito(
                productoActual.nombre,
                Number(productoActual.precio),
                productoActual.imagen,
                event
            );
        };

        const modal = new bootstrap.Modal(document.getElementById("modalProducto"));
        modal.show();
    })
    .catch(() => {
        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No se pudo cargar la información del producto.",
            confirmButtonColor: "#212529"
        });
    });
}

function dibujarEstrellasSeleccion(){

    const contenedor = document.getElementById("modalProductoEstrellas");
    contenedor.innerHTML = "";

    for(let i = 1; i <= 5; i++){

        const estrella = document.createElement("span");
        estrella.textContent = i <= calificacionSeleccionada ? "⭐" : "☆";
        estrella.style.cursor = "pointer";

        estrella.onclick = function(){
            calificacionSeleccionada = i;
            dibujarEstrellasSeleccion();
        };

        contenedor.appendChild(estrella);
    }
}

function enviarResena(){

    if(!productoActual) return;

    if(calificacionSeleccionada < 1){
        Swal.fire({
            icon: "warning",
            title: "Selecciona una calificación",
            text: "Elige de 1 a 5 estrellas antes de enviar tu reseña.",
            confirmButtonColor: "#212529"
        });
        return;
    }

    const comentario = document.getElementById("modalProductoComentario").value.trim();

    fetch("php/productos/calificar.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            producto_id: productoActual.id,
            calificacion: calificacionSeleccionada,
            comentario: comentario
        })
    })
    .then(res => res.json())
    .then(data => {

        if(data.ok){
            Swal.fire({
                icon: "success",
                title: "¡Gracias!",
                text: data.mensaje,
                timer: 1500,
                showConfirmButton: false
            });
            verProducto(productoActual.id);
        }else{
            Swal.fire({
                icon: "error",
                title: "No se pudo enviar tu reseña",
                text: data.mensaje,
                confirmButtonColor: "#212529"
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No se pudo enviar la reseña. Intenta de nuevo.",
            confirmButtonColor: "#212529"
        });
    });
}
