<?php
/**
 * Front controller del modulo de cotizaciones.
 *
 * Rutas:  index.php?accion=index|crear|guardar|ver|pdf|eliminar
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/funciones.php';
require_once __DIR__ . '/helpers/PricingCalculator.php';
require_once __DIR__ . '/controllers/CotizacionController.php';

// En local queremos ver los errores; en produccion esto se apaga.
ini_set('display_errors', '1');
error_reporting(E_ALL);

const ACCIONES_VALIDAS = ['index', 'crear', 'guardar', 'ver', 'pdf', 'eliminar'];

$accion = (string) ($_GET['accion'] ?? 'index');

if (!in_array($accion, ACCIONES_VALIDAS, true)) {
    http_response_code(404);
    exit('Accion no valida: ' . htmlspecialchars($accion, ENT_QUOTES, 'UTF-8'));
}

(new CotizacionController())->{$accion}();
