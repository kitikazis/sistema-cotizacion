<?php
/**
 * Conexion PDO a MySQL/MariaDB.
 *
 * Los datos salen del .env de la raiz (ver .env.example). Ese archivo no
 * se versiona porque lleva la clave.
 *
 * En desarrollo, si no hay .env, se usan los valores de XAMPP de esta
 * maquina: MariaDB escucha en el 8081 porque el 3306 lo ocupa un MySQL
 * 8.0 standalone instalado aparte.
 */

require_once __DIR__ . '/../helpers/env.php';
require_once __DIR__ . '/../helpers/funciones.php';

/**
 * Devuelve los datos de conexion del entorno actual.
 */
function configBaseDatos(): array
{
    if (hayEnv()) {
        $base = env('DB_NAME');

        if ($base === null) {
            throw new RuntimeException(
                'El archivo .env existe pero no define DB_NAME. '
                . 'Compáralo con .env.example y completa las variables DB_*.'
            );
        }

        return [
            'host'    => (string) env('DB_HOST', 'localhost'),
            'puerto'  => (int) env('DB_PORT', 3306),
            'base'    => (string) $base,
            'usuario' => (string) env('DB_USER', ''),
            'clave'   => (string) env('DB_PASS', ''),
        ];
    }

    if (esProduccion()) {
        throw new RuntimeException(
            'Falta el archivo .env con los datos de la base de datos. '
            . 'Copiar .env.example a .env y completarlo.'
        );
    }

    // Desarrollo sin .env: XAMPP de esta maquina.
    return [
        'host'    => '127.0.0.1',
        'puerto'  => 8081,
        'base'    => 'enlix_cotizaciones',
        'usuario' => 'root',
        'clave'   => '',
    ];
}

/**
 * Devuelve una unica instancia de PDO por request.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = configBaseDatos();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['puerto'],
        $cfg['base']
    );

    // Se deja propagar la PDOException: index.php la captura y muestra una
    // pantalla util con el enlace al diagnostico.
    $pdo = new PDO($dsn, $cfg['usuario'], $cfg['clave'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Necesario para que los DECIMAL lleguen como string y no pierdan
        // precision al convertirse a float por el driver.
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
