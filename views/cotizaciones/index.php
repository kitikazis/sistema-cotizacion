<?php
/**
 * @var array $cotizaciones
 * @var array $resumen
 * @var array $filtros
 * @var bool  $hayFiltros
 */

/** Arma el enlace de ordenamiento de una columna, alternando la direccion. */
$ordenar = static function (string $clave) use ($filtros): string {
    $activo  = ($filtros['orden'] ?? '') === $clave;
    $nuevaDir = $activo && strtolower($filtros['dir']) === 'asc' ? 'desc' : 'asc';

    return url('index', array_filter([
        'q'           => $filtros['q'],
        'fecha_desde' => $filtros['fecha_desde'],
        'fecha_hasta' => $filtros['fecha_hasta'],
        'estado'      => $filtros['estado'],
        'moneda'      => $filtros['moneda'],
        'orden'       => $clave,
        'dir'         => $nuevaDir,
    ], static fn($v) => $v !== '' && $v !== null));
};

/** Flecha de la columna ordenada. */
$flecha = static function (string $clave) use ($filtros): string {
    if (($filtros['orden'] ?? '') !== $clave) {
        return '';
    }

    return strtolower($filtros['dir']) === 'asc' ? ' ▲' : ' ▼';
};

$estados = ['borrador' => 'Borrador', 'emitida' => 'Emitida', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'];
?>

<?php
/**
 * Muestra un monto por moneda. Si solo hay soles, no ensucia con un
 * "US$ 0.00" que no aporta nada.
 */
$importe = static function ($pen, $usd): string {
    $partes = [];

    if ((float) $pen != 0.0 || (float) $usd == 0.0) {
        $partes[] = 'S/ ' . money($pen);
    }
    if ((float) $usd != 0.0) {
        $partes[] = 'US$ ' . money($usd);
    }

    return implode(' · ', $partes);
};
?>

<!-- ============ Resumen ============ -->
<div class="tarjetas-resumen">
    <div class="resumen">
        <span class="resumen-ico"><?= icono('documento', 20) ?></span>
        <div>
            <span class="resumen-valor"><?= (int) $resumen['cantidad'] ?></span>
            <span class="resumen-etq">
                <?= $hayFiltros ? 'Encontradas' : 'Cotizaciones' ?>
                <?php if ((int) $resumen['n_borrador'] > 0): ?>
                    · <?= (int) $resumen['n_borrador'] ?> en borrador
                <?php endif; ?>
            </span>
        </div>
    </div>

    <div class="resumen">
        <span class="resumen-ico"><?= icono('documento', 20) ?></span>
        <div>
            <span class="resumen-valor"><?= e($importe($resumen['oferta_pen'], $resumen['oferta_usd'])) ?></span>
            <span class="resumen-etq">
                En oferta · <?= (int) $resumen['n_emitida'] ?>
                <?= (int) $resumen['n_emitida'] === 1 ? 'enviada' : 'enviadas' ?> esperando respuesta
            </span>
        </div>
    </div>

    <div class="resumen">
        <span class="resumen-ico"><?= icono('check', 20) ?></span>
        <div>
            <span class="resumen-valor"><?= e($importe($resumen['ganado_pen'], $resumen['ganado_usd'])) ?></span>
            <span class="resumen-etq">
                Cerrado · <?= (int) $resumen['n_aceptada'] ?>
                <?= (int) $resumen['n_aceptada'] === 1 ? 'aceptada' : 'aceptadas' ?>
            </span>
        </div>
    </div>

    <div class="resumen">
        <span class="resumen-ico"><?= icono('calculadora', 20) ?></span>
        <div>
            <span class="resumen-valor">
                <?= $resumen['tasa_cierre'] === null ? '—' : $resumen['tasa_cierre'] . '%' ?>
            </span>
            <span class="resumen-etq">
                <?php if ($resumen['tasa_cierre'] === null): ?>
                    Tasa de cierre · sin respuestas aún
                <?php else: ?>
                    Tasa de cierre · sobre <?= (int) $resumen['resueltas'] ?> con respuesta
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- ============ Buscador y filtros ============ -->
<div class="tarjeta" x-data="{ abierto: <?= $hayFiltros ? 'true' : 'false' ?> }">
    <form method="get" action="index.php">
        <div class="barra-busqueda">
            <div class="campo-busqueda">
                <span class="icono-campo"><?= icono('buscar', 16) ?></span>
                <input type="search" name="q" value="<?= e($filtros['q']) ?>"
                       placeholder="Buscar por empresa, RUC o número de cotización…">
            </div>

            <button type="submit" class="btn btn-primario"><?= icono('buscar') ?> Buscar</button>

            <button type="button" class="btn" @click="abierto = !abierto"
                    :class="abierto ? 'btn-activo' : ''">
                <?= icono('filtro') ?> Filtros
                <?php if ($hayFiltros): ?><span class="punto"></span><?php endif; ?>
            </button>

            <?php if ($hayFiltros): ?>
                <a class="btn btn-sm" href="<?= e(url()) ?>"><?= icono('equis') ?> Limpiar</a>
            <?php endif; ?>

            <?php
                // Exporta lo que se esta viendo: se arrastran los filtros
                // activos a la url de descarga.
                $paramsExport = array_filter([
                    'q'           => $filtros['q'],
                    'fecha_desde' => $filtros['fecha_desde'],
                    'fecha_hasta' => $filtros['fecha_hasta'],
                    'estado'      => $filtros['estado'],
                    'moneda'      => $filtros['moneda'],
                    'orden'       => $filtros['orden'],
                    'dir'         => $filtros['dir'],
                ], static fn($v) => $v !== '' && $v !== null);
            ?>
            <a class="btn" href="<?= e(url('exportar', $paramsExport)) ?>"
               title="<?= $hayFiltros ? 'Descarga solo lo filtrado' : 'Descarga todo el listado' ?>">
                <?= icono('descargar') ?> Excel
            </a>
        </div>

        <div class="panel-filtros" x-show="abierto" x-cloak x-transition>
            <div class="grid grid-4">
                <div>
                    <label for="fecha_desde"><?= icono('calendario', 13) ?> Emitidas desde</label>
                    <input type="date" id="fecha_desde" name="fecha_desde" value="<?= e($filtros['fecha_desde']) ?>">
                </div>
                <div>
                    <label for="fecha_hasta"><?= icono('calendario', 13) ?> Emitidas hasta</label>
                    <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?= e($filtros['fecha_hasta']) ?>">
                </div>
                <div>
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $valor => $texto): ?>
                            <option value="<?= e($valor) ?>"<?= $filtros['estado'] === $valor ? ' selected' : '' ?>>
                                <?= e($texto) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="moneda">Moneda</label>
                    <select id="moneda" name="moneda">
                        <option value="">Todas</option>
                        <option value="PEN"<?= $filtros['moneda'] === 'PEN' ? ' selected' : '' ?>>Soles (S/)</option>
                        <option value="USD"<?= $filtros['moneda'] === 'USD' ? ' selected' : '' ?>>Dólares (US$)</option>
                    </select>
                </div>
            </div>
            <p style="margin:12px 0 0">
                <button type="submit" class="btn btn-primario"><?= icono('check') ?> Aplicar filtros</button>
            </p>
        </div>
    </form>
</div>

<!-- ============ Tabla ============ -->
<?php // x-effect: congela el scroll del fondo mientras haya un modal abierto. ?>
<div class="tarjeta"
     x-effect="document.body.classList.toggle('sin-scroll', !!(detalle || editando || porEliminar))"
     x-data="{
        porEliminar: null,
        detalle: null,
        html: '',
        cargando: false,

        async verDetalle(c) {
            this.detalle = c;
            this.html = '';
            this.cargando = true;

            try {
                const r = await fetch('index.php?accion=detalle&id=' + c.id, {
                    headers: { 'X-Requested-With': 'fetch' }
                });
                this.html = r.ok
                    ? await r.text()
                    : '<p class=&quot;aviso aviso-error&quot;>No se pudo cargar el detalle.</p>';
            } catch (e) {
                this.html = '<p class=&quot;aviso aviso-error&quot;>Sin conexión con el servidor.</p>';
            }

            this.cargando = false;
        },

        // ---- Alta y edicion en modal ----
        // Un solo modal para las dos: el formulario es el mismo, solo
        // cambian el titulo y de donde se carga.
        editando: null,
        htmlEdicion: '',
        guardando: false,

        init() {
            // Lo usa el boton de alta de la cabecera, que vive fuera de
            // este componente. Ojo: nada de comillas dobles aqui dentro,
            // cierran el atributo x-data y el resto se imprime como texto.
            window.abrirNuevaCotizacion = () => this.abrirNueva();
        },

        // ---- Estado de la cotizacion ----
        // Se cambia a mano: marcar como emitida al descargar el PDF la
        // daria por enviada aunque solo se estuviera revisando.
        cambiandoEstado: false,

        async cambiarEstado(id, estado) {
            this.cambiandoEstado = true;

            const datos = new FormData();
            datos.append('csrf', <?= e(json_encode(tokenCsrf())) ?>);
            datos.append('id', id);
            datos.append('estado', estado);

            try {
                const r = await fetch('index.php?accion=estadocot', { method: 'POST', body: datos });

                if (r.ok) {
                    location.reload();
                    return;
                }

                alert('No se pudo cambiar el estado (HTTP ' + r.status + ').');
            } catch (e) {
                alert('Sin conexión con el servidor.');
            }

            this.cambiandoEstado = false;
        },

        abrirNueva() {
            this.cargarFormulario(
                { titulo: 'Nueva cotización', subtitulo: 'Se asigna el siguiente correlativo' },
                'index.php?accion=crearfrag'
            );
        },

        abrirEdicion(c) {
            this.cargarFormulario(
                { titulo: 'Editar cotización N° ' + c.numero, subtitulo: c.empresa },
                'index.php?accion=editarfrag&id=' + c.id
            );
        },

        async cargarFormulario(cabecera, url) {
            this.editando = cabecera;
            this.htmlEdicion = '';
            this.cargando = true;

            try {
                const r = await fetch(url);
                this.htmlEdicion = r.ok
                    ? await r.text()
                    : '<p class=&quot;aviso aviso-error&quot;>No se pudo cargar el formulario.</p>';
            } catch (e) {
                this.htmlEdicion = '<p class=&quot;aviso aviso-error&quot;>Sin conexión con el servidor.</p>';
            }

            this.cargando = false;
        },

        // El formulario apunta a ?accion=actualizar y termina en redireccion.
        // Se envia por fetch para no salir del listado: si responde bien se
        // recarga la pagina y los totales quedan al dia.
        async guardarEdicion(ev) {
            const formulario = ev.target;

            if (!formulario.reportValidity()) {
                return;
            }

            this.guardando = true;

            try {
                const r = await fetch(formulario.action, {
                    method: 'POST',
                    body: new FormData(formulario),
                });

                if (r.ok) {
                    location.reload();
                    return;
                }

                alert('No se pudo guardar (HTTP ' + r.status + ').');
            } catch (e) {
                alert('Sin conexión con el servidor.');
            }

            this.guardando = false;
        }
     }">
    <h2><?= icono('documento', 15) ?> Cotizaciones emitidas</h2>

    <?php if ($cotizaciones === []): ?>
        <div class="vacio">
            <?= icono($hayFiltros ? 'buscar' : 'documento', 40, 'ico-vacio') ?>
            <?php if ($hayFiltros): ?>
                <p>Ninguna cotización coincide con la búsqueda.</p>
                <a class="btn" href="<?= e(url()) ?>"><?= icono('equis') ?> Limpiar filtros</a>
            <?php else: ?>
                <p>Todavía no hay cotizaciones registradas.</p>
                <a class="btn btn-primario" href="<?= e(url('crear')) ?>"><?= icono('mas') ?> Crear la primera</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="tabla-scroll tabla-sangre">
            <table class="tabla-listado">
                <thead>
                    <tr>
                        <th><a class="orden" href="<?= e($ordenar('numero')) ?>">N°<?= $flecha('numero') ?></a></th>
                        <th><a class="orden" href="<?= e($ordenar('cliente')) ?>">Empresa<?= $flecha('cliente') ?></a></th>
                        <th>RUC</th>
                        <th><a class="orden" href="<?= e($ordenar('fecha')) ?>">Emisión<?= $flecha('fecha') ?></a></th>
                        <th class="num">Ítems</th>
                        <th class="num"><a class="orden" href="<?= e($ordenar('total')) ?>">Total<?= $flecha('total') ?></a></th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cotizaciones as $c): ?>
                    <?php
                        // Los datos que necesita el modal, serializados a un
                        // objeto JS. json_encode escapa comillas y acentos:
                        // una razon social con apostrofe rompería el atributo.
                        $paraModal = json_encode([
                            'id'      => (int) $c['id'],
                            'numero'  => $c['numero'],
                            'empresa' => $c['razon_social'],
                            'ruc'     => $c['ruc'] ?? '—',
                            'fecha'   => date('d/m/Y', strtotime((string) $c['fecha_emision'])),
                            'items'   => (int) $c['items'],
                            'total'   => simboloMoneda((string) $c['moneda']) . ' ' . money($c['cliente_total']),
                            'estado'  => $c['estado'],
                        ], JSON_UNESCAPED_UNICODE);

                        // Se calcula al vuelo: el estado guardado no cambia solo.
                        $vencida = Cotizacion::estaVencida($c);
                    ?>
                    <tr>
                        <td><span class="folio"><?= e($c['numero']) ?></span></td>
                        <td>
                            <span class="con-ico"><?= icono('empresa', 14, 'ico-tenue') ?> <?= e($c['razon_social']) ?></span>
                        </td>
                        <td class="mono tenue"><?= e($c['ruc'] ?? '—') ?></td>
                        <td class="tenue"><?= e(date('d/m/Y', strtotime((string) $c['fecha_emision']))) ?></td>
                        <td class="num tenue"><?= (int) $c['items'] ?></td>
                        <td class="num">
                            <strong class="monto"><?= e(simboloMoneda((string) $c['moneda'])) ?> <?= money($c['cliente_total']) ?></strong>
                        </td>
                        <td>
                            <span class="etiqueta etiqueta-<?= e($c['estado']) ?>">
                                <?= e($estados[$c['estado']] ?? $c['estado']) ?>
                            </span>
                            <?php if ($vencida): ?>
                                <span class="etiqueta etiqueta-vencida" title="Se pasó la fecha de validez">
                                    Vencida
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="acciones">
                                <button type="button" class="btn-ico" title="Ver detalle"
                                        @click="verDetalle(<?= e($paraModal) ?>)">
                                    <?= icono('ojo') ?>
                                </button>
                                <button type="button" class="btn-ico" title="Editar"
                                        @click="abrirEdicion(<?= e($paraModal) ?>)">
                                    <?= icono('lapiz') ?>
                                </button>
                                <a class="btn-ico" title="Descargar PDF" href="<?= e(url('pdf', ['id' => $c['id'], 'descargar' => 1])) ?>"><?= icono('descargar') ?></a>
                                <button type="button" class="btn-ico btn-ico-peligro" title="Eliminar"
                                        @click="porEliminar = <?= e($paraModal) ?>">
                                    <?= icono('basura') ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============ Detalle de la cotización ============ -->
    <div class="modal-fondo" style="display:none" x-show="detalle" x-cloak x-transition.opacity
         @click.self="detalle = null"
         @keydown.escape.window="detalle = null"
         role="dialog" aria-modal="true">

        <div class="modal modal-ancho" x-show="detalle" x-transition>
            <div class="modal-cabecera modal-cabecera-neutra">
                <span class="modal-ico modal-ico-azul"><?= icono('documento', 20) ?></span>
                <div>
                    <strong>Cotización N° <span x-text="detalle?.numero"></span></strong>
                    <span x-text="detalle?.empresa"></span>
                </div>
                <button type="button" class="modal-cerrar" @click="detalle = null" title="Cerrar">
                    <?= icono('equis', 18) ?>
                </button>
            </div>

            <div class="modal-cuerpo modal-cuerpo-scroll">
                <template x-if="cargando">
                    <p class="det-cargando">Cargando detalle…</p>
                </template>
                <div x-show="!cargando" x-html="html"></div>
            </div>

            <div class="modal-pie modal-pie-estado">
                <?php
                    // Acciones de estado, segun donde este la cotizacion en su
                    // ciclo. Solo se ofrece el paso siguiente y la vuelta atras,
                    // para no llenar el pie de botones.
                ?>
                <div class="acciones-estado">
                    <template x-if="detalle?.estado === 'borrador'">
                        <button type="button" class="btn btn-estado-emitir"
                                :disabled="cambiandoEstado"
                                @click="cambiarEstado(detalle.id, 'emitida')">
                            <?= icono('documento') ?> Marcar como enviada
                        </button>
                    </template>

                    <template x-if="detalle?.estado === 'emitida'">
                        <span class="acciones-estado">
                            <button type="button" class="btn btn-estado-aceptar"
                                    :disabled="cambiandoEstado"
                                    @click="cambiarEstado(detalle.id, 'aceptada')">
                                <?= icono('check') ?> Aceptada
                            </button>
                            <button type="button" class="btn btn-estado-rechazar"
                                    :disabled="cambiandoEstado"
                                    @click="cambiarEstado(detalle.id, 'rechazada')">
                                <?= icono('equis') ?> Rechazada
                            </button>
                        </span>
                    </template>

                    <template x-if="detalle && detalle.estado !== 'borrador'">
                        <button type="button" class="btn btn-sm btn-volver-borrador"
                                :disabled="cambiandoEstado"
                                @click="cambiarEstado(detalle.id, 'borrador')"
                                title="Deshacer: la devuelve a borrador">
                            <?= icono('volver') ?> Volver a borrador
                        </button>
                    </template>
                </div>

                <button type="button" class="btn" @click="detalle = null">Cerrar</button>
                <?php
                    // Sin boton Editar: sacaba del listado hacia la pantalla
                    // completa, ahora que la edicion tiene su propio modal.
                    // Para editar se usa el lapiz de la fila.
                    // Sin descargar=1 el PDF se sirve inline y el navegador lo abre.
                ?>
                <a class="btn" target="_blank" rel="noopener"
                   :href="'index.php?accion=pdf&id=' + detalle?.id">
                    <?= icono('ojo') ?> Ver PDF
                </a>
                <a class="btn btn-primario" :href="'index.php?accion=pdf&descargar=1&id=' + detalle?.id">
                    <?= icono('descargar') ?> Descargar PDF
                </a>
            </div>
        </div>
    </div>

    <!-- ============ Edición ============ -->
    <div class="modal-fondo" style="display:none" x-show="editando" x-cloak x-transition.opacity
         @keydown.escape.window="editando = null"
         role="dialog" aria-modal="true">

        <div class="modal modal-completo" x-show="editando" x-transition>
            <div class="modal-cabecera modal-cabecera-neutra">
                <span class="modal-ico modal-ico-azul"><?= icono('lapiz', 20) ?></span>
                <div>
                    <strong x-text="editando?.titulo"></strong>
                    <span x-text="editando?.subtitulo"></span>
                </div>
                <button type="button" class="modal-cerrar" @click="editando = null" title="Cerrar">
                    <?= icono('equis', 18) ?>
                </button>
            </div>

            <?php // El submit del formulario inyectado burbujea hasta aqui. ?>
            <div class="modal-cuerpo modal-cuerpo-form"
                 @submit.prevent="guardarEdicion($event)"
                 @cerrar-edicion="editando = null">
                <template x-if="cargando">
                    <p class="det-cargando">Cargando formulario…</p>
                </template>
                <div x-show="!cargando" x-html="htmlEdicion"></div>
            </div>

            <div class="modal-velo" x-show="guardando" x-cloak style="display:none">
                <p>Guardando…</p>
            </div>
        </div>
    </div>

    <!-- ============ Confirmación de borrado ============ -->
    <!-- El display:none va en linea a proposito: si el navegador sirviera un
         CSS cacheado sin la regla de x-cloak, el modal apareceria desplegado
         dentro de la pagina. Alpine lo sobreescribe al abrirlo. -->
    <div class="modal-fondo" style="display:none" x-show="porEliminar" x-cloak x-transition.opacity
         @click.self="porEliminar = null"
         @keydown.escape.window="porEliminar = null"
         role="dialog" aria-modal="true" aria-labelledby="titulo-eliminar">

        <div class="modal" x-show="porEliminar" x-transition>
            <div class="modal-cabecera">
                <span class="modal-ico"><?= icono('alerta', 20) ?></span>
                <div>
                    <strong id="titulo-eliminar">Eliminar cotización</strong>
                    <span>Esta acción no se puede deshacer.</span>
                </div>
            </div>

            <div class="modal-cuerpo">
                <p>Se va a eliminar de forma permanente:</p>

                <table class="modal-datos">
                    <tr>
                        <td>Número</td>
                        <td><strong x-text="porEliminar?.numero"></strong></td>
                    </tr>
                    <tr>
                        <td>Empresa</td>
                        <td><strong x-text="porEliminar?.empresa"></strong></td>
                    </tr>
                    <tr>
                        <td>RUC</td>
                        <td x-text="porEliminar?.ruc"></td>
                    </tr>
                    <tr>
                        <td>Emisión</td>
                        <td x-text="porEliminar?.fecha"></td>
                    </tr>
                    <tr>
                        <td>Total</td>
                        <td><strong x-text="porEliminar?.total"></strong></td>
                    </tr>
                </table>

                <p class="pista" style="margin-top:12px">
                    También se borrarán sus
                    <strong x-text="porEliminar?.items"></strong>
                    <span x-text="porEliminar?.items === 1 ? 'ítem' : 'ítems'"></span>.
                    El cliente se conserva, porque puede tener otras cotizaciones.
                </p>
            </div>

            <div class="modal-pie">
                <button type="button" class="btn" @click="porEliminar = null">
                    <?= icono('equis') ?> Cancelar
                </button>

                <form method="post" action="<?= e(url('eliminar')) ?>">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" :value="porEliminar?.id">
                    <button type="submit" class="btn btn-peligro-solido">
                        <?= icono('basura') ?> Sí, eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
