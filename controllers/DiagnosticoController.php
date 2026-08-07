<?php

require_once __DIR__ . '/../helpers/diagnostico.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../helpers/iconos.php';

/**
 * Pantalla de estado del sistema.
 *
 * Es publica a proposito: tiene que poder verse justo cuando la base no
 * conecta, que es cuando no se puede iniciar sesion. No expone
 * contrasenas ni el mensaje crudo del driver en produccion.
 */
class DiagnosticoController
{
    public function estado(): void
    {
        $bloques  = verificarSistema();
        $todoBien = sistemaCorrecto($bloques);

        // 503 si algo esta mal: util para monitores externos.
        http_response_code($todoBien ? 200 : 503);

        require __DIR__ . '/../views/estado.php';
    }
}
