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
 * Carga los datos del emisor (razon social, RUC, cuentas, firma).
 *
 * Son valores FIJOS de Enlix. Si config/empresa.php no existe se corta la
 * ejecucion a proposito: caer a la plantilla haria que una cotizacion salga
 * hacia el cliente con RUC y cuentas bancarias de relleno, que es peor que
 * no emitirla.
 */
function configEmpresa(): array
{
    $real = __DIR__ . '/../config/empresa.php';

    if (!is_file($real)) {
        throw new RuntimeException(
            'Falta config/empresa.php (razon social, RUC y cuentas del emisor). '
            . 'Crealo copiando config/empresa.example.php y poniendo los datos reales.'
        );
    }

    return require $real;
}

/** Redirige y corta la ejecucion. */
function redirigir(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}
