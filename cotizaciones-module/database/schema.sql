-- =====================================================================
-- Modulo de cotizaciones - enlix.pe
-- MariaDB 10.4 / MySQL 8.0
--
-- Los montos calculados se guardan CONGELADOS en la fila: una cotizacion
-- emitida no debe cambiar de precio si manana se edita una tasa.
-- Por eso se guardan tanto las entradas (precio, costos, banderas) como
-- los resultados (ir, igv, subtotal, total_unitario, ...).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS enlix_cotizaciones
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE enlix_cotizaciones;

DROP TABLE IF EXISTS cotizacion_items;
DROP TABLE IF EXISTS cotizaciones;

-- ---------------------------------------------------------------------
-- Cabecera: lo que ve el cliente (Bloque 1 del Excel)
-- ---------------------------------------------------------------------
CREATE TABLE cotizaciones (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero              VARCHAR(20)  NOT NULL COMMENT 'Correlativo, ej. 0145',

    -- Datos del cliente
    empresa             VARCHAR(200) NOT NULL,
    ruc                 VARCHAR(11)  NULL,
    direccion           VARCHAR(255) NULL,

    -- Condiciones comerciales
    fecha_emision       DATE         NOT NULL,
    validez_dias        SMALLINT UNSIGNED NOT NULL DEFAULT 7   COMMENT '7 | 10 | 15 | 30',
    forma_pago          ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    credito_dias        SMALLINT UNSIGNED NULL                 COMMENT '7 | 15 | 30 | 60 | 90 | 120 (solo si forma_pago = credito)',
    tiempo_entrega_dias SMALLINT UNSIGNED NULL                 COMMENT '7 | 15 | 30 | 60',
    moneda              ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',

    observaciones       TEXT NULL,
    condiciones         TEXT NULL,

    -- Totales congelados (calculados desde los items)
    total_general       DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'SUM(total_linea) del motor interno',
    cliente_subtotal    DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Base imponible mostrada al cliente',
    cliente_igv         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'cliente_subtotal * 18%',
    cliente_total       DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Debe coincidir con total_general',

    estado              ENUM('borrador','emitida','aceptada','rechazada') NOT NULL DEFAULT 'borrador',
    creado_en           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_cotizaciones_numero (numero),
    KEY idx_cotizaciones_empresa (empresa),
    KEY idx_cotizaciones_fecha (fecha_emision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Detalle: el motor de precios (Bloque 2 del Excel, fila 43+)
-- ---------------------------------------------------------------------
CREATE TABLE cotizacion_items (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cotizacion_id       INT UNSIGNED NOT NULL,
    linea               SMALLINT UNSIGNED NOT NULL COMMENT 'Orden de aparicion, 1..n',

    -- Identificacion del item
    codigo              VARCHAR(50)  NULL,
    marca               VARCHAR(100) NULL,
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            DECIMAL(12,3) NOT NULL DEFAULT 1,

    -- ENTRADAS del calculo
    precio              DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Precio sin IGV (columna F)',
    licencia_so         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable, se ingresa a mano',
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
