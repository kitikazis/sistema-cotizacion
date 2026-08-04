<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/PricingCalculator.php';

/**
 * Acceso a datos de cotizaciones y sus items.
 *
 * Los montos se recalculan SIEMPRE en el servidor con PricingCalculator
 * antes de guardar: lo que manda el navegador es solo entrada, nunca
 * se confia en los totales que viajan en el POST.
 */
class Cotizacion
{
    /** Columnas de item que se persisten, en orden. */
    private const COLUMNAS_ITEM = [
        'linea', 'codigo', 'marca', 'descripcion', 'cantidad',
        'precio', 'licencia_so', 'delivery', 'embalaje', 'envio',
        'aplica_detraccion', 'aplica_retencion', 'porcentaje_ganancia',
        'ir', 'igv', 'detraccion', 'ganancia', 'subtotal', 'retencion',
        'total_unitario', 'total_linea', 'precio_cliente_unitario',
    ];

    /**
     * Lista cotizaciones con el conteo de items, mas recientes primero.
     */
    public static function listar(): array
    {
        $sql = 'SELECT c.*, COUNT(i.id) AS items
                FROM cotizaciones c
                LEFT JOIN cotizacion_items i ON i.cotizacion_id = c.id
                GROUP BY c.id
                ORDER BY c.id DESC';

        return db()->query($sql)->fetchAll();
    }

    /**
     * Devuelve una cotizacion con sus items, o null si no existe.
     */
    public static function obtener(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM cotizaciones WHERE id = ?');
        $stmt->execute([$id]);
        $cotizacion = $stmt->fetch();

        if (!$cotizacion) {
            return null;
        }

        $stmt = db()->prepare(
            'SELECT * FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY linea ASC'
        );
        $stmt->execute([$id]);
        $cotizacion['items'] = $stmt->fetchAll();

        return $cotizacion;
    }

    /**
     * Siguiente correlativo, con relleno a 4 digitos.
     * El Excel arranca en 0145, asi que esa es la base.
     */
    public static function siguienteNumero(): string
    {
        $max = db()->query(
            'SELECT MAX(CAST(numero AS UNSIGNED)) FROM cotizaciones'
        )->fetchColumn();

        $siguiente = max((int) $max, 144) + 1;

        return str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Crea una cotizacion con sus items dentro de una transaccion.
     *
     * @param array $datos Cabecera cruda del formulario.
     * @param array $items Items crudos del formulario.
     * @return int Id de la cotizacion creada.
     */
    public static function crear(array $datos, array $items): int
    {
        if ($items === []) {
            throw new InvalidArgumentException('La cotizacion necesita al menos un item.');
        }

        // Recalculo autoritativo en servidor.
        $resultado = PricingCalculator::calcularCotizacion($items);
        $totales   = $resultado['totales'];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $sql = 'INSERT INTO cotizaciones
                        (numero, empresa, ruc, direccion, fecha_emision, validez_dias,
                         forma_pago, credito_dias, tiempo_entrega_dias, moneda,
                         observaciones, condiciones,
                         total_general, cliente_subtotal, cliente_igv, cliente_total, estado)
                    VALUES
                        (:numero, :empresa, :ruc, :direccion, :fecha_emision, :validez_dias,
                         :forma_pago, :credito_dias, :tiempo_entrega_dias, :moneda,
                         :observaciones, :condiciones,
                         :total_general, :cliente_subtotal, :cliente_igv, :cliente_total, :estado)';

            $formaPago = ($datos['forma_pago'] ?? 'contado') === 'credito' ? 'credito' : 'contado';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':numero'              => $datos['numero'] ?? self::siguienteNumero(),
                ':empresa'             => trim((string) ($datos['empresa'] ?? '')),
                ':ruc'                 => self::nullSiVacio($datos['ruc'] ?? null),
                ':direccion'           => self::nullSiVacio($datos['direccion'] ?? null),
                ':fecha_emision'       => $datos['fecha_emision'] ?: date('Y-m-d'),
                ':validez_dias'        => (int) ($datos['validez_dias'] ?? 7),
                ':forma_pago'          => $formaPago,
                // El credito_dias solo tiene sentido si la forma de pago es credito.
                ':credito_dias'        => $formaPago === 'credito'
                                            ? (int) ($datos['credito_dias'] ?? 7)
                                            : null,
                ':tiempo_entrega_dias' => self::nullSiVacio($datos['tiempo_entrega_dias'] ?? null),
                ':moneda'              => ($datos['moneda'] ?? 'PEN') === 'USD' ? 'USD' : 'PEN',
                ':observaciones'       => self::nullSiVacio($datos['observaciones'] ?? null),
                ':condiciones'         => self::nullSiVacio($datos['condiciones'] ?? null),
                ':total_general'       => self::dec($totales['total_general']),
                ':cliente_subtotal'    => self::dec($totales['cliente_subtotal']),
                ':cliente_igv'         => self::dec($totales['cliente_igv']),
                ':cliente_total'       => self::dec($totales['cliente_total']),
                ':estado'              => $datos['estado'] ?? 'borrador',
            ]);

            $cotizacionId = (int) $pdo->lastInsertId();

            self::insertarItems($pdo, $cotizacionId, $resultado['items']);

            $pdo->commit();

            return $cotizacionId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Inserta los items ya calculados.
     */
    private static function insertarItems(PDO $pdo, int $cotizacionId, array $items): void
    {
        $columnas     = implode(', ', self::COLUMNAS_ITEM);
        $placeholders = implode(', ', array_map(static fn($c) => ':' . $c, self::COLUMNAS_ITEM));

        $stmt = $pdo->prepare(
            "INSERT INTO cotizacion_items (cotizacion_id, {$columnas})
             VALUES (:cotizacion_id, {$placeholders})"
        );

        $linea = 1;
        foreach ($items as $item) {
            $params = [':cotizacion_id' => $cotizacionId];

            foreach (self::COLUMNAS_ITEM as $columna) {
                $params[':' . $columna] = match ($columna) {
                    'linea'       => $linea,
                    'codigo',
                    'marca'       => self::nullSiVacio($item[$columna] ?? null),
                    'descripcion' => trim((string) ($item['descripcion'] ?? '')),
                    'aplica_detraccion',
                    'aplica_retencion' => (int) ($item[$columna] ?? 0),
                    default       => self::dec($item[$columna] ?? 0),
                };
            }

            $stmt->execute($params);
            $linea++;
        }
    }

    /**
     * Elimina una cotizacion (los items caen por ON DELETE CASCADE).
     */
    public static function eliminar(int $id): bool
    {
        $stmt = db()->prepare('DELETE FROM cotizaciones WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Formatea un float para una columna DECIMAL(16,6) sin notacion
     * cientifica ni separadores de miles.
     */
    private static function dec($valor): string
    {
        return number_format((float) $valor, 6, '.', '');
    }

    private static function nullSiVacio($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
