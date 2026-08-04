<?php
/**
 * Test de regresion del motor de precios contra el Excel.
 * Ejecutar:  php tests/test_pricing.php
 *
 * No usa PHPUnit a proposito: el modulo es PHP vanilla.
 */

require_once __DIR__ . '/../helpers/PricingCalculator.php';

$fallos = 0;
$pruebas = 0;

function assertCasi(string $etiqueta, float $esperado, float $obtenido, float $tolerancia = 0.0000001): void
{
    global $fallos, $pruebas;
    $pruebas++;

    if (abs($esperado - $obtenido) <= $tolerancia) {
        printf("  OK   %-26s %s%s", $etiqueta, rtrim(rtrim(number_format($obtenido, 8, '.', ''), '0'), '.'), PHP_EOL);
        return;
    }

    $fallos++;
    printf("  FALLA %-26s esperado=%.8f obtenido=%.8f%s", $etiqueta, $esperado, $obtenido, PHP_EOL);
}

// =====================================================================
// Caso 1: fila 44 del Excel, celda por celda
//   F44=1  B44=1  J44=51  K44=5  L44=5  M44=10  N44=12%  sin detraccion
// =====================================================================
echo "Caso 1 - Fila 44 del Excel (con retencion, sin detraccion)" . PHP_EOL;

$item = PricingCalculator::calcularItem([
    'cantidad'            => 1,
    'precio'              => 1,
    'licencia_so'         => 51,
    'delivery'            => 5,
    'embalaje'            => 5,
    'envio'               => 10,
    'aplica_detraccion'   => false,
    'aplica_retencion'    => true,
    'porcentaje_ganancia' => 0.12,
]);

assertCasi('G44 IR',             0.015,     $item['ir']);
assertCasi('H44 IGV',            0.18,      $item['igv']);
assertCasi('I44 Detraccion',     0.0,       $item['detraccion']);
assertCasi('N44 Ganancia',       0.12,      $item['ganancia']);
assertCasi('O44 Subtotal',       72.315,    $item['subtotal']);
assertCasi('P44 Retencion',      2.16945,   $item['retencion']);
assertCasi('Q44 Total unitario', 74.48445,  $item['total_unitario']);
assertCasi('R44 Total linea',    74.48445,  $item['total_linea']);

// =====================================================================
// Caso 2: la detraccion opcional entra al subtotal
// =====================================================================
echo PHP_EOL . "Caso 2 - Mismo item pero CON detraccion (12%)" . PHP_EOL;

$conDetraccion = PricingCalculator::calcularItem([
    'cantidad'            => 1,
    'precio'              => 1,
    'licencia_so'         => 51,
    'delivery'            => 5,
    'embalaje'            => 5,
    'envio'               => 10,
    'aplica_detraccion'   => true,
    'aplica_retencion'    => true,
    'porcentaje_ganancia' => 0.12,
]);

assertCasi('Detraccion',     0.12,                 $conDetraccion['detraccion']);
assertCasi('Subtotal',       72.435,               $conDetraccion['subtotal']);
assertCasi('Retencion',      72.435 * 0.03,        $conDetraccion['retencion']);
assertCasi('Total unitario', 72.435 * 1.03,        $conDetraccion['total_unitario']);

// =====================================================================
// Caso 3: sin retencion el total unitario es igual al subtotal
// =====================================================================
echo PHP_EOL . "Caso 3 - Sin retencion" . PHP_EOL;

$sinRetencion = PricingCalculator::calcularItem([
    'cantidad'            => 3,
    'precio'              => 1,
    'licencia_so'         => 51,
    'delivery'            => 5,
    'embalaje'            => 5,
    'envio'               => 10,
    'aplica_detraccion'   => false,
    'aplica_retencion'    => false,
    'porcentaje_ganancia' => 0.12,
]);

assertCasi('Retencion',      0.0,           $sinRetencion['retencion']);
assertCasi('Total unitario', 72.315,        $sinRetencion['total_unitario']);
assertCasi('Total linea x3', 72.315 * 3,    $sinRetencion['total_linea']);

// =====================================================================
// Caso 4: el puente al Bloque 1 cierra exacto
//   cliente_total (base + 18%) debe igualar total_general
// =====================================================================
echo PHP_EOL . "Caso 4 - Bloque 1 vs Bloque 2 cuadran" . PHP_EOL;

$resultado = PricingCalculator::calcularCotizacion([
    ['cantidad' => 2, 'precio' => 1, 'licencia_so' => 51, 'delivery' => 5,
     'embalaje' => 5, 'envio' => 10, 'aplica_retencion' => true, 'porcentaje_ganancia' => 0.12],
    ['cantidad' => 1, 'precio' => 100, 'licencia_so' => 0, 'delivery' => 12,
     'embalaje' => 3, 'envio' => 8, 'aplica_detraccion' => true, 'porcentaje_ganancia' => 0.20],
]);

$t = $resultado['totales'];
assertCasi('cliente_total = total_general', $t['total_general'], $t['cliente_total'], 0.000001);
assertCasi('cliente_igv coherente', $t['cliente_subtotal'] * 0.18, $t['cliente_igv']);

// =====================================================================
// Caso 5: normalizacion del porcentaje de ganancia
// =====================================================================
echo PHP_EOL . "Caso 5 - Normalizacion de ganancia" . PHP_EOL;

assertCasi('acepta 0.14', 0.14, PricingCalculator::normalizarGanancia(0.14));
assertCasi('acepta 14',   0.14, PricingCalculator::normalizarGanancia(14));
assertCasi('acepta "24"', 0.24, PricingCalculator::normalizarGanancia('24'));
assertCasi('invalido -> 10%', 0.10, PricingCalculator::normalizarGanancia(0.37));

// =====================================================================
echo PHP_EOL . str_repeat('=', 55) . PHP_EOL;
printf("%d pruebas, %d fallos%s", $pruebas, $fallos, PHP_EOL);
exit($fallos === 0 ? 0 : 1);
