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

/**
 * Url de un archivo estatico con marca de version.
 *
 * Le pega ?v=<fecha de modificacion> para que el navegador descargue la
 * version nueva en cuanto se despliega. Sin esto, tras un git pull el
 * navegador sigue usando el CSS cacheado y la pantalla se ve rota: los
 * estilos nuevos no existen para el, aunque el servidor ya los tenga.
 */
function asset(string $ruta): string
{
    $absoluta = dirname(__DIR__) . '/' . ltrim($ruta, '/');
    $version  = is_file($absoluta) ? filemtime($absoluta) : time();

    return $ruta . '?v=' . $version;
}

/** Redirige y corta la ejecucion. */
function redirigir(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}

/**
 * ¿Estamos en el servidor de produccion?
 *
 * Manda APP_ENV del .env. Si no esta definida se adivina por el host.
 * Ante la duda devuelve true, que es el lado seguro (errores ocultos).
 */
function esProduccion(): bool
{
    require_once __DIR__ . '/env.php';

    $declarado = env('APP_ENV');

    if (is_string($declarado) && $declarado !== '') {
        return !in_array(strtolower($declarado), ['local', 'dev', 'development'], true);
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        // CLI: los scripts de consola son de desarrollo.
        return PHP_SAPI !== 'cli';
    }

    foreach (['127.0.0.1', 'localhost', '::1', '.local', '.test'] as $marca) {
        if (str_contains($host, $marca)) {
            return false;
        }
    }

    return true;
}

/**
 * Devuelve la firma escaneada como data URI para incrustarla en HTML.
 *
 * Se lee desde disco en vez de enlazarla por URL porque la carpeta pdf/
 * esta bloqueada en .htaccess: una firma descargable permite falsificar
 * documentos. Devuelve null si el archivo no existe.
 */
function firmaDataUri(?string $rutaRelativa): ?string
{
    if ($rutaRelativa === null || $rutaRelativa === '') {
        return null;
    }

    $ruta = realpath(__DIR__ . '/../' . ltrim($rutaRelativa, '/'));

    if ($ruta === false || !is_file($ruta)) {
        return null;
    }

    $tipo = image_type_to_mime_type(exif_imagetype($ruta) ?: IMAGETYPE_PNG);

    return 'data:' . $tipo . ';base64,' . base64_encode((string) file_get_contents($ruta));
}
