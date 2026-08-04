<?php
/** @var string $numero */
/** @var array $empresaConfig */
/** @var array $ganancias */
/** @var string|null $error */

$opciones = $empresaConfig['opciones'];
?>

<?php if (!empty($error)): ?>
    <div class="aviso aviso-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('guardar')) ?>"
      x-data="cotizacionForm({ ganancias: <?= e(json_encode($ganancias)) ?> })">

    <!-- ============ Datos del cliente y condiciones ============ -->
    <div class="tarjeta">
        <h2>Cotización N° <?= e($numero) ?></h2>
        <input type="hidden" name="numero" value="<?= e($numero) ?>">

        <div class="grid grid-3">
            <div style="grid-column: span 2">
                <label for="empresa">Empresa *</label>
                <input type="text" id="empresa" name="empresa" required placeholder="Razón social del cliente">
            </div>
            <div>
                <label for="ruc">RUC</label>
                <input type="text" id="ruc" name="ruc" maxlength="11" placeholder="20xxxxxxxxx">
            </div>
            <div style="grid-column: span 2">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion">
            </div>
            <div>
                <label for="fecha_emision">Fecha de emisión</label>
                <input type="date" id="fecha_emision" name="fecha_emision" value="<?= e(date('Y-m-d')) ?>">
            </div>
        </div>

        <div class="grid grid-4" style="margin-top:14px">
            <div>
                <label for="validez_dias">Validez de la oferta</label>
                <select id="validez_dias" name="validez_dias">
                    <?php foreach ($opciones['validez_dias'] as $dias): ?>
                        <option value="<?= (int) $dias ?>"><?= (int) $dias ?> días</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="forma_pago">Forma de pago</label>
                <select id="forma_pago" name="forma_pago" x-model="formaPago">
                    <option value="contado">Contado</option>
                    <option value="credito">Crédito</option>
                </select>
            </div>
            <div>
                <label for="credito_dias">Días de crédito</label>
                <select id="credito_dias" name="credito_dias" x-bind:disabled="formaPago !== 'credito'">
                    <?php foreach ($opciones['credito_dias'] as $dias): ?>
                        <option value="<?= (int) $dias ?>"><?= (int) $dias ?> días</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tiempo_entrega_dias">Tiempo de entrega</label>
                <select id="tiempo_entrega_dias" name="tiempo_entrega_dias">
                    <option value="">—</option>
                    <?php foreach ($opciones['tiempo_entrega_dias'] as $dias): ?>
                        <option value="<?= (int) $dias ?>"><?= (int) $dias ?> días</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="moneda">Moneda</label>
                <select id="moneda" name="moneda" x-model="moneda">
                    <option value="PEN">Soles (S/)</option>
                    <option value="USD">Dólares (US$)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ============ Motor de precios, en el orden del Excel ============ -->
    <div class="tarjeta">
        <h2>
            Ítems
            <span class="pista">— las celdas blancas las escribes tú; las azules se calculan solas</span>
        </h2>

        <div class="tabla-scroll">
            <table class="tabla-items tabla-excel">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Cantidad</th>
                        <th>Codigo</th>
                        <th>Marca</th>
                        <th style="min-width:150px">Descripcion</th>
                        <th>Precio (S.IGV)</th>
                        <th>IR</th>
                        <th>IGV</th>
                        <th>Detraccion</th>
                        <th>Licencia S.O</th>
                        <th>Delivery</th>
                        <th>Embalaje</th>
                        <th>Envio</th>
                        <th>Ganancia</th>
                        <th>Subtotal</th>
                        <th>Retencion</th>
                        <th>Total unitario</th>
                        <th>Total cantidad</th>
                        <th></th>
                    </tr>

                    <!-- Leyenda: replica las filas 45-51 del Excel -->
                    <tr class="reglas">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>Variable</td>
                        <td>1.50%<br><b>Fijo</b></td>
                        <td>18%<br><b>Fijo</b></td>
                        <td>12%<br><b>Opcional</b></td>
                        <td>Variable</td>
                        <td>Variable</td>
                        <td>Variable</td>
                        <td>Variable</td>
                        <td>10 · 12 · 14<br>20 · 22 · 24%</td>
                        <td>Suma previa al total</td>
                        <td>3%<br><b>Opcional</b></td>
                        <td>Subtotal + retencion</td>
                        <td>Total unitario × cantidad</td>
                        <td></td>
                    </tr>
                </thead>

                <tbody>
                    <template x-for="(item, i) in items" :key="i">
                        <tr>
                            <td x-text="i + 1"></td>
                            <td><input type="number" step="any" min="0" :name="`items[${i}][cantidad]`" x-model="item.cantidad"></td>
                            <td><input type="text" :name="`items[${i}][codigo]`" x-model="item.codigo"></td>
                            <td><input type="text" :name="`items[${i}][marca]`" x-model="item.marca"></td>
                            <td><input type="text" :name="`items[${i}][descripcion]`" x-model="item.descripcion" required style="min-width:150px"></td>

                            <!-- Variable -->
                            <td><input type="number" step="any" min="0" :name="`items[${i}][precio]`" x-model="item.precio"></td>

                            <!-- Fijos: solo lectura -->
                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).ir)"></td>
                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).igv)"></td>

                            <!-- Opcional: el check la activa -->
                            <td class="calculado opcional">
                                <label>
                                    <input type="checkbox" value="1" :name="`items[${i}][aplica_detraccion]`" x-model="item.aplica_detraccion">
                                    <span x-text="item.aplica_detraccion ? simbolo + ' ' + f(calcular(item).detraccion) : '—'"></span>
                                </label>
                            </td>

                            <!-- Variables -->
                            <td><input type="number" step="any" min="0" :name="`items[${i}][licencia_so]`" x-model="item.licencia_so"></td>
                            <td><input type="number" step="any" min="0" :name="`items[${i}][delivery]`" x-model="item.delivery"></td>
                            <td><input type="number" step="any" min="0" :name="`items[${i}][embalaje]`" x-model="item.embalaje"></td>
                            <td><input type="number" step="any" min="0" :name="`items[${i}][envio]`" x-model="item.envio"></td>

                            <!-- Ganancia: eliges el % y ves el monto -->
                            <td class="calculado">
                                <select :name="`items[${i}][porcentaje_ganancia]`" x-model.number="item.porcentaje_ganancia">
                                    <template x-for="g in ganancias" :key="g">
                                        <option :value="g" x-text="pct(g)"></option>
                                    </template>
                                </select>
                                <div x-text="simbolo + ' ' + f(calcular(item).ganancia)"></div>
                            </td>

                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).subtotal)"></td>

                            <!-- Opcional -->
                            <td class="calculado opcional">
                                <label>
                                    <input type="checkbox" value="1" :name="`items[${i}][aplica_retencion]`" x-model="item.aplica_retencion">
                                    <span x-text="item.aplica_retencion ? simbolo + ' ' + f(calcular(item).retencion) : '—'"></span>
                                </label>
                            </td>

                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).totalUnitario)"></td>
                            <td class="calculado"><strong x-text="simbolo + ' ' + f(calcular(item).totalLinea)"></strong></td>

                            <td style="white-space:nowrap">
                                <button type="button" class="btn btn-sm" @click="duplicarItem(i)" title="Duplicar">⧉</button>
                                <button type="button" class="btn btn-sm btn-peligro" @click="quitarItem(i)" title="Quitar">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p style="margin-top:14px">
            <button type="button" class="btn" @click="agregarItem()">+ Agregar ítem</button>
        </p>

        <!-- Totales: lo que verá el cliente en el PDF -->
        <div class="totales">
            <div>
                <span>Subtotal (base imponible)</span>
                <strong><span x-text="simbolo"></span> <span x-text="f(clienteSubtotal)"></span></strong>
            </div>
            <div>
                <span>I.G.V (18%)</span>
                <strong><span x-text="simbolo"></span> <span x-text="f(clienteIgv)"></span></strong>
            </div>
            <div class="grande">
                <span>Total</span>
                <span><span x-text="simbolo"></span> <span x-text="f(clienteTotal)"></span></span>
            </div>
        </div>
    </div>

    <!-- ============ Textos libres ============ -->
    <div class="tarjeta">
        <h2>Observaciones y condiciones</h2>
        <div class="grid" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones"></textarea>
            </div>
            <div>
                <label for="condiciones">Condiciones</label>
                <textarea id="condiciones" name="condiciones"></textarea>
            </div>
        </div>
    </div>

    <p>
        <button type="submit" class="btn btn-primario">Guardar cotización</button>
        <a class="btn" href="<?= e(url()) ?>">Cancelar</a>
    </p>
</form>
