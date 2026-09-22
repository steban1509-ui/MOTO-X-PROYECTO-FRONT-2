function inicializarAuthNav(){

    const area = document.getElementById("auth-area");

    if(!area) return;

    fetch("php/auth/sesion_actual.php")
    .then(res => res.json())
    .then(data => {

        if(data.logueado){

            const primerNombre = data.nombre.split(" ")[0];

            area.innerHTML = `
                <div class="dropdown d-inline-block">
                    <a href="javascript:void(0)"
                       class="text-dark dropdown-toggle"
                       data-bs-toggle="dropdown"
                       aria-expanded="false"
                       style="text-decoration:none;">
                        👤 ${primerNombre}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.html">Ver perfil</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" id="btnCerrarSesion">Cerrar sesión</a></li>
                    </ul>
                </div>
            `;

            document.getElementById("btnCerrarSesion")
                .addEventListener("click", cerrarSesion);
        }
        // Si no hay sesión, se deja el menú de invitado (login/registro)
        // tal como ya está escrito en el HTML de la página.
    })
    .catch(() => {});
}

function cerrarSesion(){

    fetch("php/auth/logout.php")
    .then(() => {
        window.location.href = "index.html";
    });
}

document.addEventListener("DOMContentLoaded", inicializarAuthNav);
