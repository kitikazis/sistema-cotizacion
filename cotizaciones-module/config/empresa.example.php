<?php
/**
 * PLANTILLA de los datos del emisor.
 *
 * Copia este archivo a `empresa.php` y reemplaza los valores por los reales.
 * `empresa.php` esta en .gitignore a proposito: lleva cuentas bancarias y
 * datos de contacto que no deben viajar al repositorio.
 *
 *     cp config/empresa.example.php config/empresa.php
 */

return [
    'razon_social' => 'Mi Empresa S.A.C',
    'ruc'          => '20000000000',
    'web'          => 'miempresa.pe',

    'cuentas' => [
        ['banco' => 'Banco Soles',       'numero' => '0000000000000'],
        ['banco' => 'Banco Soles CCI',   'numero' => '00000000000000000000'],
        ['banco' => 'Banco Dolares',     'numero' => '0000000000000'],
        ['banco' => 'Banco Dolares CCI', 'numero' => '00000000000000000000'],
    ],

    'firma' => [
        'nombre'  => 'Nombre Apellido',
        'cargo'   => 'Gerente General',
        'email'   => 'contacto@miempresa.pe',
        'celular' => 'Cel. 000 000 000',
    ],

    // Opciones que el Excel ofrece como listas
    'opciones' => [
        'validez_dias'        => [7, 10, 15, 30],
        'credito_dias'        => [7, 15, 30, 60, 90, 120],
        'tiempo_entrega_dias' => [7, 15, 30, 60],
    ],
];
