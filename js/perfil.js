let pedidoEnEdicion = null;

function cargarPerfil(){

    fetch("php/auth/sesion_actual.php")
    .then(res => res.json())
    .then(sesion => {

        if(!sesion.logueado){
            window.location.href = "login.html";
            return;
        }

        document.getElementById("perfilInfo").textContent =
            sesion.nombre + " · " + sesion.correo;

        cargarPedidos();
    })
    .catch(() => {
        window.location.href = "login.html";
    });
}

function cargarPedidos(){

    fetch("php/pedidos/listar_pedidos.php")
    .then(res => res.json())
    .then(data => {

        const contenedor = document.getElementById("listaPedidos");
        contenedor.innerHTML = "";

        if(!data.ok){
            contenedor.innerHTML = `<p class="text-danger">${data.mensaje}</p>`;
            return;
        }

        if(data.pedidos.length === 0){
            contenedor.innerHTML =
                `<p class="text-muted">Todavía no has realizado ningún pedido.</p>`;
            return;
        }

        const badgesEstado = {
            pendiente: "bg-warning text-dark",
            pagado: "bg-success",
            enviado: "bg-primary",
            cancelado: "bg-secondary"
        };

        data.pedidos.forEach(pedido => {

            const productosHtml = pedido.productos.map(p =>
                `<li>${p.cantidad} x ${p.producto_nombre} — $${Number(p.precio).toLocaleString("es-CO")}</li>`
            ).join("");

            const claseBadge = badgesEstado[pedido.estado] || "bg-secondary";

            const botonModificar = pedido.editable
                ? `<button class="btn btn-outline-dark btn-sm" onclick="abrirModalEditar(${pedido.id})">
                       Modificar pedido (quedan ${pedido.horas_restantes} h)
                   </button>`
                : `<span class="text-muted small">Ya no se puede modificar (pasaron más de 24 horas)</span>`;

            const card = document.createElement("div");
            card.className = "card mb-3";
            card.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Pedido #${pedido.id} — ${pedido.fecha}</h6>
                        <span class="badge ${claseBadge}">${pedido.estado}</span>
                    </div>
                    <ul class="mb-2">${productosHtml}</ul>
                    <p class="fw-bold">Total: $${Number(pedido.total).toLocaleString("es-CO")}</p>
                    ${botonModificar}
                </div>
            `;

            contenedor.appendChild(card);
        });
    })
    .catch(() => {
        document.getElementById("listaPedidos").innerHTML =
            `<p class="text-danger">No se pudieron cargar tus pedidos.</p>`;
    });
}

function abrirModalEditar(pedidoId){

    fetch("php/pedidos/listar_pedidos.php")
    .then(res => res.json())
    .then(data => {

        const pedido = data.pedidos.find(p => p.id === pedidoId);

        if(!pedido || !pedido.editable){
            Swal.fire({
                icon: "info",
                title: "Este pedido ya no se puede modificar.",
                confirmButtonColor: "#212529"
            });
            cargarPedidos();
            return;
        }

        pedidoEnEdicion = pedido.id;

        const body = document.getElementById("modalEditarPedidoBody");
        body.innerHTML = "";

        pedido.productos.forEach(item => {
            const fila = document.createElement("div");
            fila.className = "d-flex justify-content-between align-items-center mb-2";
            fila.innerHTML = `
                <span>${item.producto_nombre}</span>
                <input type="number" min="0" value="${item.cantidad}"
                    class="form-control form-control-sm w-25 cantidad-item"
                    data-detalle-id="${item.id}">
            `;
            body.appendChild(fila);
        });

        const nota = document.createElement("p");
        nota.className = "text-muted small mt-2";
        nota.textContent = "Pon la cantidad en 0 para eliminar un producto del pedido.";
        body.appendChild(nota);

        new bootstrap.Modal(document.getElementById("modalEditarPedido")).show();
    });
}

function guardarCambiosPedido(){

    const inputs = document.querySelectorAll("#modalEditarPedidoBody .cantidad-item");

    const items = Array.from(inputs).map(input => ({
        detalle_id: input.dataset.detalleId,
        cantidad: parseInt(input.value, 10) || 0
    }));

    fetch("php/pedidos/actualizar_pedido.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ pedido_id: pedidoEnEdicion, items: items })
    })
    .then(res => res.json())
    .then(data => {

        if(data.ok){
            bootstrap.Modal.getInstance(document.getElementById("modalEditarPedido")).hide();
            Swal.fire({
                icon: "success",
                title: "Pedido actualizado",
                text: data.mensaje,
                timer: 1500,
                showConfirmButton: false
            });
            cargarPedidos();
        }else{
            Swal.fire({
                icon: "error",
                title: "No se pudo actualizar",
                text: data.mensaje,
                confirmButtonColor: "#212529"
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No se pudo actualizar el pedido. Intenta de nuevo.",
            confirmButtonColor: "#212529"
        });
    });
}

document.addEventListener("DOMContentLoaded", cargarPerfil);
