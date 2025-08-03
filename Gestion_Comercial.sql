-- ====================================================================
-- SISTEMA DE GESTIÓN COMERCIAL - BASE DE DATOS
-- Proyecto Final - Aplicación de Tecnologías Web
-- Autores: Estiven Oña - Jhoan Salazar
-- ====================================================================

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS `gestion_comercial` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestion_comercial`;

-- ====================================================================
-- TABLAS DEL SISTEMA DE PERMISOS Y USUARIOS
-- ====================================================================

-- Tabla de Permisos
CREATE TABLE `permisos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(50) NOT NULL UNIQUE,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_permisos_codigo` (`codigo`)
) ENGINE=InnoDB;

-- Tabla de Roles
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL UNIQUE,
  `descripcion` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_roles_nombre` (`nombre`)
) ENGINE=InnoDB;

-- Tabla puente Rol-Permiso
CREATE TABLE `rol_permisos` (
  `id_rol` INT UNSIGNED NOT NULL,
  `id_permiso` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rol`, `id_permiso`),
  FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_permiso`) REFERENCES `permisos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Usuarios
CREATE TABLE `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `estado` TINYINT DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_usuarios_username` (`username`),
  INDEX `idx_usuarios_estado` (`estado`)
) ENGINE=InnoDB;

-- Tabla puente Usuario-Rol
CREATE TABLE `usuario_roles` (
  `id_usuario` INT UNSIGNED NOT NULL,
  `id_rol` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`, `id_rol`),
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ====================================================================
-- TABLAS DE CATÁLOGOS
-- ====================================================================

-- Tabla de Categorías (con soporte jerárquico)
CREATE TABLE `categorias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT,
  `estado` TINYINT DEFAULT 1,
  `id_padre` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_padre`) REFERENCES `categorias`(`id`) ON DELETE SET NULL,
  INDEX `idx_categorias_nombre` (`nombre`),
  INDEX `idx_categorias_estado` (`estado`),
  INDEX `idx_categorias_padre` (`id_padre`)
) ENGINE=InnoDB;

-- ====================================================================
-- TABLAS DE CLIENTES (CON HERENCIA)
-- ====================================================================

-- Tabla base de Clientes
CREATE TABLE `clientes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `telefono` VARCHAR(20),
  `direccion` VARCHAR(255),
  `tipo_cliente` ENUM('natural', 'juridico') NOT NULL,
  `estado` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_clientes_email` (`email`),
  INDEX `idx_clientes_tipo` (`tipo_cliente`),
  INDEX `idx_clientes_estado` (`estado`)
) ENGINE=InnoDB;

-- Tabla de Personas Naturales
CREATE TABLE `clientes_naturales` (
  `id_cliente` INT UNSIGNED NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(100) NOT NULL,
  `cedula` VARCHAR(10) NOT NULL UNIQUE,
  PRIMARY KEY (`id_cliente`),
  FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  INDEX `idx_clientes_naturales_cedula` (`cedula`),
  INDEX `idx_clientes_naturales_nombres` (`nombres`, `apellidos`)
) ENGINE=InnoDB;

-- Tabla de Personas Jurídicas
CREATE TABLE `clientes_juridicos` (
  `id_cliente` INT UNSIGNED NOT NULL,
  `razon_social` VARCHAR(255) NOT NULL,
  `ruc` VARCHAR(13) NOT NULL UNIQUE,
  `representante_legal` VARCHAR(255),
  PRIMARY KEY (`id_cliente`),
  FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  INDEX `idx_clientes_juridicos_ruc` (`ruc`),
  INDEX `idx_clientes_juridicos_razon` (`razon_social`)
) ENGINE=InnoDB;

-- ====================================================================
-- TABLAS DE PRODUCTOS (CON HERENCIA)
-- ====================================================================

-- Tabla base de Productos
CREATE TABLE `productos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(255) NOT NULL,
  `descripcion` TEXT,
  `precio_unitario` DECIMAL(10, 2) NOT NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_categoria` INT UNSIGNED NOT NULL,
  `tipo_producto` ENUM('fisico', 'digital') NOT NULL,
  `estado` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_categoria`) REFERENCES `categorias`(`id`) ON DELETE RESTRICT,
  INDEX `idx_productos_nombre` (`nombre`),
  INDEX `idx_productos_categoria` (`id_categoria`),
  INDEX `idx_productos_tipo` (`tipo_producto`),
  INDEX `idx_productos_estado` (`estado`),
  INDEX `idx_productos_precio` (`precio_unitario`)
) ENGINE=InnoDB;

-- Tabla de Productos Físicos
CREATE TABLE `productos_fisicos` (
  `id_producto` INT UNSIGNED NOT NULL,
  `peso` DECIMAL(8, 2) NOT NULL COMMENT 'Peso en kilogramos',
  `alto` DECIMAL(8, 2) NOT NULL COMMENT 'Alto en centímetros',
  `ancho` DECIMAL(8, 2) NOT NULL COMMENT 'Ancho en centímetros',
  `profundidad` DECIMAL(8, 2) NOT NULL COMMENT 'Profundidad en centímetros',
  PRIMARY KEY (`id_producto`),
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Productos Digitales
CREATE TABLE `productos_digitales` (
  `id_producto` INT UNSIGNED NOT NULL,
  `url_descarga` VARCHAR(500),
  `licencia` VARCHAR(255),
  `clave_activacion` VARCHAR(100),
  `fecha_expiracion` TIMESTAMP NULL,
  `max_descargas` INT DEFAULT -1 COMMENT '-1 = ilimitadas',
  `descargas_realizadas` INT DEFAULT 0,
  PRIMARY KEY (`id_producto`),
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE CASCADE,
  INDEX `idx_productos_digitales_expiracion` (`fecha_expiracion`)
) ENGINE=InnoDB;

-- ====================================================================
-- TABLAS DE VENTAS Y FACTURACIÓN
-- ====================================================================

-- Tabla de Ventas
CREATE TABLE `ventas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `id_cliente` INT UNSIGNED NOT NULL,
  `total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('borrador', 'emitida', 'anulada') NOT NULL DEFAULT 'borrador',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id`) ON DELETE RESTRICT,
  INDEX `idx_ventas_fecha` (`fecha`),
  INDEX `idx_ventas_cliente` (`id_cliente`),
  INDEX `idx_ventas_estado` (`estado`),
  INDEX `idx_ventas_total` (`total`)
) ENGINE=InnoDB;

-- Tabla de Detalles de Venta
CREATE TABLE `detalle_ventas` (
  `id_venta` INT UNSIGNED NOT NULL,
  `line_number` INT UNSIGNED NOT NULL,
  `id_producto` INT UNSIGNED NOT NULL,
  `cantidad` INT UNSIGNED NOT NULL,
  `precio_unitario` DECIMAL(10, 2) NOT NULL,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  PRIMARY KEY (`id_venta`, `line_number`),
  FOREIGN KEY (`id_venta`) REFERENCES `ventas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE RESTRICT,
  INDEX `idx_detalle_ventas_producto` (`id_producto`),
  INDEX `idx_detalle_ventas_subtotal` (`subtotal`)
) ENGINE=InnoDB;

-- Tabla de Facturas
CREATE TABLE `facturas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_venta` INT UNSIGNED NOT NULL,
  `numero` VARCHAR(20) NOT NULL UNIQUE,
  `clave_acceso` VARCHAR(49) UNIQUE,
  `fecha_emision` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('pendiente', 'emitida', 'autorizada', 'anulada') NOT NULL DEFAULT 'pendiente',
  `xml_autorizado` LONGTEXT,
  `url_pdf` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_venta`) REFERENCES `ventas`(`id`) ON DELETE RESTRICT,
  INDEX `idx_facturas_numero` (`numero`),
  INDEX `idx_facturas_clave_acceso` (`clave_acceso`),
  INDEX `idx_facturas_fecha` (`fecha_emision`),
  INDEX `idx_facturas_estado` (`estado`)
) ENGINE=InnoDB;

-- ====================================================================
-- TRIGGERS PARA MANTENIMIENTO AUTOMÁTICO
-- ====================================================================

-- Trigger para actualizar total de venta cuando se insertan/actualizan detalles
DELIMITER $$
CREATE TRIGGER `tr_detalle_venta_after_insert` 
AFTER INSERT ON `detalle_ventas`
FOR EACH ROW
BEGIN
    UPDATE `ventas` 
    SET `total` = (
        SELECT SUM(`subtotal`) 
        FROM `detalle_ventas` 
        WHERE `id_venta` = NEW.`id_venta`
    )
    WHERE `id` = NEW.`id_venta`;
END$$

CREATE TRIGGER `tr_detalle_venta_after_update` 
AFTER UPDATE ON `detalle_ventas`
FOR EACH ROW
BEGIN
    UPDATE `ventas` 
    SET `total` = (
        SELECT SUM(`subtotal`) 
        FROM `detalle_ventas` 
        WHERE `id_venta` = NEW.`id_venta`
    )
    WHERE `id` = NEW.`id_venta`;
END$$

CREATE TRIGGER `tr_detalle_venta_after_delete` 
AFTER DELETE ON `detalle_ventas`
FOR EACH ROW
BEGIN
    UPDATE `ventas` 
    SET `total` = COALESCE((
        SELECT SUM(`subtotal`) 
        FROM `detalle_ventas` 
        WHERE `id_venta` = OLD.`id_venta`
    ), 0.00)
    WHERE `id` = OLD.`id_venta`;
END$$
DELIMITER ;

-- ====================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- ====================================================================

-- Procedimiento para validar stock antes de venta
DELIMITER $$
CREATE PROCEDURE `sp_validar_stock`(
    IN p_id_producto INT UNSIGNED,
    IN p_cantidad INT UNSIGNED,
    OUT p_resultado TINYINT,
    OUT p_stock_disponible INT UNSIGNED
)
BEGIN
    DECLARE v_stock_actual INT UNSIGNED DEFAULT 0;
    DECLARE v_tipo_producto ENUM('fisico', 'digital');
    
    -- Obtener stock actual y tipo de producto
    SELECT `stock`, `tipo_producto` 
    INTO v_stock_actual, v_tipo_producto
    FROM `productos` 
    WHERE `id` = p_id_producto AND `estado` = 1;
    
    SET p_stock_disponible = v_stock_actual;
    
    -- Los productos digitales normalmente no afectan stock
    IF v_tipo_producto = 'digital' THEN
        SET p_resultado = 1; -- OK
    ELSE
        -- Validar stock para productos físicos
        IF v_stock_actual >= p_cantidad THEN
            SET p_resultado = 1; -- OK
        ELSE
            SET p_resultado = 0; -- Sin stock suficiente
        END IF;
    END IF;
END$$

-- Procedimiento para descontar stock
CREATE PROCEDURE `sp_descontar_stock`(
    IN p_id_producto INT UNSIGNED,
    IN p_cantidad INT UNSIGNED,
    OUT p_resultado TINYINT
)
BEGIN
    DECLARE v_stock_actual INT UNSIGNED DEFAULT 0;
    DECLARE v_tipo_producto ENUM('fisico', 'digital');
    
    -- Obtener información del producto
    SELECT `stock`, `tipo_producto` 
    INTO v_stock_actual, v_tipo_producto
    FROM `productos` 
    WHERE `id` = p_id_producto AND `estado` = 1;
    
    -- Solo descontar stock para productos físicos
    IF v_tipo_producto = 'fisico' THEN
        IF v_stock_actual >= p_cantidad THEN
            UPDATE `productos` 
            SET `stock` = `stock` - p_cantidad 
            WHERE `id` = p_id_producto;
            SET p_resultado = 1; -- OK
        ELSE
            SET p_resultado = 0; -- Sin stock suficiente
        END IF;
    ELSE
        SET p_resultado = 1; -- Los productos digitales siempre OK
    END IF;
END$$

-- Procedimiento para devolver stock (en caso de anular venta)
CREATE PROCEDURE `sp_devolver_stock`(
    IN p_id_venta INT UNSIGNED
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id_producto INT UNSIGNED;
    DECLARE v_cantidad INT UNSIGNED;
    DECLARE v_tipo_producto ENUM('fisico', 'digital');
    
    DECLARE cur_detalles CURSOR FOR 
        SELECT dv.`id_producto`, dv.`cantidad`, p.`tipo_producto`
        FROM `detalle_ventas` dv
        INNER JOIN `productos` p ON dv.`id_producto` = p.`id`
        WHERE dv.`id_venta` = p_id_venta;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur_detalles;
    
    read_loop: LOOP
        FETCH cur_detalles INTO v_id_producto, v_cantidad, v_tipo_producto;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Solo devolver stock para productos físicos
        IF v_tipo_producto = 'fisico' THEN
            UPDATE `productos` 
            SET `stock` = `stock` + v_cantidad 
            WHERE `id` = v_id_producto;
        END IF;
    END LOOP;
    
    CLOSE cur_detalles;
END$$
DELIMITER ;

-- ====================================================================
-- DATOS INICIALES DEL SISTEMA
-- ====================================================================

-- Insertar permisos básicos
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`) VALUES
('CRUD_CLIENTE', 'Gestión completa de clientes', 'Crear, leer, actualizar y eliminar clientes'),
('VER_CLIENTE', 'Ver clientes', 'Solo visualizar información de clientes'),
('CREAR_CLIENTE', 'Crear clientes', 'Crear nuevos clientes'),
('EDITAR_CLIENTE', 'Editar clientes', 'Modificar información de clientes'),
('ELIMINAR_CLIENTE', 'Eliminar clientes', 'Eliminar clientes del sistema'),

('CRUD_PRODUCTO', 'Gestión completa de productos', 'Crear, leer, actualizar y eliminar productos'),
('VER_PRODUCTO', 'Ver productos', 'Solo visualizar información de productos'),
('CREAR_PRODUCTO', 'Crear productos', 'Crear nuevos productos'),
('EDITAR_PRODUCTO', 'Editar productos', 'Modificar información de productos'),
('ELIMINAR_PRODUCTO', 'Eliminar productos', 'Eliminar productos del sistema'),

('CRUD_CATEGORIA', 'Gestión completa de categorías', 'Crear, leer, actualizar y eliminar categorías'),
('VER_CATEGORIA', 'Ver categorías', 'Solo visualizar información de categorías'),
('CREAR_CATEGORIA', 'Crear categorías', 'Crear nuevas categorías'),
('EDITAR_CATEGORIA', 'Editar categorías', 'Modificar información de categorías'),
('ELIMINAR_CATEGORIA', 'Eliminar categorías', 'Eliminar categorías del sistema'),

('CRUD_VENTA', 'Gestión completa de ventas', 'Crear, leer, actualizar y eliminar ventas'),
('VER_VENTA', 'Ver ventas', 'Solo visualizar información de ventas'),
('CREAR_VENTA', 'Crear ventas', 'Crear nuevas ventas'),
('EDITAR_VENTA', 'Editar ventas', 'Modificar información de ventas'),
('ELIMINAR_VENTA', 'Eliminar ventas', 'Eliminar ventas del sistema'),
('ANULAR_VENTA', 'Anular ventas', 'Anular ventas emitidas'),

('CRUD_FACTURA', 'Gestión completa de facturas', 'Crear, leer, actualizar y eliminar facturas'),
('VER_FACTURA', 'Ver facturas', 'Solo visualizar información de facturas'),
('CREAR_FACTURA', 'Crear facturas', 'Crear nuevas facturas'),
('EMITIR_FACTURA', 'Emitir facturas', 'Emitir facturas a clientes'),
('ANULAR_FACTURA', 'Anular facturas', 'Anular facturas emitidas'),

('VER_REPORTES', 'Ver reportes', 'Visualizar reportes del sistema'),
('GENERAR_REPORTES', 'Generar reportes', 'Generar nuevos reportes'),
('EXPORTAR_REPORTES', 'Exportar reportes', 'Exportar reportes en diferentes formatos'),

('ADMINISTRAR_USUARIOS', 'Administrar usuarios', 'Gestión completa de usuarios del sistema'),
('ADMINISTRAR_ROLES', 'Administrar roles', 'Gestión completa de roles del sistema'),
('ADMINISTRAR_PERMISOS', 'Administrar permisos', 'Gestión completa de permisos del sistema'),
('CONFIGURAR_SISTEMA', 'Configurar sistema', 'Configuración general del sistema');

-- Insertar roles básicos
INSERT INTO `roles` (`nombre`, `descripcion`) VALUES
('ADMIN', 'Administrador del sistema con acceso completo'),
('VENDEDOR', 'Usuario vendedor con permisos de venta'),
('CONTADOR', 'Usuario contador con permisos de facturación y reportes');

-- Asignar permisos a roles
-- ADMIN: Todos los permisos
INSERT INTO `rol_permisos` (`id_rol`, `id_permiso`) 
SELECT 1, `id` FROM `permisos`;

-- VENDEDOR: Permisos de gestión de clientes, ver productos, gestionar ventas
INSERT INTO `rol_permisos` (`id_rol`, `id_permiso`) 
SELECT 2, `id` FROM `permisos` 
WHERE `codigo` IN ('CRUD_CLIENTE', 'VER_PRODUCTO', 'CRUD_VENTA', 'VER_FACTURA', 'CREAR_FACTURA', 'EMITIR_FACTURA');

-- CONTADOR: Permisos de visualización y gestión de facturas/reportes
INSERT INTO `rol_permisos` (`id_rol`, `id_permiso`) 
SELECT 3, `id` FROM `permisos` 
WHERE `codigo` IN ('VER_CLIENTE', 'VER_PRODUCTO', 'VER_CATEGORIA', 'VER_VENTA', 'CRUD_FACTURA', 'VER_REPORTES', 'GENERAR_REPORTES', 'EXPORTAR_REPORTES');

-- Crear usuario administrador por defecto
INSERT INTO `usuarios` (`username`, `password_hash`) VALUES 
('admin', '$argon2id$v=19$m=65536,t=4,p=3$Q0dOcGVZSVFXaGhSNDhINQ$K9ZzDKTFE8qcJd7Fh6R8tUjL5zY3K4VmXe9H7P1sGi2');
-- Contraseña: admin123

-- Asignar rol ADMIN al usuario admin
INSERT INTO `usuario_roles` (`id_usuario`, `id_rol`) VALUES (1, 1);

-- Insertar algunas categorías de ejemplo
INSERT INTO `categorias` (`nombre`, `descripcion`) VALUES
('Electrónicos', 'Productos electrónicos y tecnológicos'),
('Software', 'Productos digitales y licencias de software'),
('Accesorios', 'Accesorios diversos'),
('Servicios', 'Servicios digitales');

-- ====================================================================
-- VISTAS ÚTILES PARA CONSULTAS
-- ====================================================================

-- Vista de clientes completa
CREATE VIEW `v_clientes_completa` AS
SELECT 
    c.`id`,
    c.`email`,
    c.`telefono`,
    c.`direccion`,
    c.`tipo_cliente`,
    c.`estado`,
    c.`created_at`,
    c.`updated_at`,
    CASE 
        WHEN c.`tipo_cliente` = 'natural' THEN CONCAT(cn.`nombres`, ' ', cn.`apellidos`)
        ELSE cj.`razon_social`
    END AS `nombre_completo`,
    CASE 
        WHEN c.`tipo_cliente` = 'natural' THEN cn.`cedula`
        ELSE cj.`ruc`
    END AS `documento`,
    cn.`nombres`,
    cn.`apellidos`,
    cn.`cedula`,
    cj.`razon_social`,
    cj.`ruc`,
    cj.`representante_legal`
FROM `clientes` c
LEFT JOIN `clientes_naturales` cn ON c.`id` = cn.`id_cliente`
LEFT JOIN `clientes_juridicos` cj ON c.`id` = cj.`id_cliente`;

-- Vista de productos completa
CREATE VIEW `v_productos_completa` AS
SELECT 
    p.`id`,
    p.`nombre`,
    p.`descripcion`,
    p.`precio_unitario`,
    p.`stock`,
    p.`id_categoria`,
    cat.`nombre` AS `categoria_nombre`,
    p.`tipo_producto`,
    p.`estado`,
    p.`created_at`,
    p.`updated_at`,
    -- Datos de productos físicos
    pf.`peso`,
    pf.`alto`,
    pf.`ancho`,
    pf.`profundidad`,
    -- Datos de productos digitales
    pd.`url_descarga`,
    pd.`licencia`,
    pd.`clave_activacion`,
    pd.`fecha_expiracion`,
    pd.`max_descargas`,
    pd.`descargas_realizadas`
FROM `productos` p
INNER JOIN `categorias` cat ON p.`id_categoria` = cat.`id`
LEFT JOIN `productos_fisicos` pf ON p.`id` = pf.`id_producto`
LEFT JOIN `productos_digitales` pd ON p.`id` = pd.`id_producto`;

-- Vista de ventas con información del cliente
CREATE VIEW `v_ventas_completa` AS
SELECT 
    v.`id`,
    v.`fecha`,
    v.`id_cliente`,
    vc.`nombre_completo` AS `cliente_nombre`,
    vc.`documento` AS `cliente_documento`,
    vc.`email` AS `cliente_email`,
    v.`total`,
    v.`estado`,
    v.`created_at`,
    v.`updated_at`,
    (SELECT COUNT(*) FROM `detalle_ventas` WHERE `id_venta` = v.`id`) AS `cantidad_lineas`,
    (SELECT SUM(`cantidad`) FROM `detalle_ventas` WHERE `id_venta` = v.`id`) AS `cantidad_items`
FROM `ventas` v
INNER JOIN `v_clientes_completa` vc ON v.`id_cliente` = vc.`id`;

-- ====================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ====================================================================

-- Índices compuestos para búsquedas frecuentes
CREATE INDEX `idx_ventas_fecha_estado` ON `ventas` (`fecha`, `estado`);
CREATE INDEX `idx_productos_categoria_estado` ON `productos` (`id_categoria`, `estado`);
CREATE INDEX `idx_detalle_ventas_producto_cantidad` ON `detalle_ventas` (`id_producto`, `cantidad`);

COMMIT;