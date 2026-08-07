<?php
/**
 * Motor de precios. Traduccion literal del Bloque 2 del Excel (fila 43+).
 *
 * FUENTE DE VERDAD - no modificar sin pedido explicito:
 *
 *   IR             = Precio x 1.5%                        [FIJO]
 *   IGV            = Precio x 18%                         [FIJO]
 *   Detraccion     = Precio x 12%                         [OPCIONAL, por item]
 *   Ganancia       = Precio x %  (10/12/14/20/22/24)      [elegible, por item]
 *   Subtotal       = Precio + IR + IGV + Detraccion
 *                    + Licencia S.O + Delivery + Embalaje + Envio + Ganancia
 *   Retencion      = Subtotal x 3%                        [OPCIONAL, por item]
 *   Total unitario = Subtotal + Retencion
 *   Total linea    = Total unitario x Cantidad
 *
 * Nota sobre precision: se calcula en float sin redondear en pasos
 * intermedios, igual que Excel. El redondeo ocurre solo al presentar.
 */
class PricingCalculator
{
    public const TASA_IR         = 0.015;
    public const TASA_IGV        = 0.18;
    public const TASA_DETRACCION = 0.12;
    public const TASA_RETENCION  = 0.03;

    /** Las 6 opciones de ganancia del Excel (N45-N50). */
    public const GANANCIAS_PERMITIDAS = [0.10, 0.12, 0.14, 0.20, 0.22, 0.24];

    /**
     * Calcula una linea completa.
     *
     * @param array $item Claves: cantidad, precio, licencia_so, delivery,
     *                    embalaje, envio, aplica_detraccion, aplica_retencion,
     *                    porcentaje_ganancia
     * @return array El item con todas las columnas calculadas agregadas.
     */
    public static function calcularItem(array $item): array
    {
        $cantidad   = self::num($item['cantidad'] ?? 1);
        $precio     = self::num($item['precio'] ?? 0);
        $licenciaSo = self::num($item['licencia_so'] ?? 0);
        $delivery   = self::num($item['delivery'] ?? 0);
        $embalaje   = self::num($item['embalaje'] ?? 0);
        $envio      = self::num($item['envio'] ?? 0);

        $aplicaDetraccion = self::flag($item['aplica_detraccion'] ?? false);
        $aplicaRetencion  = self::flag($item['aplica_retencion'] ?? false);
        $pctGanancia      = self::normalizarGanancia($item['porcentaje_ganancia'] ?? 0.10);

        // --- Formula (no tocar) ---
        $ir         = $precio * self::TASA_IR;
        $igv        = $precio * self::TASA_IGV;
        $detraccion = $aplicaDetraccion ? $precio * self::TASA_DETRACCION : 0.0;
        $ganancia   = $precio * $pctGanancia;

        $subtotal = $precio
                  + $ir
                  + $igv
                  + $detraccion
                  + $licenciaSo
                  + $delivery
                  + $embalaje
                  + $envio
                  + $ganancia;

        $retencion     = $aplicaRetencion ? $subtotal * self::TASA_RETENCION : 0.0;
        $totalUnitario = $subtotal + $retencion;
        $totalLinea    = $totalUnitario * $cantidad;
        // --- Fin formula ---

        // Puente al Bloque 1: el P.UNIT que ve el cliente es el total unitario
        // sin IGV, de modo que al re-agregarle 18% se llega al mismo total.
        $precioClienteUnitario = $totalUnitario / (1 + self::TASA_IGV);

        return array_merge($item, [
            'cantidad'                => $cantidad,
            'precio'                  => $precio,
            'licencia_so'             => $licenciaSo,
            'delivery'                => $delivery,
            'embalaje'                => $embalaje,
            'envio'                   => $envio,
            'aplica_detraccion'       => $aplicaDetraccion ? 1 : 0,
            'aplica_retencion'        => $aplicaRetencion ? 1 : 0,
            'porcentaje_ganancia'     => $pctGanancia,
            'ir'                      => $ir,
            'igv'                     => $igv,
            'detraccion'              => $detraccion,
            'ganancia'                => $ganancia,
            'subtotal'                => $subtotal,
            'retencion'               => $retencion,
            'total_unitario'          => $totalUnitario,
            'total_linea'             => $totalLinea,
            'precio_cliente_unitario' => $precioClienteUnitario,
        ]);
    }

    /**
     * Calcula todos los items y los totales de la cotizacion.
     *
     * @param array $items Lista de items crudos.
     * @return array{items: array, totales: array}
     */
    public static function calcularCotizacion(array $items): array
    {
        $calculados = [];
        foreach ($items as $item) {
            $calculados[] = self::calcularItem($item);
        }

        $totalGeneral    = 0.0;
        $clienteSubtotal = 0.0;

        foreach ($calculados as $item) {
            $totalGeneral    += $item['total_linea'];
            $clienteSubtotal += $item['precio_cliente_unitario'] * $item['cantidad'];
        }

        $clienteIgv   = $clienteSubtotal * self::TASA_IGV;
        $clienteTotal = $clienteSubtotal + $clienteIgv;

        return [
            'items'   => $calculados,
            'totales' => [
                'total_general'    => $totalGeneral,
                'cliente_subtotal' => $clienteSubtotal,
                'cliente_igv'      => $clienteIgv,
                'cliente_total'    => $clienteTotal,
            ],
        ];
    }

    /**
     * Acepta 0.12 o 12 y devuelve siempre la fraccion valida mas cercana.
     * Si el valor no esta en la lista permitida, cae al 10% por defecto.
     */
    public static function normalizarGanancia($valor): float
    {
        $valor = self::num($valor);

        // Permite que el formulario mande "12" en vez de "0.12".
        if ($valor > 1) {
            $valor = $valor / 100;
        }

        foreach (self::GANANCIAS_PERMITIDAS as $permitida) {
            if (abs($valor - $permitida) < 0.00001) {
                return $permitida;
            }
        }

        return self::GANANCIAS_PERMITIDAS[0];
    }

    /** Convierte a float tolerando comas decimales y campos vacios. */
    private static function num($valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace([' ', ','], ['', '.'], trim($valor));
        }

        return $valor === '' || $valor === null ? 0.0 : (float) $valor;
    }

    /** Interpreta "1", "on", "true", 1, true como verdadero. */
    private static function flag($valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
