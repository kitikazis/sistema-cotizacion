-- =====================================================================
-- Modulo de cotizaciones - enlix.pe
-- MariaDB 10.4 / MySQL 8.0
--
-- Tres tablas:
--   clientes          maestro de clientes, reutilizable entre cotizaciones
--   cotizaciones      la cabecera del documento
--   cotizacion_items  el motor de precios, una fila por item
--
-- Los montos calculados se guardan CONGELADOS: una cotizacion emitida no
-- debe cambiar de precio si manana se toca una tasa. Por eso se guardan
-- tanto las entradas (precio, costos, banderas) como los resultados.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS enlix_cotizaciones
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE enlix_cotizaciones;

DROP TABLE IF EXISTS cotizacion_items;
DROP TABLE IF EXISTS cotizaciones;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;

-- ---------------------------------------------------------------------
-- Usuarios del panel
--
-- Sin registro publico: el primer usuario se crea por consola con
-- tools/crear_usuario.php.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(120) NOT NULL,
    usuario        VARCHAR(60)  NOT NULL,
    -- password_hash() con PASSWORD_DEFAULT: hoy bcrypt (60 chars), pero se
    -- reserva 255 porque el algoritmo por defecto puede cambiar.
    clave_hash     VARCHAR(255) NOT NULL,
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso  DATETIME NULL,
    creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Maestro de clientes
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razon_social   VARCHAR(200) NOT NULL,
    ruc            VARCHAR(11)  NULL,
    direccion      VARCHAR(255) NULL,
    creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Varios clientes pueden quedar sin RUC (un indice unico admite NULLs
    -- repetidos), pero un RUC cargado no se repite.
    UNIQUE KEY uq_clientes_ruc (ruc),
    KEY idx_clientes_razon_social (razon_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cabecera: lo que ve el cliente (Bloque 1 del Excel)
-- ---------------------------------------------------------------------
CREATE TABLE cotizaciones (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero              VARCHAR(20)  NOT NULL COMMENT 'Correlativo, ej. 0145',
    cliente_id          INT UNSIGNED NOT NULL,

    -- Emisor. Precargado de config/empresa.php pero editable por cotizacion
    -- y congelado aqui: un documento viejo conserva la razon social y el RUC
    -- con que se emitio. NULL = usar lo que diga config/empresa.php.
    emisor_razon_social VARCHAR(200) NULL,
    emisor_ruc          VARCHAR(11)  NULL,

    -- Condiciones comerciales
    fecha_emision       DATE         NOT NULL,
    validez_dias        SMALLINT UNSIGNED NOT NULL DEFAULT 7   COMMENT '7 | 10 | 15 | 30',
    forma_pago          ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    credito_dias        SMALLINT UNSIGNED NULL                 COMMENT '7 | 15 | 30 | 60 | 90 | 120',
    tiempo_entrega_dias SMALLINT UNSIGNED NULL                 COMMENT '7 | 15 | 30 | 60',
    moneda              ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',

    observaciones       TEXT NULL,
    condiciones         TEXT NULL,

    -- Totales congelados (derivados de los items, se guardan a proposito)
    total_general       DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'SUM(total_linea) del motor interno',
    cliente_subtotal    DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Base imponible mostrada al cliente',
    cliente_igv         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'cliente_subtotal * 18%',
    cliente_total       DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Debe coincidir con total_general',

    estado              ENUM('borrador','emitida','aceptada','rechazada') NOT NULL DEFAULT 'borrador',
    creado_en           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cotizaciones_numero (numero),
    KEY idx_cotizaciones_cliente (cliente_id),
    KEY idx_cotizaciones_fecha (fecha_emision),
    KEY idx_cotizaciones_estado (estado),

    CONSTRAINT fk_cotizaciones_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Detalle: el motor de precios (Bloque 2 del Excel, fila 43+)
-- ---------------------------------------------------------------------
CREATE TABLE cotizacion_items (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cotizacion_id       INT UNSIGNED NOT NULL,
    linea               SMALLINT UNSIGNED NOT NULL COMMENT 'Orden de aparicion, 1..n',

    codigo              VARCHAR(50)  NULL,
    marca               VARCHAR(100) NULL,
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            DECIMAL(12,3) NOT NULL DEFAULT 1,

    -- ENTRADAS del calculo
    precio              DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Precio sin IGV (columna F)',
    licencia_so         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    delivery            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    embalaje            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    envio               DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    aplica_detraccion   TINYINT(1) NOT NULL DEFAULT 0    COMMENT 'Opcional por item',
    aplica_retencion    TINYINT(1) NOT NULL DEFAULT 0    COMMENT 'Opcional por item',
    porcentaje_ganancia DECIMAL(6,4) NOT NULL DEFAULT 0.1000 COMMENT '0.10|0.12|0.14|0.20|0.22|0.24',

    -- SALIDAS congeladas
    ir                  DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * 1.5%',
    igv                 DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * 18%',
    detraccion          DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * 12% si aplica',
    ganancia            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * porcentaje_ganancia',
    subtotal            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio+ir+igv+detraccion+licencia+delivery+embalaje+envio+ganancia',
    retencion           DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'subtotal * 3% si aplica',
    total_unitario      DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'subtotal + retencion',
    total_linea         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'total_unitario * cantidad',

    -- Puente hacia el Bloque 1: P.UNIT que ve el cliente
    precio_cliente_unitario DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'total_unitario / 1.18',

    PRIMARY KEY (id),
    KEY idx_items_cotizacion (cotizacion_id),
    CONSTRAINT fk_items_cotizacion
        FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
