<?php

require_once __DIR__ . '/../config/database.php';

/**
 * Maestro de clientes.
 *
 * Una cotizacion no guarda el nombre del cliente: apunta a una fila de aqui.
 * Asi corregir un RUC mal escrito arregla todas sus cotizaciones de una vez.
 */
class Cliente
{
    /**
     * Busca por RUC y, si no hay, por razon social exacta.
     * Devuelve null si no existe.
     */
    public static function buscarPorRucORazon(?string $ruc, string $razonSocial): ?array
    {
        $ruc = self::limpiar($ruc);

        if ($ruc !== null) {
            $stmt = db()->prepare('SELECT * FROM clientes WHERE ruc = ? LIMIT 1');
            $stmt->execute([$ruc]);

            if ($fila = $stmt->fetch()) {
                return $fila;
            }
        }

        $stmt = db()->prepare('SELECT * FROM clientes WHERE razon_social = ? AND ruc IS NULL LIMIT 1');
        $stmt->execute([trim($razonSocial)]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Devuelve el id del cliente, creandolo o actualizandolo segun haga falta.
     *
     * Se usa al guardar una cotizacion: el vendedor escribe el nombre y el
     * RUC en el formulario y aqui se decide si es uno nuevo o ya existia.
     */
    public static function obtenerOCrear(array $datos): int
    {
        $razonSocial = trim((string) ($datos['empresa'] ?? ''));
        $ruc         = self::limpiar($datos['ruc'] ?? null);
        $direccion   = self::limpiar($datos['direccion'] ?? null);

        if ($razonSocial === '') {
            throw new InvalidArgumentException('La razon social del cliente es obligatoria.');
        }

        $existente = self::buscarPorRucORazon($ruc, $razonSocial);

        if ($existente !== null) {
            // Se refrescan los datos por si el vendedor corrigio algo.
            $stmt = db()->prepare(
                'UPDATE clientes SET razon_social = ?, direccion = COALESCE(?, direccion) WHERE id = ?'
            );
            $stmt->execute([$razonSocial, $direccion, $existente['id']]);

            return (int) $existente['id'];
        }

        $stmt = db()->prepare(
            'INSERT INTO clientes (razon_social, ruc, direccion) VALUES (?, ?, ?)'
        );
        $stmt->execute([$razonSocial, $ruc, $direccion]);

        return (int) db()->lastInsertId();
    }

    /** Todos los clientes, para el datalist del formulario. */
    public static function listar(): array
    {
        return db()->query(
            'SELECT id, razon_social, ruc, direccion FROM clientes ORDER BY razon_social'
        )->fetchAll();
    }

    private static function limpiar($valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
