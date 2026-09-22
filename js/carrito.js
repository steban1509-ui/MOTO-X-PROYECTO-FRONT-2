function agregarCarrito(nombre, precio, imagen, event){

    let carrito = JSON.parse(
        localStorage.getItem("carrito")
    ) || [];

    let productoExistente = carrito.find(
        producto => producto.nombre === nombre
    );

    if(productoExistente){

        productoExistente.cantidad++;

    }else{

        carrito.push({
            nombre: nombre,
            precio: precio,
            imagen: imagen,
            cantidad: 1
        });

    }

    localStorage.setItem(
        "carrito",
        JSON.stringify(carrito)
    );

    actualizarContadorCarrito();

    animarAlCarrito(event, imagen);

    if(typeof Swal !== "undefined"){

        Swal.fire({
            toast: true,
            position: "top-end",
            icon: "success",
            title: nombre + " añadido al carrito",
            showConfirmButton: false,
            timer: 1600,
            timerProgressBar: true
        });
    }
}

function animarAlCarrito(event, imagen){

    const carrito = document.getElementById(
        "icono-carrito"
    );

    if(!carrito) return;

    const img = document.createElement("img");

    img.src = imagen;

    img.classList.add(
        "producto-volando"
    );

    document.body.appendChild(img);

    const inicio =
        event.target.getBoundingClientRect();

    const destino =
        carrito.getBoundingClientRect();

    img.style.left = inicio.left + "px";
    img.style.top = inicio.top + "px";

    setTimeout(() => {

        img.style.left =
            destino.left + "px";

        img.style.top =
            destino.top + "px";

        img.style.width = "20px";

        img.style.height = "20px";

        img.style.opacity = "0.2";

    }, 10);

    setTimeout(() => {

        img.remove();

    }, 1000);
}

function actualizarContadorCarrito(){

    let carrito = JSON.parse(
        localStorage.getItem("carrito")
    ) || [];

    let totalProductos = 0;

    carrito.forEach(producto => {
        totalProductos += producto.cantidad;
    });

    const contador = document.getElementById(
        "contador-carrito"
    );

    if(contador){

        contador.textContent =
            totalProductos;

        if(totalProductos <= 0){
            contador.style.display = "none";
        }else{
            contador.style.display = "flex";
        }
    }
}

document.addEventListener(
    "DOMContentLoaded",
    actualizarContadorCarrito
);