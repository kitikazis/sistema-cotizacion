<?php
/**
 * Sesion, autenticacion y CSRF.
 *
 * La herramienta es interna: no hay registro publico, los usuarios se dan
 * de alta por consola con tools/crear_usuario.php.
 */

require_once __DIR__ . '/funciones.php';

/**
 * Arranca la sesion con cookies endurecidas.
 *
 * httponly  la cookie no es visible desde JavaScript
 * samesite  no viaja en peticiones cruzadas, primera barrera contra CSRF
 * secure    solo por HTTPS (en local seria contraproducente: no hay TLS)
 */
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => esProduccion(),
    ]);

    session_name('COTIZADOR');
    session_start();
}

/** Datos del usuario logueado, o null. */
function usuarioActual(): ?array
{
    iniciarSesion();

    return $_SESSION['usuario'] ?? null;
}

function estaAutenticado(): bool
{
    return usuarioActual() !== null;
}

/**
 * Deja al usuario dentro. Regenera el id de sesion para que un id
 * capturado antes del login no sirva despues (fijacion de sesion).
 */
function autenticar(array $usuario): void
{
    iniciarSesion();
    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id'      => (int) $usuario['id'],
        'nombre'  => $usuario['nombre'],
        'usuario' => $usuario['usuario'],
    ];
}

/** Cierra la sesion por completo. */
function cerrarSesion(): void
{
    iniciarSesion();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/**
 * ¿Esta abierta la puerta sin login?
 *
 * Se controla con 'acceso_libre' en config/app.php. Es un modo de prueba:
 * mientras este activo la aplicacion muestra un aviso rojo permanente.
 */
function accesoLibre(): bool
{
    static $valor = null;

    if ($valor === null) {
        $archivo = __DIR__ . '/../config/app.php';
        $config  = is_file($archivo) ? require $archivo : [];

        // Ante la ausencia del archivo se exige login, que es el lado seguro.
        $valor = !empty($config['acceso_libre']);
    }

    return $valor;
}

/**
 * Corta la ejecucion si no hay sesion, recordando a donde queria ir.
 */
function requiereAutenticacion(): void
{
    if (accesoLibre() || estaAutenticado()) {
        return;
    }

    iniciarSesion();
    $_SESSION['destino'] = $_SERVER['REQUEST_URI'] ?? null;

    redirigir(url('login'));
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------

/** Token de la sesion, creado la primera vez que se pide. */
function tokenCsrf(): string
{
    iniciarSesion();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/** Campo oculto listo para pegar en cualquier formulario POST. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

/**
 * Valida el token del POST. Corta con 419 si no cuadra.
 *
 * hash_equals compara en tiempo constante: evita filtrar el token a
 * base de medir cuanto tarda la comparacion.
 */
function validarCsrf(): void
{
    iniciarSesion();

    $enviado  = (string) ($_POST['csrf'] ?? '');
    $esperado = (string) ($_SESSION['csrf'] ?? '');

    if ($esperado === '' || !hash_equals($esperado, $enviado)) {
        http_response_code(419);
        exit('La sesión expiró o el formulario no es válido. Vuelve a cargar la página.');
    }
}
