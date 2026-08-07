<?php
/**
 * PLANTILLA de las credenciales de produccion.
 *
 * Copiar a `credenciales.php` en el servidor y completar con lo que
 * entregue cPanel > Bases de datos MySQL. NO se versiona.
 *
 *     cp config/credenciales.example.php config/credenciales.php
 *
 * En cPanel el usuario y la base llevan el prefijo de la cuenta, por
 * ejemplo si la cuenta es "enlixpe" la base sera "enlixpe_cotizador".
 */

return [
    'host'    => 'localhost',
    'puerto'  => 3306,
    'base'    => 'CUENTA_cotizador',
    'usuario' => 'CUENTA_cotizador',
    'clave'   => 'LA-CLAVE-QUE-GENERASTE-EN-CPANEL',
];
