<?php
/**
 * Utilidades compartidas por vistas y PDF.
 */

/** Escapa para HTML. Usar en TODA salida que venga de la base o del usuario. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formatea un monto con 2 decimales y separador de miles. */
function money($valor, int $decimales = 2): string
{
    return number_format((float) $valor, $decimales, '.', ',');
}

/** Simbolo de la moneda de la cotizacion. */
function simboloMoneda(string $moneda): string
{
    return $moneda === 'USD' ? 'US$' : 'S/';
}

/** Url base del modulo, para armar enlaces sin hardcodear el host. */
function url(string $accion = '', array $params = []): string
{
    $base = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $query = $accion === '' ? [] : ['accion' => $accion];
    $query += $params;

    return $query === [] ? $base : $base . '?' . http_build_query($query);
}

/**
 * Carga los datos del emisor.
 *
 * config/empresa.php no esta versionado porque lleva cuentas bancarias
 * reales; si todavia no existe (por ejemplo en un clon recien hecho) se
 * usa la plantilla para que la app arranque igual.
 */
function configEmpresa(): array
{
    $real    = __DIR__ . '/../config/empresa.php';
    $ejemplo = __DIR__ . '/../config/empresa.example.php';

    return require (is_file($real) ? $real : $ejemplo);
}

/** Redirige y corta la ejecucion. */
function redirigir(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}
