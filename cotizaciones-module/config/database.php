<?php
/**
 * Conexion PDO a MySQL/MariaDB.
 *
 * XAMPP en esta maquina tiene MariaDB en el puerto 8081 (el 3306 lo ocupa
 * un MySQL 8.0 standalone), por eso el puerto no es el default.
 */

const DB_HOST    = '127.0.0.1';
const DB_PORT    = 8081;
const DB_NAME    = 'enlix_cotizaciones';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Devuelve una unica instancia de PDO por request.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Necesario para que los DECIMAL lleguen como string y no pierdan
            // precision al convertirse a float por el driver.
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('No se pudo conectar a la base de datos: ' . $e->getMessage());
    }

    return $pdo;
}
