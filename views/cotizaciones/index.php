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

<!-- ============ Resumen ============ -->
<div class="tarjetas-resumen">
    <div class="resumen">
        <span class="resumen-ico"><?= icono('documento', 20) ?></span>
        <div>
            <span class="resumen-valor"><?= (int) $resumen['cantidad'] ?></span>
            <span class="resumen-etq"><?= $hayFiltros ? 'Cotizaciones encontradas' : 'Cotizaciones totales' ?></span>
        </div>
    </div>
    <div class="resumen">
        <span class="resumen-ico"><?= icono('dinero', 20) ?></span>
        <div>
            <span class="resumen-valor">S/ <?= money($resumen['total_pen']) ?></span>
            <span class="resumen-etq">Monto en soles</span>
        </div>
    </div>
    <div class="resumen">
        <span class="resumen-ico"><?= icono('dinero', 20) ?></span>
        <div>
            <span class="resumen-valor">US$ <?= money($resumen['total_usd']) ?></span>
            <span class="resumen-etq">Monto en dólares</span>
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
<div class="tarjeta" x-data="{ porEliminar: null }">
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
        <div class="tabla-scroll">
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
                        ], JSON_UNESCAPED_UNICODE);
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
                        <td><span class="etiqueta etiqueta-<?= e($c['estado']) ?>"><?= e($estados[$c['estado']] ?? $c['estado']) ?></span></td>
                        <td>
                            <div class="acciones">
                                <a class="btn-ico" title="Ver detalle" href="<?= e(url('ver', ['id' => $c['id']])) ?>"><?= icono('ojo') ?></a>
                                <a class="btn-ico" title="Editar" href="<?= e(url('editar', ['id' => $c['id']])) ?>"><?= icono('lapiz') ?></a>
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
