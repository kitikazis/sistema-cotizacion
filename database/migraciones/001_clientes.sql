CREATE TABLE IF NOT EXISTS clientes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razon_social   VARCHAR(200) NOT NULL,
    ruc            VARCHAR(11)  NULL,
    direccion      VARCHAR(255) NULL,
    creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_clientes_ruc (ruc),
    KEY idx_clientes_razon_social (razon_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
