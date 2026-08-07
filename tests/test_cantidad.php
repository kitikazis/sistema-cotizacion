<?php
/**
 * La cantidad se mostraba con cero decimales y 2.5 unidades salian como "3"
 * en el PDF del cliente: una cifra que no cuadraba con el importe.
 *
 * Ejecutar:  php tests/test_cantidad.php
 */

require_once __DIR__ . '/../helpers/funciones.php';

$casos = [
    [2,      '2'],
    [2.0,    '2'],
    ['3.000', '3'],
    [2.5,    '2.5'],
    [0.25,   '0.25'],
    [1.125,  '1.125'],
    [10,     '10'],
    [1500,   '1,500'],
    [1500.5, '1,500.5'],
];

$fallos = 0;

foreach ($casos as [$entrada, $esperado]) {
    $obtenido = cantidad($entrada);
    $ok = $obtenido === $esperado;

    if (!$ok) {
        $fallos++;
    }

    printf("  %-5s %-10s -> %-10s (esperado %s)%s",
        $ok ? 'OK' : 'FALLA', var_export($entrada, true), $obtenido, $esperado, PHP_EOL);
}

echo PHP_EOL;
echo $fallos === 0
    ? "Las cantidades se muestran sin redondear.\n"
    : "{$fallos} fallos.\n";

exit($fallos === 0 ? 0 : 1);
