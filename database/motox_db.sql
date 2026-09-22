

CREATE DATABASE IF NOT EXISTS motox_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE motox_db;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS vw_catalogo_publico;
DROP TABLE IF EXISTS resenas, producto_imagenes, pedidos_vendedor, pedido_detalle, pedidos, productos,
                     categorias, vendedor_cambios_perfil, vendedor_documentos, vendedores, usuario_roles, roles, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. USUARIOS
--    (se eliminó la columna tipo_usuario: ahora los roles viven en
--     la relación muchos a muchos usuario_roles)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    correo          VARCHAR(150) NOT NULL,
    telefono        VARCHAR(20)  NOT NULL,
    password        VARCHAR(255) NOT NULL,
    estado          ENUM('activo', 'bloqueado') NOT NULL DEFAULT 'activo',
    fecha_registro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso   DATETIME NULL,
    CONSTRAINT uq_usuarios_correo UNIQUE (correo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. ROLES  (Comprador, Vendedor, Administrador)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    clave        VARCHAR(30)  NOT NULL,          -- valor usado en el código PHP
    nombre       VARCHAR(50)  NOT NULL,          -- texto visible
    descripcion  VARCHAR(150) NULL,
    CONSTRAINT uq_roles_clave UNIQUE (clave)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. USUARIO_ROLES  (tabla intermedia → relación MUCHOS A MUCHOS)
--    Un usuario puede ser comprador y vendedor a la vez, por ejemplo.
-- ---------------------------------------------------------------------
CREATE TABLE usuario_roles (
    usuario_id        INT NOT NULL,
    rol_id            INT NOT NULL,
    fecha_asignacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, rol_id),
    KEY idx_usuario_roles_rol (rol_id),
    CONSTRAINT fk_usuario_roles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_roles_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. VENDEDORES  (tabla vinculada 1 a 1 con usuarios)
-- ---------------------------------------------------------------------
CREATE TABLE vendedores (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id            INT NOT NULL,
    razon_social          VARCHAR(150) NOT NULL,     -- nombre de la tienda / empresa
    nit                   VARCHAR(20)  NOT NULL,     -- número del RUT
    ciudad                VARCHAR(100) NOT NULL,
    direccion             VARCHAR(200) NULL,
    telefono_comercial    VARCHAR(20)  NULL,
    correo_comercial      VARCHAR(150) NULL,         -- v4: correo de contacto de la empresa
    representante_legal   VARCHAR(150) NOT NULL,
    cedula_representante  VARCHAR(20)  NOT NULL,
    estado_verificacion   ENUM('pendiente', 'aprobado', 'rechazado', 'suspendido') NOT NULL DEFAULT 'pendiente',
    observaciones         VARCHAR(255) NULL,         -- motivo de rechazo/suspensión
    fecha_registro        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_verificacion    DATETIME NULL,
    verificado_por        INT NULL,                  -- administrador que revisó
    CONSTRAINT uq_vendedores_usuario UNIQUE (usuario_id),
    CONSTRAINT uq_vendedores_nit     UNIQUE (nit),
    KEY idx_vendedores_estado (estado_verificacion),
    CONSTRAINT fk_vendedores_usuario FOREIGN KEY (usuario_id)     REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_vendedores_admin   FOREIGN KEY (verificado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. VENDEDOR_DOCUMENTOS
--    Cámara de comercio, RUT, estados financieros y cédula del
--    representante legal. Se guarda la ruta del archivo (que vive en
--    storage/documentos_vendedores, FUERA de la carpeta pública).
-- ---------------------------------------------------------------------
CREATE TABLE vendedor_documentos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    vendedor_id      INT NOT NULL,
    tipo             ENUM('camara_comercio', 'rut', 'estados_financieros', 'cedula_representante') NOT NULL,
    ruta_archivo     VARCHAR(255) NOT NULL,
    nombre_original  VARCHAR(255) NOT NULL,
    mime_type        VARCHAR(100) NOT NULL,
    tamano_bytes     INT UNSIGNED NOT NULL,
    fecha_subida     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_documento_por_tipo UNIQUE (vendedor_id, tipo),
    CONSTRAINT fk_documentos_vendedor FOREIGN KEY (vendedor_id) REFERENCES vendedores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5b. VENDEDOR_CAMBIOS_PERFIL  (v4)
--     Cuando el vendedor edita ciudad, dirección, teléfono o correo, el
--     cambio NO se aplica en vivo: queda aquí como "pendiente" hasta que
--     un administrador lo aprueba (se copia a vendedores) o lo rechaza.
--     Regla de negocio (PHP): máximo UNA solicitud pendiente por vendedor.
-- ---------------------------------------------------------------------
CREATE TABLE vendedor_cambios_perfil (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    vendedor_id         INT NOT NULL,
    ciudad              VARCHAR(100) NOT NULL,
    direccion           VARCHAR(200) NULL,
    telefono_comercial  VARCHAR(20)  NULL,
    correo_comercial    VARCHAR(150) NULL,
    estado              ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
    observaciones       VARCHAR(255) NULL,
    fecha_solicitud     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_revision      DATETIME NULL,
    revisado_por        INT NULL,
    KEY idx_cambios_estado (estado, fecha_solicitud),
    KEY idx_cambios_vendedor (vendedor_id, estado),
    CONSTRAINT fk_cambios_vendedor FOREIGN KEY (vendedor_id)  REFERENCES vendedores(id) ON DELETE CASCADE,
    CONSTRAINT fk_cambios_admin    FOREIGN KEY (revisado_por) REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. CATEGORÍAS
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(50)  NOT NULL,          -- usado en la URL: catalogo.php?categoria=llantas
    nombre       VARCHAR(100) NOT NULL,
    titulo       VARCHAR(150) NOT NULL,          -- encabezado de la página de catálogo
    descripcion  VARCHAR(255) NULL,
    imagen       VARCHAR(255) NULL,
    orden        TINYINT NOT NULL DEFAULT 0,
    CONSTRAINT uq_categorias_slug   UNIQUE (slug),
    CONSTRAINT uq_categorias_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PRODUCTOS  (+ estado Activo/Inactivo y dueño vendedor)
-- ---------------------------------------------------------------------
CREATE TABLE productos (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id         INT NOT NULL,
    vendedor_id          INT NOT NULL,
    nombre               VARCHAR(150)  NOT NULL,
    descripcion          VARCHAR(255)  NULL,
    caracteristicas      TEXT          NULL,     -- separadas por "|"
    precio               DECIMAL(10,2) NOT NULL,
    imagen               VARCHAR(255)  NULL,     -- imagen principal (primera de la galería)
    stock                INT NOT NULL DEFAULT 0,
    estado               ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    fecha_creacion       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion  DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_productos_catalogo (categoria_id, estado),
    KEY idx_productos_vendedor (vendedor_id, estado),
    CONSTRAINT chk_productos_precio CHECK (precio >= 0),
    CONSTRAINT chk_productos_stock  CHECK (stock >= 0),
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    -- RESTRICT: un vendedor con productos no se borra; se suspende.
    CONSTRAINT fk_productos_vendedor  FOREIGN KEY (vendedor_id)  REFERENCES vendedores(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. GALERÍA DE IMÁGENES (hasta 5 por producto, controlado en PHP)
-- ---------------------------------------------------------------------
CREATE TABLE producto_imagenes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    producto_id  INT NOT NULL,
    url_imagen   VARCHAR(255) NOT NULL,
    orden        INT NOT NULL DEFAULT 1,
    KEY idx_imagenes_producto (producto_id, orden),
    CONSTRAINT fk_imagenes_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. PEDIDOS
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NULL,
    nombre_cliente  VARCHAR(150) NOT NULL,
    correo_cliente  VARCHAR(150) NOT NULL,
    total           DECIMAL(10,2) NOT NULL,
    -- v4: estado GENERAL, calculado a partir de los estados de cada vendedor (pedidos_vendedor)
    estado          ENUM('pendiente', 'aprobado', 'rechazado', 'completado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pedidos_usuario (usuario_id, fecha),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. DETALLE DEL PEDIDO
--     producto_id enlaza con el producto REAL (aunque esté inactivo),
--     y se guarda una "foto" del nombre/precio/imagen del momento de la compra.
-- ---------------------------------------------------------------------
CREATE TABLE pedido_detalle (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id        INT NOT NULL,
    producto_id      INT NULL,
    vendedor_id      INT NULL,          -- v4: vendedor dueño del producto al momento de la compra
    producto_nombre  VARCHAR(150)  NOT NULL,
    precio           DECIMAL(10,2) NOT NULL,
    cantidad         INT NOT NULL,
    imagen           VARCHAR(255)  NULL,
    KEY idx_detalle_producto (producto_id),
    KEY idx_detalle_vendedor (pedido_id, vendedor_id),
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT fk_detalle_pedido   FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
    CONSTRAINT fk_detalle_vendedor FOREIGN KEY (vendedor_id) REFERENCES vendedores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10b. PEDIDOS_VENDEDOR  (v4)
--      Un pedido puede tener productos de VARIOS vendedores. Cada vendedor
--      gestiona "su parte" del pedido con su propio estado:
--        pendiente → aprobado → completado      (venta realizada)
--        pendiente → rechazado                  (se devuelve el stock)
--      "Pedidos pendientes" del vendedor = pendiente / aprobado / rechazado
--      "Historial de ventas"             = SOLO completado
-- ---------------------------------------------------------------------
CREATE TABLE pedidos_vendedor (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id            INT NOT NULL,
    vendedor_id          INT NOT NULL,
    subtotal             DECIMAL(10,2) NOT NULL DEFAULT 0,
    estado               ENUM('pendiente', 'aprobado', 'rechazado', 'completado') NOT NULL DEFAULT 'pendiente',
    fecha_actualizacion  DATETIME NULL,
    CONSTRAINT uq_pedido_vendedor UNIQUE (pedido_id, vendedor_id),
    KEY idx_pedidos_vendedor_estado (vendedor_id, estado),
    CONSTRAINT fk_pv_pedido   FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)    ON DELETE CASCADE,
    CONSTRAINT fk_pv_vendedor FOREIGN KEY (vendedor_id) REFERENCES vendedores(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. RESEÑAS
-- ---------------------------------------------------------------------
CREATE TABLE resenas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    producto_id     INT NOT NULL,
    usuario_id      INT NOT NULL,
    nombre_usuario  VARCHAR(150) NOT NULL,
    calificacion    TINYINT NOT NULL,
    comentario      TEXT,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_resena_usuario_producto UNIQUE (producto_id, usuario_id),
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5),
    CONSTRAINT fk_resenas_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    CONSTRAINT fk_resenas_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. VISTA DEL CATÁLOGO PÚBLICO
--     ÚNICA fuente de productos para la tienda. Aquí se garantiza, a nivel
--     de base de datos, que los productos INACTIVOS (o de vendedores no
--     aprobados) nunca aparezcan en el catálogo.
--     El historial de compras y el panel de administración consultan la
--     tabla productos directamente, por eso sí ven los inactivos.
-- ---------------------------------------------------------------------
CREATE VIEW vw_catalogo_publico AS
SELECT  p.id,
        p.categoria_id,
        c.slug          AS categoria_slug,
        p.vendedor_id,
        p.nombre,
        p.descripcion,
        p.caracteristicas,
        p.precio,
        p.imagen,
        p.stock,
        v.razon_social  AS vendedor_nombre,
        v.ciudad        AS vendedor_ubicacion,
        p.fecha_creacion
FROM productos p
INNER JOIN categorias c ON c.id = p.categoria_id
INNER JOIN vendedores v ON v.id = p.vendedor_id
WHERE p.estado = 'activo'
  AND v.estado_verificacion = 'aprobado';

-- @@SEED@@
-- =====================================================================
--  DATOS INICIALES
-- =====================================================================

-- Roles
INSERT INTO roles (clave, nombre, descripcion) VALUES
    ('comprador',     'Comprador',     'Compra productos en la tienda'),
    ('vendedor',      'Vendedor',      'Publica y administra sus productos'),
    ('administrador', 'Administrador', 'Gestiona vendedores, productos y pedidos');

-- Usuarios de prueba  (⚠️ cambia estas contraseñas en producción)
--   admin@motosx.com      / Admin123*
--   vendedor@motosx.com   / Vendedor123*
--   comprador@motosx.com  / Comprador123*
INSERT INTO usuarios (nombre, correo, telefono, password) VALUES
    ('Administrador MotosX', 'admin@motosx.com',     '3000000001', '$2y$12$dXSITBw1OxCPAL4YRiM5L.wrfesgRavKognDZpIVXI9voGcTjapyi'),
    ('MotosX Store',         'vendedor@motosx.com',  '3000000002', '$2y$12$.XF7V3kvKJAPltQNXhNqGOYRTz0o.hQxNxp5twjoW1/uGBMH.hrZe'),
    ('Comprador Demo',       'comprador@motosx.com', '3000000003', '$2y$12$r1Ma5BSqZgTG4KihShbvQ.7bBSy//rB4YhbeOwdKeLHzhPpZIaxpG');

-- Asignación de roles (tabla muchos a muchos). v4: los perfiles son exclusivos,
-- un administrador o vendedor NO tiene además el rol comprador.
INSERT INTO usuario_roles (usuario_id, rol_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.clave = 'administrador' WHERE u.correo = 'admin@motosx.com';
INSERT INTO usuario_roles (usuario_id, rol_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.clave = 'vendedor'  WHERE u.correo = 'vendedor@motosx.com';
INSERT INTO usuario_roles (usuario_id, rol_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.clave = 'comprador' WHERE u.correo = 'comprador@motosx.com';

-- Vendedor de prueba (ya aprobado)
INSERT INTO vendedores (usuario_id, razon_social, nit, ciudad, direccion, telefono_comercial, correo_comercial,
                        representante_legal, cedula_representante, estado_verificacion, fecha_verificacion)
SELECT id, 'MotosX Store', '900123456-7', 'Bogotá, Colombia', 'Calle 80 # 20-15', '6015550000', 'ventas@motosxstore.com',
       'Representante Demo', '1000000000', 'aprobado', CURRENT_TIMESTAMP
FROM usuarios WHERE correo = 'vendedor@motosx.com';

-- Categorías
INSERT INTO categorias (slug, nombre, titulo, descripcion, imagen, orden) VALUES
    ('llantas',    'Llantas',    'Llantas para todos los estilos',          'Tracción y seguridad para ciudad, carretera y off road.', 'img/secciones/llantas/seccion_para_llantas.jpg',          1),
    ('repuestos',  'Repuestos',  'Repuestos para todo tipo de motocicletas', 'Piezas 100% compatibles para mantener tu moto a punto.',  'img/secciones/repuestos/repuestos_motos.png',             2),
    ('accesorios', 'Accesorios', 'Accesorios para todos los estilos',        'Seguridad, comodidad y estilo para cada viaje.',          'img/secciones/accesorios/accesorios_de_motos.jpeg',      3),
    ('aceites',    'Aceites',    'Aceites para todas las referencias',       'Lubricantes para motores de 2 y 4 tiempos.',              'img/secciones/aceites/Aceites_para_motos.jpg',           4),
    ('frenos',     'Frenos',     'Frenos seguros',                           'Pastillas y componentes para un frenado confiable.',      'img/secciones/frenos/pastillas-de-frenos-de-moto.png',   5);

-- Productos (los mismos que estaban "quemados" en los HTML)
INSERT INTO productos (categoria_id, vendedor_id, nombre, descripcion, caracteristicas, precio, imagen, stock) VALUES
-- Llantas
((SELECT id FROM categorias WHERE slug='llantas'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Llantas off Road', 'Alta tracción para terrenos difíciles.', 'Compuesto de goma reforzada|Diseño de taco profundo|Resistente a cortes y pinchazos', 620000, 'img/productos/llantas/llantas_off_road.jpg', 50),
((SELECT id FROM categorias WHERE slug='llantas'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Llanta Michelin Pilot Road', 'Excelente agarre y estabilidad en carretera.', 'Tecnología 2CT para mayor duración|Excelente agarre en mojado|Ideal para touring y carretera', 850000, 'img/productos/llantas/Llanta_Michelin_Pilot_Road.jpg', 50),
((SELECT id FROM categorias WHERE slug='llantas'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Pirelli Diablo Rosso', 'Llanta deportiva con gran rendimiento.', 'Compuesto de alto rendimiento|Óptima adherencia en curva|Diseño deportivo', 670000, 'img/productos/llantas/Pirelli_Diablo_Rosso.jpg', 50),
((SELECT id FROM categorias WHERE slug='llantas'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Llanta Pirelli MT60', 'Ideal para ciudad y caminos destapados.', 'Uso mixto ciudad/destapado|Banda de rodadura versátil|Buena estabilidad a alta velocidad', 760000, 'img/productos/llantas/Pirelli_MT60.jpg', 50),
((SELECT id FROM categorias WHERE slug='llantas'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Shinko SR241', 'Confort y seguridad para viajes en carretera.', 'Larga duración|Confort en carretera|Buena relación precio-calidad', 197000, 'img/productos/llantas/Shinko SR241.jpeg', 50),
-- Aceites
((SELECT id FROM categorias WHERE slug='aceites'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Castrol Power 1', 'Aceite lubricante con tecnología sintética para motores de 4 tiempos.', 'Tecnología sintética|Protección contra el desgaste|Para motores de 4 tiempos', 45000, 'img/productos/aceites/Castrol.png', 50),
((SELECT id FROM categorias WHERE slug='aceites'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Repsol Moto Sport 10W-40', 'Aceite para motocicletas de 4 tiempos.', 'Fórmula semisintética|Buena lubricación en frío|Compatible con embrague húmedo', 35000, 'img/productos/aceites/repsol.jpeg', 50),
((SELECT id FROM categorias WHERE slug='aceites'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Ecstar R7000 10W-40', 'Lubricante semisintético para motores 4T.', 'Desarrollado por Suzuki|Óptimo rendimiento del motor|Reduce la fricción interna', 54000, 'img/productos/aceites/ecstar.jpeg', 50),
((SELECT id FROM categorias WHERE slug='aceites'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Mobil Super Ultra 20W-50', 'Aceite con protección para motores 4T.', 'Alta viscosidad|Protección en climas cálidos|Larga vida útil del motor', 35000, 'img/productos/aceites/mobil_super.jpeg', 50),
((SELECT id FROM categorias WHERE slug='aceites'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Motul 7100 20W-50', 'Lubricante 100% sintético para 4 tiempos.', '100% sintético|Alto rendimiento en competencia|Máxima protección térmica', 82000, 'img/productos/aceites/motul dorado.jpeg', 50),
-- Frenos
((SELECT id FROM categorias WHERE slug='frenos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Pastillas de freno Brembo', 'Pastillas de freno para sistemas de disco.', 'Frenado de alto rendimiento|Baja generación de polvo|Resistente al desgaste', 215000, 'img/productos/frenos/Pastilla_Brembo.webp', 50),
((SELECT id FROM categorias WHERE slug='frenos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Pastillas de freno Frasle', 'Pastillas de freno para sistema de disco.', 'Fabricación certificada|Buen desempeño en frenadas bruscas|Fácil instalación', 127000, 'img/productos/frenos/Pastilla_Frasle.png', 50),
((SELECT id FROM categorias WHERE slug='frenos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Pastillas de freno Ridex', 'Set de pastillas de freno.', 'Económicas y confiables|Compatible con varios modelos|Frenado estable', 95000, 'img/productos/frenos/Pastilla_Ridex.jpg', 50),
((SELECT id FROM categorias WHERE slug='frenos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Pastillas de freno Stark', 'Pastillas de freno para vehículos.', 'Material cerámico|Menor ruido al frenar|Buena disipación de calor', 136000, 'img/productos/frenos/Pastilla_Stark.jpg', 50),
((SELECT id FROM categorias WHERE slug='frenos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Pastillas de freno Bosch', 'Pastillas de freno de alto rendimiento.', 'Tecnología alemana|Alta durabilidad|Frenado preciso', 174000, 'img/productos/frenos/Pastillas_Bosch.jpg', 50),
-- Repuestos
((SELECT id FROM categorias WHERE slug='repuestos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Amortiguador trasero', 'Absorbe impactos y controla la suspensión.', 'Ajuste de precarga|Resistente a impactos|Mejora la estabilidad', 157000, 'img/productos/repuestos/Amortiguador trasero.jpg', 50),
((SELECT id FROM categorias WHERE slug='repuestos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Bujía', 'Genera la chispa para la combustión.', 'Encendido eficiente|Electrodo de níquel|Fácil instalación', 16500, 'img/productos/repuestos/Bujia.jpg', 50),
((SELECT id FROM categorias WHERE slug='repuestos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Cadena de transmisión', 'Transfiere el movimiento hacia la rueda.', 'Acero reforzado|Baja fricción|Larga duración', 59000, 'img/productos/repuestos/Cadena de transmision.jpg', 50),
((SELECT id FROM categorias WHERE slug='repuestos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Filtro de aire', 'Limpia el aire que entra al motor.', 'Alta capacidad de filtrado|Mejora el rendimiento del motor|Fácil mantenimiento', 37000, 'img/productos/repuestos/Filtro de aire.jpg', 50),
((SELECT id FROM categorias WHERE slug='repuestos'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Kit de arrastre', 'Transmite potencia del motor a la rueda.', 'Incluye cadena, piñón y corona|Materiales resistentes|Ideal para uso intensivo', 140000, 'img/productos/repuestos/Kit de arrastre.jpg', 50),
-- Accesorios
((SELECT id FROM categorias WHERE slug='accesorios'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Anclaje', 'Accesorio de seguridad para proteger tu motocicleta.', 'Acero de alta resistencia|Fácil de instalar|Compatible con varias cadenas', 127000, 'img/productos/accesorios/Anclaje.png', 50),
((SELECT id FROM categorias WHERE slug='accesorios'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Cadena antirrobo', 'Mayor protección contra robos.', 'Eslabones templados|Funda protectora incluida|Alta resistencia al corte', 167000, 'img/productos/accesorios/Cadena antirobo.png', 50),
((SELECT id FROM categorias WHERE slug='accesorios'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Funda', 'Protege la moto del polvo y la lluvia.', 'Material impermeable|Protección UV|Ajuste universal', 68000, 'img/productos/accesorios/Funda.png', 50),
((SELECT id FROM categorias WHERE slug='accesorios'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Puños calefactables', 'Mayor comodidad en climas fríos.', 'Varios niveles de calor|Fácil instalación|Ideal para clima frío', 116000, 'img/productos/accesorios/PUÑOS CALEFACTABLES.png', 50),
((SELECT id FROM categorias WHERE slug='accesorios'), (SELECT id FROM vendedores WHERE nit='900123456-7'), 'Retrovisores', 'Mejor visibilidad y seguridad al conducir.', 'Visión amplia|Resistentes a vibraciones|Diseño universal', 67000, 'img/productos/accesorios/Retrovisores.png', 50);

-- Imagen principal como primera foto de la galería
INSERT INTO producto_imagenes (producto_id, url_imagen, orden)
SELECT id, imagen, 1 FROM productos;

-- Fotos adicionales que ya existían en img/productos/frenos (galería 2 a 5)
INSERT INTO producto_imagenes (producto_id, url_imagen, orden)
SELECT p.id, g.url_imagen, g.orden
FROM productos p
JOIN (
    SELECT 'Pastillas de freno Brembo' AS producto, 'img/productos/frenos/Pastillas_Brembo_2.png' AS url_imagen, 2 AS orden
    UNION ALL SELECT 'Pastillas de freno Brembo', 'img/productos/frenos/Pastillas_Brembo_3.png', 3
    UNION ALL SELECT 'Pastillas de freno Brembo', 'img/productos/frenos/Pastillas_Brembo_4.png', 4
    UNION ALL SELECT 'Pastillas de freno Brembo', 'img/productos/frenos/Pastillas_Brembo_5.png', 5
    UNION ALL SELECT 'Pastillas de freno Frasle', 'img/productos/frenos/Pastilla_Frasle_2.png', 2
    UNION ALL SELECT 'Pastillas de freno Frasle', 'img/productos/frenos/Pastilla_Frasle_3.png', 3
    UNION ALL SELECT 'Pastillas de freno Frasle', 'img/productos/frenos/Pastilla_Frasle_4.png', 4
    UNION ALL SELECT 'Pastillas de freno Frasle', 'img/productos/frenos/Pastilla_Frasle_5.png', 5
    UNION ALL SELECT 'Pastillas de freno Ridex',  'img/productos/frenos/Pastilla_Ridex_2.png', 2
    UNION ALL SELECT 'Pastillas de freno Ridex',  'img/productos/frenos/Pastilla_Ridex_3.png', 3
    UNION ALL SELECT 'Pastillas de freno Ridex',  'img/productos/frenos/Pastilla_Ridex_4.png', 4
    UNION ALL SELECT 'Pastillas de freno Ridex',  'img/productos/frenos/Pastilla_Ridex_5.png', 5
    UNION ALL SELECT 'Pastillas de freno Stark',  'img/productos/frenos/Pastilla_Stark_2.png', 2
    UNION ALL SELECT 'Pastillas de freno Stark',  'img/productos/frenos/Pastilla_Stark_3.png', 3
    UNION ALL SELECT 'Pastillas de freno Stark',  'img/productos/frenos/Pastilla_Stark_4.png', 4
    UNION ALL SELECT 'Pastillas de freno Stark',  'img/productos/frenos/Pastilla_Stark_5.png', 5
    UNION ALL SELECT 'Pastillas de freno Bosch',  'img/productos/frenos/Pastillas_Bosch_2.png', 2
    UNION ALL SELECT 'Pastillas de freno Bosch',  'img/productos/frenos/Pastillas_Bosch_3.png', 3
    UNION ALL SELECT 'Pastillas de freno Bosch',  'img/productos/frenos/Pastillas_Bosch_4.png', 4
    UNION ALL SELECT 'Pastillas de freno Bosch',  'img/productos/frenos/Pastillas_Bosch_5.png', 5
) g ON g.producto = p.nombre;

-- ---------------------------------------------------------------------
-- v4: stock bajo / agotado para probar las validaciones de compra
-- ---------------------------------------------------------------------
UPDATE productos SET stock = 3 WHERE nombre = 'Shinko SR241';
UPDATE productos SET stock = 0 WHERE nombre = 'Funda';

-- ---------------------------------------------------------------------
-- v4: pedidos de ejemplo del comprador demo (uno por cada estado)
--   #1 pendiente · #2 aprobado · #3 completado · #4 rechazado
-- ---------------------------------------------------------------------
INSERT INTO pedidos (id, usuario_id, nombre_cliente, correo_cliente, total, estado, fecha)
SELECT 1, id, nombre, correo, 0, 'pendiente',  CURRENT_TIMESTAMP FROM usuarios WHERE correo = 'comprador@motosx.com';
INSERT INTO pedidos (id, usuario_id, nombre_cliente, correo_cliente, total, estado, fecha)
SELECT 2, id, nombre, correo, 0, 'aprobado',   CURRENT_TIMESTAMP - INTERVAL 1 DAY FROM usuarios WHERE correo = 'comprador@motosx.com';
INSERT INTO pedidos (id, usuario_id, nombre_cliente, correo_cliente, total, estado, fecha)
SELECT 3, id, nombre, correo, 0, 'completado', CURRENT_TIMESTAMP - INTERVAL 5 DAY FROM usuarios WHERE correo = 'comprador@motosx.com';
INSERT INTO pedidos (id, usuario_id, nombre_cliente, correo_cliente, total, estado, fecha)
SELECT 4, id, nombre, correo, 0, 'rechazado',  CURRENT_TIMESTAMP - INTERVAL 2 DAY FROM usuarios WHERE correo = 'comprador@motosx.com';

INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
SELECT 1, id, vendedor_id, nombre, precio, 1, imagen FROM productos WHERE nombre = 'Llantas off Road';
INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
SELECT 1, id, vendedor_id, nombre, precio, 2, imagen FROM productos WHERE nombre = 'Castrol Power 1';
INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
SELECT 2, id, vendedor_id, nombre, precio, 4, imagen FROM productos WHERE nombre = 'Bujía';
INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
SELECT 3, id, vendedor_id, nombre, precio, 1, imagen FROM productos WHERE nombre = 'Pirelli Diablo Rosso';
INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
SELECT 3, id, vendedor_id, nombre, precio, 2, imagen FROM productos WHERE nombre = 'Retrovisores';
INSERT INTO pedido_detalle (pedido_id, producto_id, vendedor_id, producto_nombre, precio, cantidad, imagen)
SELECT 4, id, vendedor_id, nombre, precio, 1, imagen FROM productos WHERE nombre = 'Kit de arrastre';

INSERT INTO pedidos_vendedor (pedido_id, vendedor_id, subtotal, estado)
SELECT d.pedido_id, d.vendedor_id, SUM(d.precio * d.cantidad), p.estado
FROM pedido_detalle d
INNER JOIN pedidos p ON p.id = d.pedido_id
GROUP BY d.pedido_id, d.vendedor_id, p.estado;

UPDATE pedidos SET total = (SELECT COALESCE(SUM(d.precio * d.cantidad), 0) FROM pedido_detalle d WHERE d.pedido_id = pedidos.id);
