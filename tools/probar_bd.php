<?php
/**
 * Diagnostico de la conexion a la base, por consola.
 *
 *   php tools/probar_bd.php
 *
 * Muestra lo que la aplicacion leyo del .env y el error crudo de MySQL.
 * Solo por consola: en la web esos datos no deben verse. La contrasena
 * nunca se imprime, solo su longitud y si trae caracteres delicados.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por consola.');
}

require_once __DIR__ . '/../helpers/env.php';
require_once __DIR__ . '/../config/database.php';

echo '=== Archivo .env ===' . PHP_EOL;

$ruta = dirname(__DIR__) . '/.env';

if (!is_file($ruta)) {
    echo "  NO EXISTE en {$ruta}" . PHP_EOL;
    echo '  Solucion: cp .env.example .env && nano .env' . PHP_EOL;
    exit(1);
}

printf("  ruta    : %s%s", $ruta, PHP_EOL);
printf("  permisos: %s%s", substr(sprintf('%o', fileperms($ruta)), -4), PHP_EOL);

// Fin de linea: un .env guardado en Windows puede dejar \r al final de
// cada valor y hacer fallar la clave sin que se note a simple vista.
$crudo = (string) file_get_contents($ruta);
printf("  saltos  : %s%s", str_contains($crudo, "\r\n") ? 'CRLF (Windows) - puede romper valores' : 'LF (correcto)', PHP_EOL);

echo PHP_EOL . '=== Valores leidos ===' . PHP_EOL;

foreach (['APP_ENV', 'APP_ACCESO_LIBRE', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $clave) {
    $valor = env($clave);
    printf("  %-18s %s%s", $clave, var_export($valor, true), PHP_EOL);
}

$clave = (string) env('DB_PASS', '');

printf("  %-18s %d caracteres%s", 'DB_PASS', strlen($clave), PHP_EOL);

if ($clave === '') {
    echo '     AVISO: la clave llego vacia. Revisa que DB_PASS tenga valor.' . PHP_EOL;
}
if ($clave !== trim($clave)) {
    echo '     AVISO: tiene espacios o saltos al inicio o al final.' . PHP_EOL;
}
if (str_contains($clave, '"') || str_contains($clave, "'")) {
    echo '     AVISO: contiene comillas. Si envolviste el valor en comillas,' . PHP_EOL;
    echo '            quedaron duplicadas.' . PHP_EOL;
}
foreach (['=' => 'un igual', '#' => 'una almohadilla'] as $caracter => $nombre) {
    if (str_contains($clave, $caracter)) {
        printf("     nota: contiene %s, conviene envolverla en comillas dobles.%s", $nombre, PHP_EOL);
    }
}

echo PHP_EOL . '=== Conexion ===' . PHP_EOL;

try {
    $pdo = db();
    echo '  CONECTADA' . PHP_EOL;
    printf("  servidor: %s%s", $pdo->query('SELECT VERSION()')->fetchColumn(), PHP_EOL);
    printf("  usuario : %s%s", $pdo->query('SELECT CURRENT_USER()')->fetchColumn(), PHP_EOL);
    printf("  base    : %s%s", $pdo->query('SELECT DATABASE()')->fetchColumn(), PHP_EOL);

    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    echo PHP_EOL . '=== Tablas ===' . PHP_EOL;

    if ($tablas === []) {
        echo '  ninguna. Falta importar database/schema.sql' . PHP_EOL;
    } else {
        foreach ($tablas as $tabla) {
            printf("  %-20s %d registros%s", $tabla, $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn(), PHP_EOL);
        }
    }

    exit(0);
} catch (Throwable $e) {
    echo '  FALLO' . PHP_EOL;
    printf("  %s%s", $e->getMessage(), PHP_EOL);

    echo PHP_EOL . '=== Que revisar ===' . PHP_EOL;

    if (str_contains($e->getMessage(), 'Access denied')) {
        echo '  El usuario y la clave no coinciden, o el usuario no esta' . PHP_EOL;
        echo '  asignado a la base. Comprueba en cPanel:' . PHP_EOL;
        echo '    Bases de datos MySQL > Usuarios actuales' . PHP_EOL;
        echo '      el nombre debe ser exactamente el de DB_USER' . PHP_EOL;
        echo '    Bases de datos MySQL > Anadir usuario a la base de datos' . PHP_EOL;
        echo '      ese usuario + esa base, con ALL PRIVILEGES' . PHP_EOL;
        echo PHP_EOL;
        echo '  Para probar la clave sin pasar por la aplicacion:' . PHP_EOL;
        printf("    mysql -u %s -p -e \"SHOW GRANTS;\"%s", env('DB_USER', '?'), PHP_EOL);
    } elseif (str_contains($e->getMessage(), 'Unknown database')) {
        echo '  La base no existe con ese nombre. En cPanel lleva el prefijo' . PHP_EOL;
        echo '  de la cuenta. Para ver las que existen:' . PHP_EOL;
        printf("    mysql -u %s -p -e \"SHOW DATABASES;\"%s", env('DB_USER', '?'), PHP_EOL);
    }

    exit(1);
}
