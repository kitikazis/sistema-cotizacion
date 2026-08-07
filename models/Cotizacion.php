<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Cliente.php';
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

    /** Columnas por las que se permite ordenar el listado. */
    private const ORDENES = [
        'numero'  => 'c.numero',
        'fecha'   => 'c.fecha_emision',
        'cliente' => 'cl.razon_social',
        'total'   => 'c.cliente_total',
    ];

    /**
     * Lista cotizaciones aplicando filtros de busqueda.
     *
     * @param array $filtros q, fecha_desde, fecha_hasta, estado, moneda,
     *                       orden, dir
     */
    public static function listar(array $filtros = []): array
    {
        [$where, $params] = self::construirFiltros($filtros);

        $orden = self::ORDENES[$filtros['orden'] ?? ''] ?? 'c.id';
        $dir   = strtolower($filtros['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $sql = "SELECT c.*, cl.razon_social, cl.ruc, cl.direccion,
                       COUNT(i.id) AS items
                FROM cotizaciones c
                JOIN clientes cl ON cl.id = c.cliente_id
                LEFT JOIN cotizacion_items i ON i.cotizacion_id = c.id
                {$where}
                GROUP BY c.id
                ORDER BY {$orden} {$dir}";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Totales del conjunto filtrado, para las tarjetas de resumen.
     */
    public static function resumen(array $filtros = []): array
    {
        [$where, $params] = self::construirFiltros($filtros);

        // Se separa por estado para poder distinguir lo que sigue en juego
        // de lo que ya cerro: sumarlo todo junto no dice nada util.
        $sql = "SELECT
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(CASE WHEN c.moneda = 'PEN' THEN c.cliente_total END), 0) AS total_pen,
                    COALESCE(SUM(CASE WHEN c.moneda = 'USD' THEN c.cliente_total END), 0) AS total_usd,

                    SUM(c.estado = 'borrador')  AS n_borrador,
                    SUM(c.estado = 'emitida')   AS n_emitida,
                    SUM(c.estado = 'aceptada')  AS n_aceptada,
                    SUM(c.estado = 'rechazada') AS n_rechazada,

                    COALESCE(SUM(CASE WHEN c.estado = 'emitida'  AND c.moneda = 'PEN' THEN c.cliente_total END), 0) AS oferta_pen,
                    COALESCE(SUM(CASE WHEN c.estado = 'emitida'  AND c.moneda = 'USD' THEN c.cliente_total END), 0) AS oferta_usd,
                    COALESCE(SUM(CASE WHEN c.estado = 'aceptada' AND c.moneda = 'PEN' THEN c.cliente_total END), 0) AS ganado_pen,
                    COALESCE(SUM(CASE WHEN c.estado = 'aceptada' AND c.moneda = 'USD' THEN c.cliente_total END), 0) AS ganado_usd
                FROM cotizaciones c
                JOIN clientes cl ON cl.id = c.cliente_id
                {$where}";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $r = $stmt->fetch() ?: [];

        // Tasa de cierre sobre lo que ya tuvo respuesta: incluir las que
        // siguen esperando la hundiria sin que signifique nada.
        $resueltas = (int) ($r['n_aceptada'] ?? 0) + (int) ($r['n_rechazada'] ?? 0);
        $r['tasa_cierre'] = $resueltas > 0
            ? round((int) $r['n_aceptada'] * 100 / $resueltas)
            : null;
        $r['resueltas'] = $resueltas;

        return $r;
    }

    /**
     * Arma el WHERE compartido por listar() y resumen().
     *
     * @return array{0: string, 1: array}
     */
    private static function construirFiltros(array $filtros): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            // Tres placeholders distintos con el mismo valor: con prepares
            // nativos (EMULATE_PREPARES = false) MySQL no permite repetir un
            // parametro con nombre dentro de la misma consulta.
            $where[] = '(cl.razon_social LIKE :q_razon OR cl.ruc LIKE :q_ruc OR c.numero LIKE :q_numero)';
            $patron = '%' . $q . '%';
            $params[':q_razon']  = $patron;
            $params[':q_ruc']    = $patron;
            $params[':q_numero'] = $patron;
        }

        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'c.fecha_emision >= :fecha_desde';
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'c.fecha_emision <= :fecha_hasta';
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }

        if (!empty($filtros['estado'])) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['moneda'])) {
            $where[] = 'c.moneda = :moneda';
            $params[':moneda'] = $filtros['moneda'];
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Devuelve una cotizacion con su cliente y sus items, o null.
     */
    public static function obtener(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT c.*, cl.razon_social, cl.ruc, cl.direccion
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $cotizacion = $stmt->fetch();

        if (!$cotizacion) {
            return null;
        }

        // Alias para que las vistas y el PDF sigan hablando de "empresa".
        $cotizacion['empresa'] = $cotizacion['razon_social'];

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
    public static function siguienteNumero(bool $bloqueando = false): string
    {
        // FOR UPDATE dentro de una transaccion serializa a quienes esten
        // creando a la vez: el segundo espera y toma el numero siguiente.
        // Sin esto, dos personas con el formulario abierto obtenian el mismo
        // correlativo y la segunda chocaba contra la clave unica.
        $sql = 'SELECT MAX(CAST(numero AS UNSIGNED)) FROM cotizaciones'
             . ($bloqueando ? ' FOR UPDATE' : '');

        $max = db()->query($sql)->fetchColumn();

        $siguiente = max((int) $max, 144) + 1;

        return str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Crea una cotizacion con sus items dentro de una transaccion.
     *
     * @return int Id de la cotizacion creada.
     */
    public static function crear(array $datos, array $items): int
    {
        if ($items === []) {
            throw new InvalidArgumentException('La cotizacion necesita al menos un item.');
        }

        $resultado = PricingCalculator::calcularCotizacion($items);
        $totales   = $resultado['totales'];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $clienteId = Cliente::obtenerOCrear($datos);

            $sql = 'INSERT INTO cotizaciones
                        (numero, cliente_id, emisor_razon_social, emisor_ruc,
                         fecha_emision, validez_dias, forma_pago, credito_dias,
                         tiempo_entrega_dias, moneda, observaciones, condiciones,
                         total_general, cliente_subtotal, cliente_igv, cliente_total, estado)
                    VALUES
                        (:numero, :cliente_id, :emisor_razon_social, :emisor_ruc,
                         :fecha_emision, :validez_dias, :forma_pago, :credito_dias,
                         :tiempo_entrega_dias, :moneda, :observaciones, :condiciones,
                         :total_general, :cliente_subtotal, :cliente_igv, :cliente_total, :estado)';

            // El numero se asigna AQUI, ya dentro de la transaccion, y se
            // ignora el que venga del formulario: aquel se calculo al abrir
            // la pantalla y para cuando se guarda puede estar tomado.
            $stmt = $pdo->prepare($sql);
            $stmt->execute(self::parametrosCabecera($datos, $totales, $clienteId) + [
                ':numero' => self::siguienteNumero(true),
                ':estado' => $datos['estado'] ?? 'borrador',
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
     * Actualiza una cotizacion existente.
     *
     * Los items se reemplazan por completo: es mas simple y seguro que
     * intentar casar cual fila cambio, y como todo se recalcula igual no
     * hay nada que preservar de las filas viejas.
     */
    public static function actualizar(int $id, array $datos, array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('La cotizacion necesita al menos un item.');
        }

        $resultado = PricingCalculator::calcularCotizacion($items);
        $totales   = $resultado['totales'];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $clienteId = Cliente::obtenerOCrear($datos);

            $sql = 'UPDATE cotizaciones SET
                        cliente_id          = :cliente_id,
                        emisor_razon_social = :emisor_razon_social,
                        emisor_ruc          = :emisor_ruc,
                        fecha_emision       = :fecha_emision,
                        validez_dias        = :validez_dias,
                        forma_pago          = :forma_pago,
                        credito_dias        = :credito_dias,
                        tiempo_entrega_dias = :tiempo_entrega_dias,
                        moneda              = :moneda,
                        observaciones       = :observaciones,
                        condiciones         = :condiciones,
                        total_general       = :total_general,
                        cliente_subtotal    = :cliente_subtotal,
                        cliente_igv         = :cliente_igv,
                        cliente_total       = :cliente_total
                    WHERE id = :id';

            $stmt = $pdo->prepare($sql);
            $stmt->execute(self::parametrosCabecera($datos, $totales, $clienteId) + [':id' => $id]);

            $borrar = $pdo->prepare('DELETE FROM cotizacion_items WHERE cotizacion_id = ?');
            $borrar->execute([$id]);

            self::insertarItems($pdo, $id, $resultado['items']);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Parametros comunes al INSERT y al UPDATE de la cabecera.
     */
    private static function parametrosCabecera(array $datos, array $totales, int $clienteId): array
    {
        $formaPago = ($datos['forma_pago'] ?? 'contado') === 'credito' ? 'credito' : 'contado';

        return [
            ':cliente_id'          => $clienteId,
            ':emisor_razon_social' => self::nullSiVacio($datos['emisor_razon_social'] ?? null),
            ':emisor_ruc'          => self::nullSiVacio($datos['emisor_ruc'] ?? null),
            ':fecha_emision'       => $datos['fecha_emision'] ?: date('Y-m-d'),
            ':validez_dias'        => (int) ($datos['validez_dias'] ?? 7),
            ':forma_pago'          => $formaPago,
            // credito_dias solo tiene sentido si la forma de pago es credito.
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
        ];
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

    /** Estados por los que puede pasar una cotizacion. */
    public const ESTADOS = ['borrador', 'emitida', 'aceptada', 'rechazada'];

    /**
     * Cambia el estado de una cotizacion.
     *
     * No se tocan los montos: cambiar de estado no re-cotiza nada.
     *
     * @throws InvalidArgumentException si el estado no es uno de los validos.
     */
    public static function cambiarEstado(int $id, string $estado): bool
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException("Estado no valido: {$estado}");
        }

        $stmt = db()->prepare('UPDATE cotizaciones SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * ¿Se le paso la fecha de validez?
     *
     * Se calcula al vuelo y no se guarda: el estado sigue siendo el que se
     * puso a mano. Asi, si el cliente responde tarde, la cotizacion se puede
     * aceptar igual. Una ya aceptada o rechazada nunca se marca vencida:
     * su historia ya termino.
     */
    public static function estaVencida(array $cotizacion): bool
    {
        if (in_array($cotizacion['estado'], ['aceptada', 'rechazada'], true)) {
            return false;
        }

        $limite = strtotime(
            (string) $cotizacion['fecha_emision'] . ' +' . (int) $cotizacion['validez_dias'] . ' days'
        );

        return $limite !== false && $limite < strtotime('today');
    }

    /**
     * Elimina una cotizacion (los items caen por ON DELETE CASCADE).
     * El cliente se conserva: puede tener otras cotizaciones.
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
