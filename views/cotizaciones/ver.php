<?php
/** @var array $cotizacion */
/** @var bool $guardada */

$simbolo = simboloMoneda((string) $cotizacion['moneda']);
?>

<?php if (!empty($guardada)): ?>
    <div class="aviso aviso-ok">Cotización guardada correctamente.</div>
<?php endif; ?>

<div class="tarjeta">
    <h2><?= icono('documento', 15) ?>Cotización N° <?= e($cotizacion['numero']) ?></h2>

    <div class="grid grid-4">
        <div><label>Empresa</label><?= e($cotizacion['empresa']) ?></div>
        <div><label>RUC</label><?= e($cotizacion['ruc'] ?? '—') ?></div>
        <div><label>Dirección</label><?= e($cotizacion['direccion'] ?? '—') ?></div>
        <div><label>Emisión</label><?= e(date('d/m/Y', strtotime((string) $cotizacion['fecha_emision']))) ?></div>
        <div><label>Validez</label><?= (int) $cotizacion['validez_dias'] ?> días</div>
        <div>
            <label>Forma de pago</label>
            <?= e(ucfirst((string) $cotizacion['forma_pago'])) ?><?php
                if ($cotizacion['forma_pago'] === 'credito' && $cotizacion['credito_dias']) {
                    echo ' a ' . (int) $cotizacion['credito_dias'] . ' días';
                }
            ?>
        </div>
        <div>
            <label>Tiempo de entrega</label>
            <?= $cotizacion['tiempo_entrega_dias'] ? (int) $cotizacion['tiempo_entrega_dias'] . ' días' : '—' ?>
        </div>
        <div><label>Moneda</label><?= e($cotizacion['moneda']) ?></div>
    </div>

    <p style="margin-top:20px">
        <a class="btn btn-primario" href="<?= e(url('pdf', ['id' => $cotizacion['id'], 'descargar' => 1])) ?>"><?= icono('descargar') ?> Descargar PDF</a>
        <a class="btn" href="<?= e(url('editar', ['id' => $cotizacion['id']])) ?>"><?= icono('lapiz') ?> Editar</a>
        <a class="btn" href="<?= e(url('pdf', ['id' => $cotizacion['id']])) ?>" target="_blank"><?= icono('ojo') ?> Ver PDF en el navegador</a>
        <a class="btn" href="<?= e(url()) ?>"><?= icono('volver') ?> Volver al listado</a>
    </p>
</div>

<!-- ============ Desglose interno (Bloque 2 del Excel) ============ -->
<div class="tarjeta">
    <h2><?= icono('calculadora', 15) ?>Desglose interno <span class="pista">— uso interno, no aparece en el PDF del cliente</span></h2>

    <div class="tabla-scroll">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Marca</th>
                    <th>Descripción</th>
                    <th class="num">Cant.</th>
                    <th class="num">Precio</th>
                    <th class="num">IR</th>
                    <th class="num">IGV</th>
                    <th class="num">Detrac.</th>
                    <th class="num">Lic. S.O</th>
                    <th class="num">Delivery</th>
                    <th class="num">Embalaje</th>
                    <th class="num">Envío</th>
                    <th class="num">Ganancia</th>
                    <th class="num">Subtotal</th>
                    <th class="num">Retención</th>
                    <th class="num">Total unit.</th>
                    <th class="num">Total línea</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cotizacion['items'] as $item): ?>
                <tr>
                    <td><?= (int) $item['linea'] ?></td>
                    <td><?= e($item['codigo'] ?? '—') ?></td>
                    <td><?= e($item['marca'] ?? '—') ?></td>
                    <td><?= e($item['descripcion']) ?></td>
                    <td class="num"><?= money($item['cantidad'], 0) ?></td>
                    <td class="num"><?= money($item['precio'], 4) ?></td>
                    <td class="num"><?= money($item['ir'], 4) ?></td>
                    <td class="num"><?= money($item['igv'], 4) ?></td>
                    <td class="num"><?= $item['aplica_detraccion'] ? money($item['detraccion'], 4) : '—' ?></td>
                    <td class="num"><?= money($item['licencia_so']) ?></td>
                    <td class="num"><?= money($item['delivery']) ?></td>
                    <td class="num"><?= money($item['embalaje']) ?></td>
                    <td class="num"><?= money($item['envio']) ?></td>
                    <td class="num">
                        <?= money($item['ganancia'], 4) ?>
                        <span class="pista">(<?= round((float) $item['porcentaje_ganancia'] * 100) ?>%)</span>
                    </td>
                    <td class="num"><?= money($item['subtotal'], 4) ?></td>
                    <td class="num"><?= $item['aplica_retencion'] ? money($item['retencion'], 4) : '—' ?></td>
                    <td class="num"><?= money($item['total_unitario'], 4) ?></td>
                    <td class="num"><strong><?= money($item['total_linea'], 4) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="totales" style="margin-top:16px">
        <div class="grande">
            <span>Total interno</span>
            <span><?= e($simbolo) ?> <?= money($cotizacion['total_general'], 4) ?></span>
        </div>
    </div>
</div>

<!-- ============ Vista del cliente (Bloque 1 del Excel) ============ -->
<div class="tarjeta">
    <h2><?= icono('ojo', 15) ?>Lo que ve el cliente</h2>

    <div class="tabla-scroll">
        <table>
            <thead>
                <tr>
                    <th>ITEM</th>
                    <th>CÓDIGO</th>
                    <th>MARCA</th>
                    <th class="num">CANTIDAD</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="num">P.UNIT</th>
                    <th class="num">P.TOTAL</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cotizacion['items'] as $item): ?>
                <tr>
                    <td><?= (int) $item['linea'] ?></td>
                    <td><?= e($item['codigo'] ?? '—') ?></td>
                    <td><?= e($item['marca'] ?? '—') ?></td>
                    <td class="num"><?= money($item['cantidad'], 0) ?></td>
                    <td><?= e($item['descripcion']) ?></td>
                    <td class="num"><?= money($item['precio_cliente_unitario']) ?></td>
                    <td class="num"><?= money((float) $item['precio_cliente_unitario'] * (float) $item['cantidad']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="totales" style="margin-top:16px">
        <div><span>Subtotal</span><strong><?= e($simbolo) ?> <?= money($cotizacion['cliente_subtotal']) ?></strong></div>
        <div><span>I.G.V (18%)</span><strong><?= e($simbolo) ?> <?= money($cotizacion['cliente_igv']) ?></strong></div>
        <div class="grande"><span>Total</span><span><?= e($simbolo) ?> <?= money($cotizacion['cliente_total']) ?></span></div>
    </div>

    <?php if (abs((float) $cotizacion['cliente_total'] - (float) $cotizacion['total_general']) > 0.01): ?>
        <div class="aviso aviso-error" style="margin-top:16px">
            El total del cliente no coincide con el total interno. Revisar el cálculo.
        </div>
    <?php else: ?>
        <div class="aviso aviso-info" style="margin-top:16px">
            El total del cliente cuadra con el total interno del motor de precios.
        </div>
    <?php endif; ?>
</div>

<?php if ($cotizacion['observaciones'] || $cotizacion['condiciones']): ?>
<div class="tarjeta">
    <h2>Observaciones y condiciones</h2>
    <div class="grid" style="grid-template-columns:1fr 1fr">
        <div><label>Observaciones</label><?= nl2br(e($cotizacion['observaciones'] ?? '—')) ?></div>
        <div><label>Condiciones</label><?= nl2br(e($cotizacion['condiciones'] ?? '—')) ?></div>
    </div>
</div>
<?php endif; ?>
