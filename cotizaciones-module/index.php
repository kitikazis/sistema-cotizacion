<?php
/**
 * Front controller del modulo de cotizaciones.
 *
 * Rutas publicas:  login, entrar
 * Rutas privadas:  el resto, exigen sesion iniciada
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/funciones.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/iconos.php';
require_once __DIR__ . '/helpers/PricingCalculator.php';
require_once __DIR__ . '/controllers/CotizacionController.php';
require_once __DIR__ . '/controllers/AuthController.php';

// En local los errores se ven; en el servidor van al log y nunca a pantalla,
// porque una traza expone rutas y consultas.
error_reporting(E_ALL);
ini_set('display_errors', esProduccion() ? '0' : '1');
ini_set('log_errors', '1');

/** Acciones que se pueden usar sin haber iniciado sesion. */
const ACCIONES_PUBLICAS = [
    'login'  => [AuthController::class, 'login'],
    'entrar' => [AuthController::class, 'entrar'],
];

/** Acciones que exigen sesion. */
const ACCIONES_PRIVADAS = [
    'index'      => [CotizacionController::class, 'index'],
    'crear'      => [CotizacionController::class, 'crear'],
    'guardar'    => [CotizacionController::class, 'guardar'],
    'editar'     => [CotizacionController::class, 'editar'],
    'actualizar' => [CotizacionController::class, 'actualizar'],
    'ver'        => [CotizacionController::class, 'ver'],
    'pdf'        => [CotizacionController::class, 'pdf'],
    'eliminar'   => [CotizacionController::class, 'eliminar'],
    'salir'      => [AuthController::class, 'salir'],
];

$accion = (string) ($_GET['accion'] ?? 'index');

if (isset(ACCIONES_PUBLICAS[$accion])) {
    [$clase, $metodo] = ACCIONES_PUBLICAS[$accion];
    (new $clase())->{$metodo}();
    exit;
}

if (!isset(ACCIONES_PRIVADAS[$accion])) {
    http_response_code(404);
    exit('Accion no valida: ' . htmlspecialchars($accion, ENT_QUOTES, 'UTF-8'));
}

// A partir de aqui, todo exige sesion.
requiereAutenticacion();

[$clase, $metodo] = ACCIONES_PRIVADAS[$accion];
(new $clase())->{$metodo}();
