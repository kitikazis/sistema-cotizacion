<?php
/** @var array $cotizaciones */
?>
<div class="tarjeta">
    <h2>Cotizaciones emitidas</h2>

    <?php if ($cotizaciones === []): ?>
        <div class="vacio">
            <p>Todavía no hay cotizaciones registradas.</p>
            <a class="btn btn-primario" href="<?= e(url('crear')) ?>">Crear la primera</a>
        </div>
    <?php else: ?>
        <div class="tabla-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Empresa</th>
                        <th>RUC</th>
                        <th>Emisión</th>
                        <th class="num">Ítems</th>
                        <th class="num">Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cotizaciones as $c): ?>
                    <tr>
                        <td><strong><?= e($c['numero']) ?></strong></td>
                        <td><?= e($c['empresa']) ?></td>
                        <td><?= e($c['ruc'] ?? '—') ?></td>
                        <td><?= e(date('d/m/Y', strtotime((string) $c['fecha_emision']))) ?></td>
                        <td class="num"><?= (int) $c['items'] ?></td>
                        <td class="num">
                            <?= e(simboloMoneda((string) $c['moneda'])) ?> <?= money($c['cliente_total']) ?>
                        </td>
                        <td><span class="etiqueta"><?= e($c['estado']) ?></span></td>
                        <td>
                            <a class="btn btn-sm" href="<?= e(url('ver', ['id' => $c['id']])) ?>">Ver</a>
                            <a class="btn btn-sm" href="<?= e(url('pdf', ['id' => $c['id'], 'descargar' => 1])) ?>">PDF</a>
                            <form method="post" action="<?= e(url('eliminar')) ?>" style="display:inline"
                                  onsubmit="return confirm('¿Eliminar la cotización N° <?= e($c['numero']) ?>?')">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-peligro">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
