<?php
/**
 * Alta de usuarios por consola.
 *
 *   php tools/crear_usuario.php
 *   php tools/crear_usuario.php "Crhistian Garcia" cgarcia
 *
 * No existe registro por web a proposito: es una herramienta interna y
 * cualquiera que llegue a la URL no debe poder crearse una cuenta.
 *
 * La contrasena se pide por consola y no se toma de los argumentos: los
 * argumentos quedan en el historial del shell y en la lista de procesos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por consola.');
}

require_once __DIR__ . '/../models/Usuario.php';

/** Lee una linea de la consola, ocultando el eco si se puede. */
function preguntar(string $texto, bool $oculto = false): string
{
    echo $texto;

    if ($oculto && DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
        // En Unix se apaga el eco del terminal.
        shell_exec('stty -echo 2>/dev/null');
        $valor = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;

        return $valor;
    }

    // En Windows no hay forma portable de ocultarlo; se avisa.
    return trim((string) fgets(STDIN));
}

echo "=== Alta de usuario del cotizador ===" . PHP_EOL;

try {
    $nombre  = $argv[1] ?? preguntar('Nombre completo: ');
    $usuario = $argv[2] ?? preguntar('Usuario (para entrar): ');

    if (DIRECTORY_SEPARATOR === '\\') {
        echo '(en Windows la contraseña se verá al escribirla)' . PHP_EOL;
    }

    $clave  = preguntar('Contraseña (mínimo 10 caracteres): ', true);
    $repite = preguntar('Repetir contraseña: ', true);

    if ($clave !== $repite) {
        fwrite(STDERR, "Las contraseñas no coinciden." . PHP_EOL);
        exit(1);
    }

    $id = Usuario::crear($nombre, $usuario, $clave);

    echo PHP_EOL . "Usuario creado (id {$id}). Ya puedes entrar en el panel." . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
