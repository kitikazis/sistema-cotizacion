CREATE TABLE IF NOT EXISTS cotizacion_items (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cotizacion_id       INT UNSIGNED NOT NULL,
    linea               SMALLINT UNSIGNED NOT NULL COMMENT 'Orden de aparicion, 1..n',

    codigo              VARCHAR(50)  NULL,
    marca               VARCHAR(100) NULL,
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            DECIMAL(12,3) NOT NULL DEFAULT 1,

    precio              DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Precio sin IGV (columna F del Excel)',
    licencia_so         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    delivery            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    embalaje            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    envio               DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'Variable',
    aplica_detraccion   TINYINT(1) NOT NULL DEFAULT 0    COMMENT 'Opcional por item',
    aplica_retencion    TINYINT(1) NOT NULL DEFAULT 0    COMMENT 'Opcional por item',
    porcentaje_ganancia DECIMAL(6,4) NOT NULL DEFAULT 0.1000 COMMENT '0.10|0.12|0.14|0.20|0.22|0.24',

    ir                  DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * 1.5%',
    igv                 DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * 18%',
    detraccion          DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * 12% si aplica',
    ganancia            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio * porcentaje_ganancia',
    subtotal            DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'precio + ir + igv + detraccion + costos + ganancia',
    retencion           DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'subtotal * 3% si aplica',
    total_unitario      DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'subtotal + retencion',
    total_linea         DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'total_unitario * cantidad',

    precio_cliente_unitario DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'total_unitario / 1.18',

    PRIMARY KEY (id),
    KEY idx_items_cotizacion (cotizacion_id),
    CONSTRAINT fk_items_cotizacion
        FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
