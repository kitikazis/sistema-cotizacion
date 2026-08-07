<?php
/**
 * Ajustes generales del modulo.
 */

return [
    /*
     |----------------------------------------------------------------
     | acceso_libre
     |----------------------------------------------------------------
     |
     | true  = se entra sin login. SOLO PARA PRUEBAS.
     | false = se exige usuario y contrasena (comportamiento normal).
     |
     | Con esto en true cualquiera que llegue a la URL ve costos,
     | margenes, porcentajes de ganancia y la cartera de clientes.
     | Mientras este activo, la aplicacion muestra un aviso rojo en
     | todas las pantallas para que no se quede encendido por olvido.
     |
     | ANTES DE USARLO CON DATOS REALES: ponerlo en false.
     |
     */
    'acceso_libre' => true,
];
