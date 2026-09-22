<?php /** Modal "Ver producto" (lo llena js/producto-modal.js con api/producto_detalle.php). */ ?>
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalProductoNombre">Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">

        <div id="modalProductoCarrusel" class="carousel slide mb-3">
          <div class="carousel-inner" id="modalProductoImagenes"></div>
          <div id="modalProductoControles">
            <button class="carousel-control-prev" type="button" data-bs-target="#modalProductoCarrusel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" style="filter:invert(1) grayscale(1);"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#modalProductoCarrusel" data-bs-slide="next">
              <span class="carousel-control-next-icon" style="filter:invert(1) grayscale(1);"></span>
            </button>
          </div>
        </div>

        <p id="modalProductoDescripcion"></p>
        <h4 id="modalProductoPrecio" class="mb-1"></h4>
        <p id="modalProductoStock" class="stock-disponible mb-3"></p>

        <p class="mb-1"><strong>Vendedor:</strong> <span id="modalProductoVendedor"></span></p>
        <p><strong>Ubicación del vendedor:</strong> <span id="modalProductoUbicacion"></span></p>

        <h6>Características</h6>
        <ul id="modalProductoCaracteristicas"></ul>

        <hr>

        <h6>Calificación <span id="modalProductoPromedio"></span></h6>
        <div id="modalProductoResenas" class="mb-3"></div>

        <div id="modalProductoZonaResena">
          <hr>
          <h6>Deja tu reseña</h6>
          <div id="modalProductoEstrellas" class="mb-2 fs-3"></div>
          <textarea id="modalProductoComentario" class="form-control mb-2" rows="2" maxlength="1000" placeholder="Escribe tu opinión (opcional)"></textarea>
          <button class="btn btn-outline-dark btn-sm" type="button" onclick="enviarResena()">Enviar reseña</button>
        </div>

      </div>
      <div class="modal-footer">
        <a href="#" class="btn btn-outline-dark me-auto" id="modalProductoPagina">Ver ficha completa</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-dark" id="modalProductoAgregar">Añadir al carrito</button>
      </div>
    </div>
  </div>
</div>
