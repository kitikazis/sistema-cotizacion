<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/funciones.php';

/**
 * Login y logout.
 */
class AuthController
{
    /** Pantalla de acceso. */
    public function login(): void
    {
        if (estaAutenticado()) {
            redirigir(url());
        }

        $titulo = 'Acceder';
        $error  = $_GET['error'] ?? null;
        $sinUsuarios = Usuario::contar() === 0;

        require __DIR__ . '/../views/auth/login.php';
    }

    /** Procesa el formulario de acceso. */
    public function entrar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirigir(url('login'));
        }

        validarCsrf();

        $usuario = (string) ($_POST['usuario'] ?? '');
        $clave   = (string) ($_POST['clave'] ?? '');

        $fila = Usuario::verificar($usuario, $clave);

        if ($fila === null) {
            // Pequena espera: encarece la fuerza bruta sin molestar a nadie.
            usleep(400000);
            error_log('Login fallido para "' . $usuario . '" desde ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));

            // Mensaje generico: no se revela si el usuario existe.
            redirigir(url('login', ['error' => 'Usuario o contraseña incorrectos.']));
        }

        autenticar($fila);
        Usuario::registrarAcceso((int) $fila['id']);

        // Si intento entrar a una URL concreta antes del login, se respeta.
        $destino = $_SESSION['destino'] ?? null;
        unset($_SESSION['destino']);

        redirigir($destino ?: url());
    }

    /** Cierra sesion. */
    public function salir(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            validarCsrf();
            cerrarSesion();
        }

        redirigir(url('login'));
    }
}
