<?php
/**
 * Ejecutor de migraciones.
 *
 *   php tools/migrar.php            aplica lo que falte
 *   php tools/migrar.php --estado   solo informa, no toca nada
 *
 * Crea las tablas leyendo los .sql de database/migraciones en orden y
 * anota cada una en la tabla `migraciones`, de modo que volver a correrlo
 * no repite trabajo. No borra ni modifica datos existentes: cada archivo
 * usa CREATE TABLE IF NOT EXISTS.
 *
 * Solo por consola: crear tablas desde una URL seria un regalo para
 * cualquiera que la encuentre.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por consola.');
}

require_once __DIR__ . '/../config/database.php';

const CARPETA_MIGRACIONES = __DIR__ . '/../database/migraciones';

$soloEstado = in_array('--estado', $argv, true);

echo '=== Migraciones ===' . PHP_EOL;

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo conectar a la base de datos.' . PHP_EOL);
    fwrite(STDERR, '  ' . $e->getMessage() . PHP_EOL . PHP_EOL);
    fwrite(STDERR, 'Revisa el .env con: php tools/probar_bd.php' . PHP_EOL);
    exit(1);
}

// La bitacora de lo ya aplicado. Se crea sola la primera vez.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migraciones (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        archivo    VARCHAR(180) NOT NULL,
        aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migraciones_archivo (archivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$aplicadas = $pdo->query('SELECT archivo FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);

$archivos = glob(CARPETA_MIGRACIONES . '/*.sql') ?: [];
sort($archivos, SORT_STRING);   // el prefijo numerico define el orden

if ($archivos === []) {
    fwrite(STDERR, 'No se encontraron migraciones en database/migraciones' . PHP_EOL);
    exit(1);
}

$pendientes = 0;
$errores    = 0;

foreach ($archivos as $ruta) {
    $nombre = basename($ruta);

    if (in_array($nombre, $aplicadas, true)) {
        printf("  ya aplicada   %s%s", $nombre, PHP_EOL);
        continue;
    }

    $pendientes++;

    if ($soloEstado) {
        printf("  PENDIENTE     %s%s", $nombre, PHP_EOL);
        continue;
    }

    $sql = trim((string) file_get_contents($ruta));

    if ($sql === '') {
        printf("  vacia, salto  %s%s", $nombre, PHP_EOL);
        continue;
    }

    try {
        // Cada archivo contiene UNA sola sentencia: asi se evita partir por
        // ";" y romper los COMMENT que pueden contener ese caracter.
        $pdo->exec($sql);

        $stmt = $pdo->prepare('INSERT INTO migraciones (archivo) VALUES (?)');
        $stmt->execute([$nombre]);

        printf("  APLICADA      %s%s", $nombre, PHP_EOL);
    } catch (Throwable $e) {
        $errores++;
        printf("  FALLO         %s%s", $nombre, PHP_EOL);
        printf("                %s%s", $e->getMessage(), PHP_EOL);
        break;   // no seguir: las siguientes suelen depender de esta
    }
}

echo PHP_EOL;

if ($soloEstado) {
    printf('%d pendientes de %d.%s', $pendientes, count($archivos), PHP_EOL);
    exit(0);
}

if ($errores > 0) {
    fwrite(STDERR, 'Se detuvo por un error. Nada mas se aplico.' . PHP_EOL);
    exit(1);
}

if ($pendientes === 0) {
    echo 'La base ya estaba al dia.' . PHP_EOL;
} else {
    printf('%d migracion(es) aplicada(s).%s', $pendientes, PHP_EOL);
}

// Resumen de como quedo
echo PHP_EOL . '=== Tablas ===' . PHP_EOL;

foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
    printf("  %-20s %d registros%s", $tabla, $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn(), PHP_EOL);
}

echo PHP_EOL . 'Listo. Recarga la pantalla de estado: ?accion=estado' . PHP_EOL;
exit(0);
