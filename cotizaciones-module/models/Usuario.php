<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Usuarios del panel. Sin registro publico: se dan de alta por consola.
 */
class Usuario
{
    /** Coste de bcrypt. 12 es un equilibrio razonable en hosting compartido. */
    private const COSTE = 12;

    public static function buscarPorUsuario(string $usuario): ?array
    {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE usuario = ? AND activo = 1 LIMIT 1');
        $stmt->execute([strtolower(trim($usuario))]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Verifica credenciales. Devuelve el usuario o null.
     *
     * Cuando el usuario no existe igual se ejecuta un password_verify
     * contra un hash de descarte: sin eso, un login inexistente responde
     * mucho mas rapido y permite averiguar que usuarios existen.
     */
    public static function verificar(string $usuario, string $clave): ?array
    {
        $fila = self::buscarPorUsuario($usuario);

        if ($fila === null) {
            password_verify($clave, '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexistente00');

            return null;
        }

        if (!password_verify($clave, $fila['clave_hash'])) {
            return null;
        }

        // Si el algoritmo por defecto de PHP cambio, se re-hashea al vuelo.
        if (password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT, ['cost' => self::COSTE])) {
            self::cambiarClave((int) $fila['id'], $clave);
        }

        return $fila;
    }

    public static function crear(string $nombre, string $usuario, string $clave): int
    {
        $usuario = strtolower(trim($usuario));

        if ($usuario === '' || $nombre === '') {
            throw new InvalidArgumentException('Nombre y usuario son obligatorios.');
        }

        if (strlen($clave) < 10) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 10 caracteres.');
        }

        if (self::buscarPorUsuario($usuario) !== null) {
            throw new InvalidArgumentException("El usuario \"{$usuario}\" ya existe.");
        }

        $stmt = db()->prepare(
            'INSERT INTO usuarios (nombre, usuario, clave_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            trim($nombre),
            $usuario,
            password_hash($clave, PASSWORD_DEFAULT, ['cost' => self::COSTE]),
        ]);

        return (int) db()->lastInsertId();
    }

    public static function cambiarClave(int $id, string $clave): void
    {
        $stmt = db()->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?');
        $stmt->execute([
            password_hash($clave, PASSWORD_DEFAULT, ['cost' => self::COSTE]),
            $id,
        ]);
    }

    public static function registrarAcceso(int $id): void
    {
        $stmt = db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function contar(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }
}
