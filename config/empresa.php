<?php
/**
 * Datos fijos del emisor y del pie de la cotizacion.
 * Salen del Bloque 1 del Excel (filas 22-40).
 *
 * Este archivo SI se versiona: son valores fijos de Enlix y asi vienen
 * cargados en cada despliegue sin tener que configurarlos a mano.
 *
 * Para cambiarlos: editar aqui, commitear y hacer git pull en el servidor.
 * La razon social y el RUC ademas se pueden sobrescribir por cotizacion
 * desde el formulario; lo que se guarde ahi manda sobre esto.
 */

return [
    'razon_social' => 'Enlix S.A.C',
    'ruc'          => '20616109945',
    'web'          => 'enlix.pe',

    'cuentas' => [
        ['banco' => 'Interbank Soles',        'numero' => '2003008557045'],
        ['banco' => 'Interbank Soles CCI',    'numero' => '00320000300855704532'],
        ['banco' => 'Interbank Dolares',      'numero' => '2003008557052'],
        ['banco' => 'Interbank Dolares CCI',  'numero' => '00320000300855705238'],
    ],

    'firma' => [
        'nombre' => 'Crhistian Garcia Rojas',
        'cargo'  => 'Gerente General',
        'email'  => 'Cgarcia@enlix.pe',
        'celular'=> 'Cel. 963 885 176',
        // Firma escaneada, relativa a la raiz del modulo. Se usa la version
        // con fondo transparente: el escaneo original (firma.png) trae fondo
        // gris #F7F7F7 que sobre el PDF blanco se ve como un recuadro.
        // Si el archivo no existe, el PDF sale sin firma en vez de romperse.
        'imagen' => 'pdf/firma-transparente.png',
    ],

    // Opciones que el Excel ofrece como listas
    'opciones' => [
        'validez_dias'        => [7, 10, 15, 30],
        'credito_dias'        => [7, 15, 30, 60, 90, 120],
        'tiempo_entrega_dias' => [7, 15, 30, 60],
    ],
];
