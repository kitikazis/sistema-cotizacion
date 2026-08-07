<?php
/**
 * Conexion PDO a MySQL/MariaDB.
 *
 * Las credenciales cambian segun donde corra:
 *   local      XAMPP, MariaDB en el puerto 8081 (el 3306 lo ocupa un
 *              MySQL 8.0 standalone instalado aparte).
 *   produccion cPanel, MySQL local en 3306. El usuario y la base llevan
 *              el prefijo de la cuenta cPanel, p.ej. enlixpe_cotizador.
 *
 * En produccion las credenciales se leen de config/credenciales.php, que
 * NO va al repositorio. Si falta, se corta con un mensaje claro en vez de
 * intentar conectar con datos de ejemplo.
 */

require_once __DIR__ . '/../helpers/funciones.php';

/**
 * Devuelve los datos de conexion del entorno actual.
 */
function configBaseDatos(): array
{
    if (!esProduccion()) {
        return [
            'host'   => '127.0.0.1',
            'puerto' => 8081,
            'base'   => 'enlix_cotizaciones',
            'usuario'=> 'root',
            'clave'  => '',
        ];
    }

    $archivo = __DIR__ . '/credenciales.php';

    if (!is_file($archivo)) {
        // Se lanza en vez de cortar: asi la pantalla de estado puede
        // capturarlo y explicarlo, en lugar de morir con un texto pelado.
        throw new RuntimeException(
            'Falta config/credenciales.php con los datos de la base de datos '
            . 'de produccion. Copiar config/credenciales.example.php y completarlo.'
        );
    }

    return require $archivo;
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
