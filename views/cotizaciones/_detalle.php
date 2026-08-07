<?php
/**
 * Fragmento del detalle de una cotizacion, para el modal del listado.
 * Se sirve suelto, sin layout.
 *
 * @var array $cotizacion
 */

$simbolo = simboloMoneda((string) $cotizacion['moneda']);

$estados = [
    'borrador'  => 'Borrador',
    'emitida'   => 'Emitida',
    'aceptada'  => 'Aceptada',
    'rechazada' => 'Rechazada',
];

$formaPago = ucfirst((string) $cotizacion['forma_pago']);
if ($cotizacion['forma_pago'] === 'credito' && $cotizacion['credito_dias']) {
    $formaPago .= ' a ' . (int) $cotizacion['credito_dias'] . ' días';
}

$vence = date(
    'd/m/Y',
    strtotime((string) $cotizacion['fecha_emision'] . ' +' . (int) $cotizacion['validez_dias'] . ' days')
);
?>

<!-- Cabecera -->
<div class="det-cabecera">
    <div>
        <span class="folio"><?= e($cotizacion['numero']) ?></span>
        <span class="etiqueta etiqueta-<?= e($cotizacion['estado']) ?>">
            <?= e($estados[$cotizacion['estado']] ?? $cotizacion['estado']) ?>
        </span>
    </div>
    <div class="det-total">
        <span>Total</span>
        <strong><?= e($simbolo) ?> <?= money($cotizacion['cliente_total']) ?></strong>
    </div>
</div>

<!-- Cliente y condiciones -->
<div class="det-columnas">
    <div class="det-bloque">
        <h4><?= icono('empresa', 13) ?> Cliente</h4>
        <p class="det-destacado"><?= e($cotizacion['razon_social']) ?></p>
        <dl>
            <dt>RUC</dt><dd><?= e($cotizacion['ruc'] ?? '—') ?></dd>
            <dt>Dirección</dt><dd><?= e($cotizacion['direccion'] ?? '—') ?></dd>
        </dl>
    </div>

    <div class="det-bloque">
        <h4><?= icono('calendario', 13) ?> Condiciones</h4>
        <dl>
            <dt>Emisión</dt>
            <dd><?= e(date('d/m/Y', strtotime((string) $cotizacion['fecha_emision']))) ?></dd>

            <dt>Válida hasta</dt>
            <dd><?= e($vence) ?> <span class="pista">(<?= (int) $cotizacion['validez_dias'] ?> días)</span></dd>

            <dt>Forma de pago</dt>
            <dd><?= e($formaPago) ?></dd>

            <dt>Entrega</dt>
            <dd><?= $cotizacion['tiempo_entrega_dias'] ? (int) $cotizacion['tiempo_entrega_dias'] . ' días' : '—' ?></dd>

            <dt>Moneda</dt>
            <dd><?= $cotizacion['moneda'] === 'USD' ? 'Dólares (US$)' : 'Soles (S/)' ?></dd>
        </dl>
    </div>
</div>

<!-- Items tal como los ve el cliente -->
<div class="det-bloque">
    <h4><?= icono('documento', 13) ?> Ítems <span class="pista">— como los ve el cliente en el PDF</span></h4>

    <div class="tabla-scroll">
        <table class="det-tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descripción</th>
                    <th class="num">Cant.</th>
                    <th class="num">P. Unitario</th>
                    <th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cotizacion['items'] as $item): ?>
                <?php
                    $meta = array_filter([$item['codigo'] ?? null, $item['marca'] ?? null]);
                    $importe = (float) $item['precio_cliente_unitario'] * (float) $item['cantidad'];
                ?>
                <tr>
                    <td><?= (int) $item['linea'] ?></td>
                    <td>
                        <strong><?= e($item['descripcion']) ?></strong>
                        <?php if ($meta !== []): ?>
                            <br><span class="pista"><?= e(implode(' · ', $meta)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= money($item['cantidad'], 0) ?></td>
                    <td class="num"><?= e($simbolo) ?> <?= money($item['precio_cliente_unitario']) ?></td>
                    <td class="num"><strong><?= e($simbolo) ?> <?= money($importe) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="det-totales">
        <div><span>Subtotal</span><span><?= e($simbolo) ?> <?= money($cotizacion['cliente_subtotal']) ?></span></div>
        <div><span>I.G.V (18%)</span><span><?= e($simbolo) ?> <?= money($cotizacion['cliente_igv']) ?></span></div>
        <div class="fuerte"><span>Total</span><span><?= e($simbolo) ?> <?= money($cotizacion['cliente_total']) ?></span></div>
    </div>
</div>

<!-- Desglose interno, plegado: no es lo que se manda al cliente -->
<div class="det-bloque" x-data="{ abierto: false }">
    <button type="button" class="det-plegable" @click="abierto = !abierto">
        <?= icono('calculadora', 13) ?>
        Desglose interno
        <span class="pista">— costos y margen, no salen en el PDF</span>
        <span class="det-flecha" :class="abierto ? 'girada' : ''"><?= icono('flecha', 14) ?></span>
    </button>

    <div x-show="abierto" x-transition style="display:none">
        <div class="tabla-scroll">
            <table class="det-tabla det-tabla-interna">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="num">Precio</th>
                        <th class="num">IR</th>
                        <th class="num">IGV</th>
                        <th class="num">Detrac.</th>
                        <th class="num">Lic. S.O</th>
                        <th class="num">Deliv.</th>
                        <th class="num">Embal.</th>
                        <th class="num">Envío</th>
                        <th class="num">Ganancia</th>
                        <th class="num">Subtotal</th>
                        <th class="num">Retenc.</th>
                        <th class="num">T. unit.</th>
                        <th class="num">T. línea</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cotizacion['items'] as $item): ?>
                    <tr>
                        <td><?= (int) $item['linea'] ?></td>
                        <td class="num"><?= money($item['precio'], 2) ?></td>
                        <td class="num"><?= money($item['ir'], 2) ?></td>
                        <td class="num"><?= money($item['igv'], 2) ?></td>
                        <td class="num"><?= $item['aplica_detraccion'] ? money($item['detraccion'], 2) : '—' ?></td>
                        <td class="num"><?= money($item['licencia_so'], 2) ?></td>
                        <td class="num"><?= money($item['delivery'], 2) ?></td>
                        <td class="num"><?= money($item['embalaje'], 2) ?></td>
                        <td class="num"><?= money($item['envio'], 2) ?></td>
                        <td class="num">
                            <?= money($item['ganancia'], 2) ?>
                            <span class="pista"><?= round((float) $item['porcentaje_ganancia'] * 100) ?>%</span>
                        </td>
                        <td class="num"><?= money($item['subtotal'], 2) ?></td>
                        <td class="num"><?= $item['aplica_retencion'] ? money($item['retencion'], 2) : '—' ?></td>
                        <td class="num"><?= money($item['total_unitario'], 2) ?></td>
                        <td class="num"><strong><?= money($item['total_linea'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="pista" style="margin-top:8px">
            Total interno <?= e($simbolo) ?> <?= money($cotizacion['total_general'], 2) ?>.
            <?php if (abs((float) $cotizacion['cliente_total'] - (float) $cotizacion['total_general']) > 0.01): ?>
                <strong style="color:var(--rojo)">No cuadra con el total del cliente.</strong>
            <?php else: ?>
                Cuadra con el total que ve el cliente.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($cotizacion['observaciones'] || $cotizacion['condiciones']): ?>
<div class="det-columnas">
    <?php if ($cotizacion['observaciones']): ?>
        <div class="det-bloque">
            <h4>Observaciones</h4>
            <p class="det-texto"><?= nl2br(e($cotizacion['observaciones'])) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($cotizacion['condiciones']): ?>
        <div class="det-bloque">
            <h4>Condiciones</h4>
            <p class="det-texto"><?= nl2br(e($cotizacion['condiciones'])) ?></p>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
