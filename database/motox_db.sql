-- =========================================================
-- Base de datos MotosX (MySQL)
-- Importar en phpMyAdmin, MySQL Workbench o con:
--   mysql -u root -p < motox_db.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS motox_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE motox_db;

-- ---------------------------------------------------------
-- Usuarios (clientes / vendedores)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(150)  NOT NULL,
    correo         VARCHAR(150)  NOT NULL UNIQUE,
    telefono       VARCHAR(20)   NOT NULL,
    password       VARCHAR(255)  NOT NULL,
    tipo_usuario   ENUM('motociclista', 'vendedor') NOT NULL DEFAULT 'motociclista',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Categorías de productos
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Catálogo de productos
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id  INT NOT NULL,
    nombre        VARCHAR(150) NOT NULL,
    descripcion   VARCHAR(255),
    precio        DECIMAL(10,2) NOT NULL,
    imagen        VARCHAR(255),
    stock         INT NOT NULL DEFAULT 50,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Pedidos (cabecera de cada compra)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT NULL,
    nombre_cliente VARCHAR(150) NOT NULL,
    correo_cliente VARCHAR(150) NOT NULL,
    total          DECIMAL(10,2) NOT NULL,
    estado         ENUM('pendiente', 'pagado', 'enviado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Detalle de cada pedido (productos comprados)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedido_detalle (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id        INT NOT NULL,
    producto_nombre  VARCHAR(150) NOT NULL,
    precio           DECIMAL(10,2) NOT NULL,
    cantidad         INT NOT NULL,
    imagen           VARCHAR(255),
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- Datos iniciales (seed) — categorías
-- =========================================================
INSERT INTO categorias (nombre) VALUES
    ('llantas'),
    ('aceites'),
    ('frenos'),
    ('repuestos'),
    ('accesorios');

-- =========================================================
-- Datos iniciales (seed) — productos, tomados del catálogo
-- actual del sitio (llantas.html, aceites.html, frenos.html,
-- repuestos.html, accesorios.html)
-- =========================================================

-- Llantas
INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen) VALUES
((SELECT id FROM categorias WHERE nombre = 'llantas'), 'Llantas off Road', 'Alta tracción para terrenos difíciles.', 620000, 'img/productos/llantas/llantas_off_road.jpg'),
((SELECT id FROM categorias WHERE nombre = 'llantas'), 'Llanta Michelin Pilot Road', 'Excelente agarre y estabilidad en carretera.', 850000, 'img/productos/llantas/Llanta_Michelin_Pilot_Road.jpg'),
((SELECT id FROM categorias WHERE nombre = 'llantas'), 'Pirelli Diablo Rosso', 'Llanta deportiva con gran rendimiento.', 670000, 'img/productos/llantas/Pirelli_Diablo_Rosso.jpg'),
((SELECT id FROM categorias WHERE nombre = 'llantas'), 'Llanta Pirelli MT60', 'Ideal para ciudad y caminos destapados.', 760000, 'img/productos/llantas/Pirelli_MT60.jpg'),
((SELECT id FROM categorias WHERE nombre = 'llantas'), 'Shinko SR241', 'Confort y seguridad para viajes en carretera.', 197000, 'img/productos/llantas/Shinko SR241.jpeg');

-- Aceites
INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen) VALUES
((SELECT id FROM categorias WHERE nombre = 'aceites'), 'Castrol Power 1', 'Aceite lubricante con tecnología sintética para motores de 4 tiempos.', 45000, 'img/productos/aceites/Castrol.png'),
((SELECT id FROM categorias WHERE nombre = 'aceites'), 'Repsol Moto Sport 10W-40', 'Aceite para motocicletas de 4 tiempos.', 35000, 'img/productos/aceites/repsol.jpeg'),
((SELECT id FROM categorias WHERE nombre = 'aceites'), 'Ecstar R7000 10W-40', 'Lubricante semisintético para motores 4T.', 54000, 'img/productos/aceites/ecstar.jpeg'),
((SELECT id FROM categorias WHERE nombre = 'aceites'), 'Mobil Super Ultra 20W-50', 'Aceite con protección para motores 4T.', 35000, 'img/productos/aceites/mobil_super.jpeg'),
((SELECT id FROM categorias WHERE nombre = 'aceites'), 'Motul 7100 20W-50', 'Lubricante 100% sintético para 4 tiempos.', 82000, 'img/productos/aceites/motul dorado.jpeg');

-- Frenos
INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen) VALUES
((SELECT id FROM categorias WHERE nombre = 'frenos'), 'Pastillas de freno Brembo', 'Pastillas de freno para sistemas de disco.', 215000, 'img/productos/frenos/Pastilla_Brembo.webp'),
((SELECT id FROM categorias WHERE nombre = 'frenos'), 'Pastillas de freno Frasle', 'Pastillas de freno para sistema de disco.', 127000, 'img/productos/frenos/Pastilla_Frasle.png'),
((SELECT id FROM categorias WHERE nombre = 'frenos'), 'Pastillas de freno Ridex', 'Set de pastillas de freno.', 95000, 'img/productos/frenos/Pastilla_Ridex.jpg'),
((SELECT id FROM categorias WHERE nombre = 'frenos'), 'Pastillas de freno Stark', 'Pastillas de freno para vehículos.', 136000, 'img/productos/frenos/Pastilla_Stark.jpg'),
((SELECT id FROM categorias WHERE nombre = 'frenos'), 'Pastillas de freno Bosch', 'Pastillas de freno de alto rendimiento.', 174000, 'img/productos/frenos/Pastillas_Bosch.jpg');

-- Repuestos
INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen) VALUES
((SELECT id FROM categorias WHERE nombre = 'repuestos'), 'Amortiguador trasero', 'Absorbe impactos y controla la suspensión.', 157000, 'img/productos/repuestos/Amortiguador trasero.jpg'),
((SELECT id FROM categorias WHERE nombre = 'repuestos'), 'Bujía', 'Genera la chispa para la combustión.', 16500, 'img/productos/repuestos/Bujia.jpg'),
((SELECT id FROM categorias WHERE nombre = 'repuestos'), 'Cadena de transmisión', 'Transfiere el movimiento hacia la rueda.', 59000, 'img/productos/repuestos/Cadena de transmision.jpg'),
((SELECT id FROM categorias WHERE nombre = 'repuestos'), 'Filtro de aire', 'Limpia el aire que entra al motor.', 37000, 'img/productos/repuestos/Filtro de aire.jpg'),
((SELECT id FROM categorias WHERE nombre = 'repuestos'), 'Kit de arrastre', 'Transmite potencia del motor a la rueda.', 140000, 'img/productos/repuestos/Kit de arrastre.jpg');

-- Accesorios
INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen) VALUES
((SELECT id FROM categorias WHERE nombre = 'accesorios'), 'Anclaje', 'Accesorio de seguridad para proteger tu motocicleta.', 127000, 'img/productos/accesorios/Anclaje.png'),
((SELECT id FROM categorias WHERE nombre = 'accesorios'), 'Cadena antirrobo', 'Mayor protección contra robos.', 167000, 'img/productos/accesorios/Cadena antirobo.png'),
((SELECT id FROM categorias WHERE nombre = 'accesorios'), 'Funda', 'Protege la moto del polvo y la lluvia.', 68000, 'img/productos/accesorios/Funda.png'),
((SELECT id FROM categorias WHERE nombre = 'accesorios'), 'Puños calefactables', 'Mayor comodidad en climas fríos.', 116000, 'img/productos/accesorios/PUÑOS CALEFACTABLES.png'),
((SELECT id FROM categorias WHERE nombre = 'accesorios'), 'Retrovisores', 'Mejor visibilidad y seguridad al conducir.', 67000, 'img/productos/accesorios/Retrovisores.png');
-- =========================================================
-- Actualización v2 — MotosX
-- Ejecutar sobre la base de datos motox_db ya existente.
--   mysql -u root -p motox_db < actualizacion_v2.sql
-- (Este mismo bloque ya viene incluido al final de motox_db.sql,
--  así que si vas a instalar el proyecto DESDE CERO no necesitas
--  correr este archivo aparte).
-- =========================================================

USE motox_db;

-- ---------------------------------------------------------
-- Nuevos campos en productos: vendedor y características
-- ---------------------------------------------------------
ALTER TABLE productos
    ADD COLUMN vendedor_nombre     VARCHAR(150) NOT NULL DEFAULT 'MotosX Store' AFTER imagen,
    ADD COLUMN vendedor_ubicacion  VARCHAR(150) NOT NULL DEFAULT 'Bogotá, Colombia' AFTER vendedor_nombre,
    ADD COLUMN caracteristicas     TEXT NULL AFTER vendedor_ubicacion;

-- ---------------------------------------------------------
-- Galería de imágenes por producto (hasta 5 por producto)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS producto_imagenes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    producto_id  INT NOT NULL,
    url_imagen   VARCHAR(255) NOT NULL,
    orden        INT NOT NULL DEFAULT 1,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Reseñas y calificaciones (1 a 5 estrellas) por producto
-- Un usuario solo puede dejar una reseña por producto
-- (si vuelve a calificar, se actualiza su reseña anterior)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS resenas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    producto_id     INT NOT NULL,
    usuario_id      INT NOT NULL,
    nombre_usuario  VARCHAR(150) NOT NULL,
    calificacion    TINYINT NOT NULL,
    comentario      TEXT,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resena_usuario_producto (producto_id, usuario_id),
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Datos de vendedor y características para cada producto
-- (las características van separadas por "|")
-- ---------------------------------------------------------

-- Llantas
UPDATE productos SET vendedor_nombre='MotoTraction Bogotá', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Compuesto de goma reforzada|Diseño de taco profundo|Resistente a cortes y pinchazos'
    WHERE nombre='Llantas off Road';
UPDATE productos SET vendedor_nombre='Michelin Colombia', vendedor_ubicacion='Medellín, Colombia',
    caracteristicas='Tecnología 2CT para mayor duración|Excelente agarre en mojado|Ideal para touring y carretera'
    WHERE nombre='Llanta Michelin Pilot Road';
UPDATE productos SET vendedor_nombre='Pirelli Store CO', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Compuesto de alto rendimiento|Óptima adherencia en curva|Diseño deportivo'
    WHERE nombre='Pirelli Diablo Rosso';
UPDATE productos SET vendedor_nombre='Pirelli Store CO', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Uso mixto ciudad/destapado|Banda de rodadura versátil|Buena estabilidad a alta velocidad'
    WHERE nombre='Llanta Pirelli MT60';
UPDATE productos SET vendedor_nombre='Shinko Andina', vendedor_ubicacion='Bucaramanga, Colombia',
    caracteristicas='Larga duración|Confort en carretera|Buena relación precio-calidad'
    WHERE nombre='Shinko SR241';

-- Aceites
UPDATE productos SET vendedor_nombre='Castrol Colombia', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Tecnología sintética|Protección contra el desgaste|Para motores de 4 tiempos'
    WHERE nombre='Castrol Power 1';
UPDATE productos SET vendedor_nombre='Repsol Lubricantes', vendedor_ubicacion='Medellín, Colombia',
    caracteristicas='Fórmula semisintética|Buena lubricación en frío|Compatible con embrague húmedo'
    WHERE nombre='Repsol Moto Sport 10W-40';
UPDATE productos SET vendedor_nombre='Suzuki Ecstar CO', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Desarrollado por Suzuki|Óptimo rendimiento del motor|Reduce la fricción interna'
    WHERE nombre='Ecstar R7000 10W-40';
UPDATE productos SET vendedor_nombre='Mobil Colombia', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Alta viscosidad|Protección en climas cálidos|Larga vida útil del motor'
    WHERE nombre='Mobil Super Ultra 20W-50';
UPDATE productos SET vendedor_nombre='Motul Andina', vendedor_ubicacion='Pereira, Colombia',
    caracteristicas='100% sintético|Alto rendimiento en competencia|Máxima protección térmica'
    WHERE nombre='Motul 7100 20W-50';

-- Frenos
UPDATE productos SET vendedor_nombre='Brembo Colombia', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Frenado de alto rendimiento|Baja generación de polvo|Resistente al desgaste'
    WHERE nombre='Pastillas de freno Brembo';
UPDATE productos SET vendedor_nombre='Frasle Andina', vendedor_ubicacion='Medellín, Colombia',
    caracteristicas='Fabricación certificada|Buen desempeño en frenadas bruscas|Fácil instalación'
    WHERE nombre='Pastillas de freno Frasle';
UPDATE productos SET vendedor_nombre='Ridex Parts', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Económicas y confiables|Compatible con varios modelos|Frenado estable'
    WHERE nombre='Pastillas de freno Ridex';
UPDATE productos SET vendedor_nombre='Stark Motorparts', vendedor_ubicacion='Bucaramanga, Colombia',
    caracteristicas='Material cerámico|Menor ruido al frenar|Buena disipación de calor'
    WHERE nombre='Pastillas de freno Stark';
UPDATE productos SET vendedor_nombre='Bosch Colombia', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Tecnología alemana|Alta durabilidad|Frenado preciso'
    WHERE nombre='Pastillas de freno Bosch';

-- Repuestos
UPDATE productos SET vendedor_nombre='SuspensionesMoto', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Ajuste de precarga|Resistente a impactos|Mejora la estabilidad'
    WHERE nombre='Amortiguador trasero';
UPDATE productos SET vendedor_nombre='ElectroMoto Parts', vendedor_ubicacion='Medellín, Colombia',
    caracteristicas='Encendido eficiente|Electrodo de níquel|Fácil instalación'
    WHERE nombre='Bujía';
UPDATE productos SET vendedor_nombre='TransmiMoto', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Acero reforzado|Baja fricción|Larga duración'
    WHERE nombre='Cadena de transmisión';
UPDATE productos SET vendedor_nombre='FiltrosMoto CO', vendedor_ubicacion='Bucaramanga, Colombia',
    caracteristicas='Alta capacidad de filtrado|Mejora el rendimiento del motor|Fácil mantenimiento'
    WHERE nombre='Filtro de aire';
UPDATE productos SET vendedor_nombre='TransmiMoto', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Incluye cadena, piñón y corona|Materiales resistentes|Ideal para uso intensivo'
    WHERE nombre='Kit de arrastre';

-- Accesorios
UPDATE productos SET vendedor_nombre='SeguridadMoto', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Acero de alta resistencia|Fácil de instalar|Compatible con varias cadenas'
    WHERE nombre='Anclaje';
UPDATE productos SET vendedor_nombre='SeguridadMoto', vendedor_ubicacion='Bogotá, Colombia',
    caracteristicas='Eslabones templados|Funda protectora incluida|Alta resistencia al corte'
    WHERE nombre='Cadena antirrobo';
UPDATE productos SET vendedor_nombre='MotoCovers CO', vendedor_ubicacion='Medellín, Colombia',
    caracteristicas='Material impermeable|Protección UV|Ajuste universal'
    WHERE nombre='Funda';
UPDATE productos SET vendedor_nombre='ComfortRide', vendedor_ubicacion='Pereira, Colombia',
    caracteristicas='Varios niveles de calor|Fácil instalación|Ideal para clima frío'
    WHERE nombre='Puños calefactables';
UPDATE productos SET vendedor_nombre='AccesoriosMoto CO', vendedor_ubicacion='Cali, Colombia',
    caracteristicas='Visión amplia|Resistentes a vibraciones|Diseño universal'
    WHERE nombre='Retrovisores';

-- ---------------------------------------------------------
-- Imagen principal de cada producto como primera foto de su
-- galería (el modal soporta hasta 5; puedes agregar más filas
-- en producto_imagenes con orden 2, 3, 4, 5 cuando tengas más
-- fotos de cada producto)
-- ---------------------------------------------------------
INSERT INTO producto_imagenes (producto_id, url_imagen, orden)
SELECT id, imagen, 1 FROM productos;
