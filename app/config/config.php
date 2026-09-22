<?php

// Aplicación
defined('APP_NAME')     || define('APP_NAME', 'MotosX');
defined('APP_DEBUG')    || define('APP_DEBUG', true);          // false en producción
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'America/Bogota');

// URL base pública. 
defined('BASE_URL') || define('BASE_URL', '');

// Base de datos (MySQL)
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', 3306);
defined('DB_NAME') || define('DB_NAME', 'motox_db');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Rutas del sistema de archivos
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('VIEW_PATH',    APP_PATH . '/views');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Imágenes de productos: carpeta PÚBLICA 
define('UPLOAD_PRODUCTOS_DIR', PUBLIC_PATH . '/uploads/productos');
define('UPLOAD_PRODUCTOS_URL', 'uploads/productos');

// Imágenes de categoría carpeta PÚBLICA
define('UPLOAD_CATEGORIAS_DIR', PUBLIC_PATH . '/uploads/categorias');
define('UPLOAD_CATEGORIAS_URL', 'uploads/categorias');

// Documentos legales de vendedores
define('DOCS_VENDEDORES_DIR', STORAGE_PATH . '/documentos_vendedores');

// Reglas de negocio
defined('MAX_IMAGEN_BYTES')     || define('MAX_IMAGEN_BYTES', 2 * 1024 * 1024);    // 2 MB
defined('MAX_DOCUMENTO_BYTES')  || define('MAX_DOCUMENTO_BYTES', 5 * 1024 * 1024); // 5 MB
defined('MAX_IMAGENES_PRODUCTO')|| define('MAX_IMAGENES_PRODUCTO', 5);
defined('HORAS_EDICION_PEDIDO') || define('HORAS_EDICION_PEDIDO', 24);

// true  = 
// false = 
defined('VENDEDOR_REQUIERE_APROBACION') || define('VENDEDOR_REQUIERE_APROBACION', true);
