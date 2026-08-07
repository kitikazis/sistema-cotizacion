CREATE TABLE IF NOT EXISTS cotizaciones (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero              VARCHAR(20)  NOT NULL COMMENT 'Correlativo, ej. 0145',
    cliente_id          INT UNSIGNED NOT NULL,

    emisor_razon_social VARCHAR(200) NULL COMMENT 'NULL = usar config/empresa.php',
    emisor_ruc          VARCHAR(11)  NULL COMMENT 'NULL = usar config/empresa.php',

    fecha_emision       DATE         NOT NULL,
    validez_dias        SMALLINT UNSIGNED NOT NULL DEFAULT 7   COMMENT '7 | 10 | 15 | 30',
    forma_pago          ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    credito_dias        SMALLINT UNSIGNED NULL                 COMMENT '7 | 15 | 30 | 60 | 90 | 120',
    tiempo_entrega_dias SMALLINT UNSIGNED NULL                 COMMENT '7 | 15 | 30 | 60',
    moneda              ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',

    observaciones       TEXT NULL,
    condiciones         TEXT NULL,

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
