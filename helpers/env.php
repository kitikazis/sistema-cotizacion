<?php
/**
 * Lector de .env.
 *
 * Sin dependencias: el modulo corre en hosting compartido y no conviene
 * arrastrar un paquete para leer un archivo de texto.
 *
 * Formato admitido:
 *
 *   # comentario
 *   DB_HOST=localhost
 *   DB_PASS="clave con espacios o = adentro"
 *   DB_PASS='comillas simples tambien'
 *
 * Los valores NO se meten en putenv() ni en $_ENV a proposito: ahi
 * quedarian visibles en un phpinfo() o en un volcado de variables.
 */

/**
 * Carga y cachea el .env de la raiz del proyecto.
 */
function cargarEnv(): array
{
    static $valores = null;

    if ($valores !== null) {
        return $valores;
    }

    $valores = [];
    $archivo = dirname(__DIR__) . '/.env';

    if (!is_file($archivo) || !is_readable($archivo)) {
        return $valores;
    }

    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        // Solo el PRIMER "=" separa: una contrasena puede tener "=" adentro.
        $pos = strpos($linea, '=');

        if ($pos === false) {
            continue;
        }

        $clave = trim(substr($linea, 0, $pos));
        $valor = trim(substr($linea, $pos + 1));

        // Quitar comillas envolventes, si las hay.
        $largo = strlen($valor);
        if ($largo >= 2) {
            $primero = $valor[0];
            $ultimo  = $valor[$largo - 1];

            if (($primero === '"' && $ultimo === '"') || ($primero === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        $valores[$clave] = $valor;
    }

    return $valores;
}

/**
 * Devuelve una variable del .env.
 *
 * @param mixed $default Lo que se devuelve si no esta definida.
 * @return mixed
 */
function env(string $clave, $default = null)
{
    $valores = cargarEnv();

    if (!array_key_exists($clave, $valores)) {
        return $default;
    }

    $valor = $valores[$clave];

    // Conveniencias para no andar comparando cadenas en el resto del codigo.
    return match (strtolower($valor)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        ''                 => $default,
        default            => $valor,
    };
}

/** ¿Existe el archivo .env? */
function hayEnv(): bool
{
    return is_file(dirname(__DIR__) . '/.env');
}
