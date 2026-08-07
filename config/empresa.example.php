<?php
/**
 * PLANTILLA de los datos del emisor, solo como referencia de la estructura.
 *
 * Los valores reales de Enlix (razon social, RUC y cuentas Interbank) son
 * FIJOS y viven en `empresa.php`, que es el archivo que la aplicacion usa.
 * Esta plantilla nunca se carga sola: si `empresa.php` falta, configEmpresa()
 * lanza una excepcion en vez de emitir cotizaciones con datos de relleno.
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
