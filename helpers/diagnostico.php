<?php
/**
 * Comprobaciones de estado del sistema.
 *
 * Sirve para saber, desde el navegador, si el despliegue quedo bien:
 * si conecta la base, si estan las tablas, si estan los archivos de
 * configuracion y si se puede escribir en disco.
 */

require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

const TABLAS_ESPERADAS = ['clientes', 'cotizaciones', 'cotizacion_items', 'usuarios'];

/**
 * Traduce el error de MySQL a algo que diga que hacer.
 *
 * El mensaje crudo del driver incluye usuario, host y base, asi que en
 * produccion solo se devuelve la explicacion; el detalle va al log.
 */
function explicarErrorBd(Throwable $e): string
{
    // Lo que no venga del driver lo escribimos nosotros (por ejemplo, que
    // falta el .env): ese texto es seguro de mostrar y es justo el que
    // hace falta leer para arreglarlo.
    if (!$e instanceof PDOException) {
        return $e->getMessage();
    }

    $codigo = $e->errorInfo[1] ?? 0;
    $texto  = $e->getMessage();

    // El SQLSTATE no siempre trae errorInfo (p. ej. si falla al resolver
    // el host), asi que tambien se mira el texto.
    if ($codigo === 1045 || str_contains($texto, 'Access denied for user')) {
        return 'Usuario o contraseña incorrectos. Revisa DB_USER y DB_PASS '
             . 'en el .env, y que el usuario esté asignado a la base con '
             . 'todos los privilegios. Si la clave lleva "=" o "#", ponla '
             . 'entre comillas.';
    }

    if ($codigo === 1049 || str_contains($texto, 'Unknown database')) {
        return 'La base de datos no existe. Revisa DB_NAME en el .env; en '
             . 'cPanel lleva el prefijo de la cuenta, por ejemplo '
             . 'enlixpe_cotizacion.';
    }

    if ($codigo === 1044) {
        return 'El usuario existe pero no tiene permisos sobre esa base. '
             . 'En cPanel: Bases de datos MySQL → Añadir usuario a la base '
             . 'de datos → ALL PRIVILEGES.';
    }

    if ($codigo === 2002 || str_contains($texto, 'No such host') || str_contains($texto, 'Connection refused')) {
        return 'No se pudo contactar al servidor MySQL. Revisa DB_HOST y '
             . 'DB_PORT en el .env (en cPanel casi siempre es localhost '
             . 'y 3306).';
    }

    return esProduccion()
        ? 'Error de conexión. El detalle quedó en el log de errores del servidor.'
        : $texto;
}

/**
 * Corre todas las comprobaciones.
 *
 * @return array Lista de bloques con sus verificaciones.
 */
function verificarSistema(): array
{
    $bloques = [];

    // ---------------------------------------------------------------
    // Entorno
    // ---------------------------------------------------------------
    $extensiones = [];
    foreach (['pdo_mysql', 'mbstring', 'dom', 'gd', 'iconv', 'fileinfo'] as $ext) {
        $extensiones[] = [
            'ok'      => extension_loaded($ext),
            'nombre'  => "Extensión {$ext}",
            'detalle' => extension_loaded($ext)
                ? 'disponible'
                : ($ext === 'gd'
                    ? 'falta: el PDF no podrá incrustar la firma'
                    : 'falta: pídela al hosting'),
        ];
    }

    $bloques[] = [
        'titulo'        => 'Entorno',
        'verificaciones' => array_merge([
            [
                'ok'      => PHP_VERSION_ID >= 80000,
                'nombre'  => 'Versión de PHP',
                'detalle' => PHP_VERSION . (PHP_VERSION_ID >= 80000 ? '' : ' — se requiere 8.0 o superior'),
            ],
            [
                'ok'      => true,
                'nombre'  => 'Entorno detectado',
                'detalle' => esProduccion() ? 'producción' : 'desarrollo',
            ],
        ], $extensiones),
    ];

    // ---------------------------------------------------------------
    // Archivos de configuracion
    // ---------------------------------------------------------------
    $raiz = dirname(__DIR__);

    $bloques[] = [
        'titulo' => 'Archivos',
        'verificaciones' => [
            [
                'ok'      => hayEnv() || !esProduccion(),
                'nombre'  => 'Archivo .env',
                'detalle' => hayEnv()
                    ? 'presente'
                    : (esProduccion()
                        ? 'falta: cp .env.example .env y completarlo'
                        : 'no hace falta en desarrollo'),
            ],
            [
                'ok'      => is_file($raiz . '/config/empresa.php'),
                'nombre'  => 'config/empresa.php',
                'detalle' => is_file($raiz . '/config/empresa.php')
                    ? 'presente'
                    : 'falta: copiar de config/empresa.example.php',
            ],
            [
                'ok'      => is_file($raiz . '/pdf/firma-transparente.png'),
                // No impide operar: el PDF sale igual, solo que sin firma
                // sobre la linea. Por eso no tumba el estado general.
                'critico' => false,
                'nombre'  => 'Firma escaneada',
                'detalle' => is_file($raiz . '/pdf/firma-transparente.png')
                    ? 'presente'
                    : 'falta: el PDF saldrá sin firma sobre la línea',
            ],
            [
                'ok'      => is_dir($raiz . '/vendor'),
                'nombre'  => 'Dependencias (vendor/)',
                'detalle' => is_dir($raiz . '/vendor')
                    ? 'instaladas'
                    : 'faltan: correr composer install',
            ],
            [
                'ok'      => is_dir($raiz . '/storage') && is_writable($raiz . '/storage'),
                'nombre'  => 'Carpeta storage/ escribible',
                'detalle' => is_dir($raiz . '/storage')
                    ? (is_writable($raiz . '/storage') ? 'con permiso de escritura' : 'sin permiso: chmod 755 storage')
                    : 'no existe: mkdir storage',
            ],
        ],
    ];

    // ---------------------------------------------------------------
    // Base de datos
    // ---------------------------------------------------------------
    $verifBd = [];
    $conectada = false;
    $pdo = null;

    try {
        $pdo = db();
        $conectada = true;

        $verifBd[] = [
            'ok'      => true,
            'nombre'  => 'Conexión a la base de datos',
            'detalle' => 'conectada — MySQL/MariaDB ' . $pdo->query('SELECT VERSION()')->fetchColumn(),
        ];
    } catch (Throwable $e) {
        error_log('Diagnostico: fallo la conexion a la base: ' . $e->getMessage());

        $verifBd[] = [
            'ok'      => false,
            'nombre'  => 'Conexión a la base de datos',
            'detalle' => explicarErrorBd($e),
        ];
    }

    if ($conectada) {
        $existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach (TABLAS_ESPERADAS as $tabla) {
            $hay = in_array($tabla, $existentes, true);
            $filas = null;

            if ($hay) {
                $filas = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
            }

            $verifBd[] = [
                'ok'      => $hay,
                'nombre'  => "Tabla {$tabla}",
                'detalle' => $hay
                    ? $filas . ' ' . ($filas === 1 ? 'registro' : 'registros')
                    : 'no existe: importar database/schema.sql',
            ];
        }

        // Sin usuarios y con el login activo, nadie podria entrar.
        if (in_array('usuarios', $existentes, true)) {
            $cuantos = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

            $verifBd[] = [
                'ok'      => $cuantos > 0 || accesoLibre(),
                'nombre'  => 'Usuarios dados de alta',
                'detalle' => $cuantos > 0
                    ? $cuantos . ' ' . ($cuantos === 1 ? 'usuario' : 'usuarios')
                    : (accesoLibre()
                        ? 'ninguno, pero el modo prueba permite entrar sin login'
                        : 'ninguno: crear uno con php tools/crear_usuario.php'),
            ];
        }
    }

    $bloques[] = ['titulo' => 'Base de datos', 'verificaciones' => $verifBd];

    return $bloques;
}

/**
 * ¿Esta operativo el sistema?
 *
 * Solo miran las comprobaciones criticas. Una verificacion marcada con
 * 'critico' => false es un aviso: conviene resolverla, pero la aplicacion
 * funciona igual. Sin esta distincion, un detalle menor como la firma
 * faltante devolvia un 503 y cualquier monitor externo daria el sitio por
 * caido estando sano.
 */
function sistemaCorrecto(array $bloques): bool
{
    foreach ($bloques as $bloque) {
        foreach ($bloque['verificaciones'] as $v) {
            if (!$v['ok'] && ($v['critico'] ?? true)) {
                return false;
            }
        }
    }

    return true;
}

/** Cuantas comprobaciones no criticas quedaron sin pasar. */
function contarAvisos(array $bloques): int
{
    $avisos = 0;

    foreach ($bloques as $bloque) {
        foreach ($bloque['verificaciones'] as $v) {
            if (!$v['ok'] && ($v['critico'] ?? true) === false) {
                $avisos++;
            }
        }
    }

    return $avisos;
}

/** Clasifica una verificacion: 'si' | 'aviso' | 'no'. */
function estadoVerificacion(array $v): string
{
    if ($v['ok']) {
        return 'si';
    }

    return ($v['critico'] ?? true) ? 'no' : 'aviso';
}
