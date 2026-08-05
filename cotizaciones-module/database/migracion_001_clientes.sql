-- =====================================================================
-- Migracion 001: normalizar los datos del cliente
--
-- Antes: cotizaciones guardaba empresa/ruc/direccion repetidos en cada fila.
-- Ahora: tabla `clientes` y una FK desde cotizaciones.
--
-- Es idempotente a medias: correrla dos veces fallara al crear la tabla,
-- que es lo que se quiere (no duplicar clientes en silencio).
-- =====================================================================

USE enlix_cotizaciones;

START TRANSACTION;

-- 1. Maestro de clientes -------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razon_social   VARCHAR(200) NOT NULL,
    ruc            VARCHAR(11)  NULL,
    direccion      VARCHAR(255) NULL,
    creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Varios clientes pueden no tener RUC (MySQL admite NULLs repetidos en
    -- un indice unico), pero un RUC cargado no puede repetirse.
    UNIQUE KEY uq_clientes_ruc (ruc),
    KEY idx_clientes_razon_social (razon_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Volcar los clientes que hoy viven dentro de cotizaciones ------------
INSERT INTO clientes (razon_social, ruc, direccion)
SELECT c.empresa, c.ruc, MAX(c.direccion)
FROM cotizaciones c
GROUP BY c.empresa, c.ruc;

-- 3. Enlazar cada cotizacion con su cliente ------------------------------
ALTER TABLE cotizaciones
    ADD COLUMN cliente_id INT UNSIGNED NULL AFTER numero;

-- <=> es el igual "null-safe": empareja tambien cuando ambos RUC son NULL.
UPDATE cotizaciones c
JOIN clientes cl
  ON cl.razon_social = c.empresa
 AND cl.ruc <=> c.ruc
SET c.cliente_id = cl.id;

-- 4. Ya con todo enlazado, exigir la relacion y soltar las columnas viejas
ALTER TABLE cotizaciones
    MODIFY COLUMN cliente_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_cotizaciones_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    DROP COLUMN empresa,
    DROP COLUMN ruc,
    DROP COLUMN direccion;

COMMIT;
