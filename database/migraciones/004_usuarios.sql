CREATE TABLE IF NOT EXISTS usuarios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(120) NOT NULL,
    usuario        VARCHAR(60)  NOT NULL,
    clave_hash     VARCHAR(255) NOT NULL COMMENT 'password_hash con PASSWORD_DEFAULT',
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso  DATETIME NULL,
    creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
