<?php
/**
 * Formulario compartido por crear y editar.
 *
 * @var array|null $cotizacion  null al crear; con datos al editar
 * @var string     $numero
 * @var array      $empresaConfig
 * @var array      $ganancias
 * @var string|null $error
 */

$opciones  = $empresaConfig['opciones'];
$editando  = $cotizacion !== null;

// Lo lee views/layout.php: esta pantalla necesita todo el ancho por la
// tabla de items.
$anchoCompleto = true;

/** Devuelve el valor guardado o el default cuando se esta creando. */
$val = static function (string $campo, $default = '') use ($cotizacion) {
    return $cotizacion[$campo] ?? $default;
};

/** Marca la opcion seleccionada de un select. */
$sel = static function ($valor, $actual): string {
    return (string) $valor === (string) $actual ? ' selected' : '';
};

// Items ya guardados, normalizados a los tipos que espera Alpine.
$itemsIniciales = [];
foreach ($cotizacion['items'] ?? [] as $item) {
    $itemsIniciales[] = [
        'codigo'              => (string) ($item['codigo'] ?? ''),
        'marca'               => (string) ($item['marca'] ?? ''),
        'descripcion'         => (string) $item['descripcion'],
        'cantidad'            => (float) $item['cantidad'],
        'precio'              => (float) $item['precio'],
        'licencia_so'         => (float) $item['licencia_so'],
        'delivery'            => (float) $item['delivery'],
        'embalaje'            => (float) $item['embalaje'],
        'envio'               => (float) $item['envio'],
        'aplica_detraccion'   => (bool) $item['aplica_detraccion'],
        'aplica_retencion'    => (bool) $item['aplica_retencion'],
        'porcentaje_ganancia' => (float) $item['porcentaje_ganancia'],
    ];
}

// Clientes ya registrados, para autocompletar. Se manda solo lo que hace
// falta: con cientos de clientes, mandar la fila entera engorda la pagina.
$clientesJs = [];
foreach ($clientes ?? [] as $cl) {
    $clientesJs[] = [
        'nombre'    => $cl['razon_social'],
        'ruc'       => (string) ($cl['ruc'] ?? ''),
        'direccion' => (string) ($cl['direccion'] ?? ''),
    ];
}

$configAlpine = [
    'ganancias' => $ganancias,
    'items'     => $itemsIniciales,
    'moneda'    => (string) $val('moneda', 'PEN'),
    'formaPago' => (string) $val('forma_pago', 'contado'),
    'clientes'  => $clientesJs,
    'cliente'   => [
        'empresa'   => (string) $val('empresa'),
        'ruc'       => (string) $val('ruc'),
        'direccion' => (string) $val('direccion'),
    ],
];
?>

<?php if (!empty($error)): ?>
    <div class="aviso aviso-error"><?= e($error) ?></div>
<?php endif; ?>

<?php // En el modal la cabecera ya dice que cotizacion se esta editando. ?>
<?php if ($editando && empty($enModal)): ?>
    <div class="aviso aviso-info">
        Estás editando la cotización N° <?= e($cotizacion['numero']) ?>.
        Al guardar se recalculan todos los montos y se reemplazan sus ítems.
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url($editando ? 'actualizar' : 'guardar')) ?>"
      x-data="cotizacionForm(<?= e(json_encode($configAlpine)) ?>)"
      @submit="alEnviar($event)"
      @reset-envio="enviando = false">

    <?= campoCsrf() ?>

    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= (int) $cotizacion['id'] ?>">
    <?php endif; ?>

    <!-- ============ Datos del cliente y condiciones ============ -->
    <div class="tarjeta">
        <?php // Dentro del modal el numero ya sale en la cabecera. ?>
        <h2>
            <?= icono(!empty($enModal) ? 'empresa' : 'documento', 15) ?>
            <?= !empty($enModal) ? 'Cliente y condiciones' : 'Cotización N° ' . e($numero) ?>
            <?php if (!$editando): ?>
                <span class="pista">— el N° <?= e($numero) ?> se confirma al guardar</span>
            <?php endif; ?>
        </h2>
        <?php if ($editando): ?>
            <?php // Al editar el numero ya existe y no se recalcula. ?>
            <input type="hidden" name="numero" value="<?= e($numero) ?>">
        <?php endif; ?>

        <div class="grid grid-3">
            <div style="grid-column: span 2">
                <label for="empresa">Empresa <span class="obligatorio" aria-hidden="true">*</span></label>
                <?php
                    // Autocompleta con los clientes ya registrados: al elegir
                    // uno se rellenan RUC y direccion. Evita tipear de nuevo y
                    // evita crear un cliente duplicado por una tilde de mas.
                ?>
                <input type="text" id="empresa" name="empresa" required
                       list="lista-clientes" autocomplete="off"
                       placeholder="Razón social del cliente"
                       x-model="cliente.empresa"
                       @input="autocompletarCliente()"
                       @blur="tocado.empresa = true"
                       :aria-invalid="errorEmpresa ? 'true' : 'false'"
                       aria-describedby="error-empresa">

                <datalist id="lista-clientes">
                    <?php foreach ($clientes ?? [] as $cl): ?>
                        <option value="<?= e($cl['razon_social']) ?>">
                            <?= e($cl['ruc'] ? 'RUC ' . $cl['ruc'] : '') ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>

                <p class="error-campo" id="error-empresa" x-show="errorEmpresa" x-cloak style="display:none"
                   x-text="errorEmpresa"></p>
            </div>
            <div>
                <label for="ruc">RUC</label>
                <?php // inputmode numeric: en movil abre el teclado de numeros. ?>
                <input type="text" id="ruc" name="ruc" maxlength="11"
                       inputmode="numeric" pattern="[0-9]{11}" autocomplete="off"
                       placeholder="20xxxxxxxxx"
                       x-model="cliente.ruc"
                       @blur="tocado.ruc = true"
                       :aria-invalid="errorRuc ? 'true' : 'false'"
                       aria-describedby="error-ruc">

                <p class="error-campo" id="error-ruc" x-show="errorRuc" x-cloak style="display:none"
                   x-text="errorRuc"></p>
            </div>
            <div style="grid-column: span 2">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion"
                       autocomplete="off" x-model="cliente.direccion">
            </div>
            <div>
                <label for="fecha_emision">Fecha de emisión</label>
                <input type="date" id="fecha_emision" name="fecha_emision"
                       value="<?= e($val('fecha_emision', date('Y-m-d'))) ?>">
            </div>
        </div>

        <div class="grid grid-4" style="margin-top:14px">
            <div>
                <label for="validez_dias">Validez de la oferta</label>
                <select id="validez_dias" name="validez_dias">
                    <?php foreach ($opciones['validez_dias'] as $dias): ?>
                        <option value="<?= (int) $dias ?>"<?= $sel($dias, $val('validez_dias', 7)) ?>><?= (int) $dias ?> días</option>
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
                        <option value="<?= (int) $dias ?>"<?= $sel($dias, $val('credito_dias', 7)) ?>><?= (int) $dias ?> días</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tiempo_entrega_dias">Tiempo de entrega</label>
                <select id="tiempo_entrega_dias" name="tiempo_entrega_dias">
                    <option value="">—</option>
                    <?php foreach ($opciones['tiempo_entrega_dias'] as $dias): ?>
                        <option value="<?= (int) $dias ?>"<?= $sel($dias, $val('tiempo_entrega_dias')) ?>><?= (int) $dias ?> días</option>
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
            <?= icono('calculadora', 15) ?>Ítems
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
                        <td></td><td></td><td></td><td></td><td></td>
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
                            <td><input type="number" step="any" min="0" :name="`items[${i}][cantidad]`" :aria-label="`Cantidad del item ${i + 1}`" x-model="item.cantidad"></td>
                            <td><input type="text" :name="`items[${i}][codigo]`" :aria-label="`Codigo del item ${i + 1}`" x-model="item.codigo"></td>
                            <td><input type="text" :name="`items[${i}][marca]`" :aria-label="`Marca del item ${i + 1}`" x-model="item.marca"></td>
                            <td><input type="text" :name="`items[${i}][descripcion]`" :aria-label="`Descripcion del item ${i + 1}`" x-model="item.descripcion" required style="min-width:150px"></td>

                            <td><input type="number" step="any" min="0" placeholder="0.00" class="dinero" :name="`items[${i}][precio]`" :aria-label="`Precio sin IGV del item ${i + 1}`" x-model="item.precio"></td>

                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).ir)"></td>
                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).igv)"></td>

                            <td class="calculado opcional">
                                <label>
                                    <input type="checkbox" value="1" :name="`items[${i}][aplica_detraccion]`" :aria-label="`Aplicar detraccion al item ${i + 1}`" x-model="item.aplica_detraccion">
                                    <span x-text="item.aplica_detraccion ? simbolo + ' ' + f(calcular(item).detraccion) : '—'"></span>
                                </label>
                            </td>

                            <td><input type="number" step="any" min="0" placeholder="0.00" class="dinero" :name="`items[${i}][licencia_so]`" :aria-label="`Licencia S.O del item ${i + 1}`" x-model="item.licencia_so"></td>
                            <td><input type="number" step="any" min="0" placeholder="0.00" class="dinero" :name="`items[${i}][delivery]`" :aria-label="`Delivery del item ${i + 1}`" x-model="item.delivery"></td>
                            <td><input type="number" step="any" min="0" placeholder="0.00" class="dinero" :name="`items[${i}][embalaje]`" :aria-label="`Embalaje del item ${i + 1}`" x-model="item.embalaje"></td>
                            <td><input type="number" step="any" min="0" placeholder="0.00" class="dinero" :name="`items[${i}][envio]`" :aria-label="`Envio del item ${i + 1}`" x-model="item.envio"></td>

                            <td class="calculado">
                                <?php
                                    // Las opciones se pintan aqui y no con un x-for anidado:
                                    // con x-for, x-model corre antes de que existan y no puede
                                    // seleccionar el valor guardado. Al editar un item con 20%
                                    // el desplegable mostraba 10% aunque el monto fuera el
                                    // correcto.
                                ?>
                                <select :name="`items[${i}][porcentaje_ganancia]`" :aria-label="`Porcentaje de ganancia del item ${i + 1}`" x-model.number="item.porcentaje_ganancia">
                                    <?php foreach ($ganancias as $g): ?>
                                        <option value="<?= e((string) $g) ?>"><?= round($g * 100) ?>%</option>
                                    <?php endforeach; ?>
                                </select>
                                <div x-text="simbolo + ' ' + f(calcular(item).ganancia)"></div>
                            </td>

                            <td class="calculado" x-text="simbolo + ' ' + f(calcular(item).subtotal)"></td>

                            <td class="calculado opcional">
                                <label>
                                    <input type="checkbox" value="1" :name="`items[${i}][aplica_retencion]`" :aria-label="`Aplicar retencion al item ${i + 1}`" x-model="item.aplica_retencion">
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
            <button type="button" class="btn" @click="agregarItem()"><?= icono('mas') ?> Agregar ítem</button>
        </p>

        <div class="panel-totales">
            <div class="panel-totales-nota">
                <?= icono('ojo', 14, 'ico-tenue') ?>
                Esto es lo que verá el cliente en el PDF. El desglose interno
                no aparece ahí.
            </div>

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
    </div>

    <!-- ============ Textos libres ============ -->
    <div class="tarjeta">
        <h2>Observaciones y condiciones</h2>
        <div class="grid" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones"><?= e($val('observaciones')) ?></textarea>
            </div>
            <div>
                <label for="condiciones">Condiciones</label>
                <textarea id="condiciones" name="condiciones"><?= e($val('condiciones')) ?></textarea>
            </div>
        </div>
    </div>

    <!-- ============ Datos del emisor ============ -->
    <?php
        // Incrustada como data URI: pdf/ esta bloqueada por .htaccess para que
        // la firma no se pueda descargar desde internet.
        $firmaImagen = firmaDataUri($empresaConfig['firma']['imagen'] ?? null);
    ?>
    <div class="tarjeta">
        <h2>
            <?= icono('empresa', 15) ?>Datos del emisor
            <span class="pista">— vienen precargados; si los cambias, se guardan con esta cotización</span>
        </h2>

        <div class="grid" style="grid-template-columns:1fr 1.2fr 1fr">
            <div>
                <label for="emisor_razon_social">Razón social</label>
                <input type="text" id="emisor_razon_social" name="emisor_razon_social"
                       value="<?= e($val('emisor_razon_social') ?: $empresaConfig['razon_social']) ?>">

                <label for="emisor_ruc" style="margin-top:12px">RUC</label>
                <input type="text" id="emisor_ruc" name="emisor_ruc" maxlength="11"
                       value="<?= e($val('emisor_ruc') ?: $empresaConfig['ruc']) ?>">
            </div>

            <div>
                <label>Cuentas bancarias</label>
                <?php foreach ($empresaConfig['cuentas'] as $cuenta): ?>
                    <div class="fijo fijo-fila">
                        <span><?= e($cuenta['banco']) ?></span>
                        <strong><?= e($cuenta['numero']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <label>Firma</label>
                <div class="fijo fijo-firma">
                    <?php if ($firmaImagen !== null): ?>
                        <img src="<?= e($firmaImagen) ?>" alt="Firma" class="firma-preview">
                    <?php else: ?>
                        <span class="pista">Sin imagen de firma</span>
                    <?php endif; ?>
                    <div class="fijo-linea"></div>
                    <strong><?= e($empresaConfig['firma']['nombre']) ?></strong><br>
                    <?= e($empresaConfig['firma']['cargo']) ?><br>
                    <?= e($empresaConfig['firma']['email']) ?><br>
                    <?= e($empresaConfig['firma']['celular']) ?>
                </div>
            </div>
        </div>

        <p class="pista" style="margin:14px 0 0">
            Razón social y RUC se editan aquí y quedan guardados en esta cotización.
            Para cambiar el valor que viene precargado por defecto —y las cuentas
            bancarias y la firma— se edita <code>config/empresa.php</code>.
        </p>
    </div>

    <!-- Barra fija: el formulario es largo y el boton de guardar quedaba a
         varias pantallas de scroll. Ademas repite el total, que es el dato
         que se mira antes de confirmar. -->
    <div class="barra-guardar">
        <div class="barra-guardar-total">
            <span>Total de la cotización</span>
            <strong><span x-text="simbolo"></span> <span x-text="f(clienteTotal)"></span></strong>
        </div>

        <div class="barra-guardar-acciones">
            <?php if (!empty($enModal)): ?>
                <?php // Dentro del modal, cancelar lo cierra en vez de navegar. ?>
                <button type="button" class="btn" @click="$dispatch('cerrar-edicion')">
                    <?= icono('equis') ?> Cancelar
                </button>
            <?php else: ?>
                <a class="btn" href="<?= e($editando ? url('ver', ['id' => $cotizacion['id']]) : url()) ?>">
                    <?= icono('equis') ?> Cancelar
                </a>
            <?php endif; ?>
            <?php // Deshabilitado mientras haya errores o el envio este en curso. ?>
            <button type="submit" class="btn btn-primario"
                    :disabled="!puedeGuardar"
                    :title="puedeGuardar ? '' : (errorItems || 'Falta completar la empresa o corregir el RUC')">
                <span x-show="!enviando"><?= icono('guardar') ?></span>
                <span x-show="enviando" x-cloak style="display:none" class="girando"><?= icono('flecha') ?></span>
                <span x-text="enviando
                    ? 'Guardando…'
                    : '<?= $editando ? 'Actualizar cotización' : 'Guardar cotización' ?>'"></span>
            </button>
        </div>
    </div>
</form>
