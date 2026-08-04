<?php
/**
 * Genera el PDF de una cotizacion con dompdf.
 *
 * Reproduce el Bloque 1 del Excel: el cliente ve P.UNIT sin IGV, el subtotal,
 * el IGV al 18% y el total. El desglose interno (IR, detraccion, ganancia,
 * retencion) NUNCA sale en este documento.
 */

require_once __DIR__ . '/../helpers/funciones.php';

/**
 * @param array $cotizacion Cabecera + items, tal como los devuelve Cotizacion::obtener().
 * @param bool  $descargar  true = attachment, false = inline en el navegador.
 */
function generarPdfCotizacion(array $cotizacion, bool $descargar = true): void
{
    $empresa = configEmpresa();

    $html = construirHtmlCotizacion($cotizacion, $empresa);

    $opciones = new \Dompdf\Options();
    $opciones->set('defaultFont', 'DejaVu Sans');   // soporta tildes y ñ
    $opciones->set('isRemoteEnabled', false);       // no cargamos recursos externos
    $opciones->set('isHtml5ParserEnabled', true);
    $opciones->set('chroot', dirname(__DIR__));

    $dompdf = new \Dompdf\Dompdf($opciones);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nombre = 'Cotizacion-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $cotizacion['numero']) . '.pdf';

    // Sin buffers colgando, o el PDF sale corrupto.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $dompdf->stream($nombre, ['Attachment' => $descargar]);
    exit;
}

/**
 * Arma el HTML del documento. dompdf no soporta flexbox ni grid,
 * asi que la maquetacion va con tablas a proposito.
 */
function construirHtmlCotizacion(array $cotizacion, array $empresa): string
{
    $simbolo   = simboloMoneda((string) $cotizacion['moneda']);
    $firma     = $empresa['firma'];
    $fecha     = date('d/m/Y', strtotime((string) $cotizacion['fecha_emision']));

    $formaPago = ucfirst((string) $cotizacion['forma_pago']);
    if ($cotizacion['forma_pago'] === 'credito' && $cotizacion['credito_dias']) {
        $formaPago .= ' a ' . (int) $cotizacion['credito_dias'] . ' días';
    }

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28mm 14mm 20mm 14mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #1f2937; }

    .titulo { font-size: 16pt; font-weight: bold; color: #1e40af; margin: 0 0 2px; }
    .emisor { font-size: 8pt; color: #6b7280; margin: 0 0 14px; }

    table { width: 100%; border-collapse: collapse; }
    .datos td { padding: 2px 0; vertical-align: top; font-size: 9pt; }
    .datos .etq { font-weight: bold; width: 92px; }

    .items { margin-top: 14px; }
    .items th {
        background: #1e40af; color: #fff; padding: 6px 5px;
        font-size: 8pt; text-align: left; text-transform: uppercase;
    }
    .items td { padding: 5px; border-bottom: 1px solid #e5e7eb; font-size: 8.5pt; }
    .num { text-align: right; }

    .totales { margin-top: 10px; width: 44%; float: right; }
    .totales td { padding: 4px 6px; font-size: 9pt; }
    .totales .etq { text-align: right; font-weight: bold; }
    .totales .final td { border-top: 1.5px solid #1f2937; font-size: 11pt; font-weight: bold; padding-top: 6px; }

    .bloque { margin-top: 16px; font-size: 8.5pt; }
    .bloque h3 { font-size: 9pt; margin: 0 0 4px; color: #1e40af; text-transform: uppercase; }
    .caja { border: 1px solid #e5e7eb; padding: 8px; }

    .firma { margin-top: 42px; font-size: 8.5pt; }
    .firma .linea { border-top: 1px solid #1f2937; width: 230px; padding-top: 4px; }
    .nota { font-size: 7.5pt; color: #6b7280; }
    .limpiar { clear: both; }
</style>
</head>
<body>

    <p class="titulo">COTIZACIÓN N° <?= e($cotizacion['numero']) ?></p>
    <p class="emisor">
        <?= e($empresa['razon_social']) ?> · RUC <?= e($empresa['ruc']) ?> · <?= e($empresa['web']) ?>
    </p>

    <!-- Datos del cliente y condiciones, en dos columnas -->
    <table>
        <tr>
            <td style="width:56%; vertical-align:top">
                <table class="datos">
                    <tr><td class="etq">Empresa:</td><td><?= e($cotizacion['empresa']) ?></td></tr>
                    <tr><td class="etq">RUC:</td><td><?= e($cotizacion['ruc'] ?? '—') ?></td></tr>
                    <tr><td class="etq">Dirección:</td><td><?= e($cotizacion['direccion'] ?? '—') ?></td></tr>
                </table>
            </td>
            <td style="width:44%; vertical-align:top">
                <table class="datos">
                    <tr><td class="etq">Fecha de emisión:</td><td><?= e($fecha) ?></td></tr>
                    <tr><td class="etq">Validez de la oferta:</td><td><?= (int) $cotizacion['validez_dias'] ?> días</td></tr>
                    <tr><td class="etq">Forma de pago:</td><td><?= e($formaPago) ?></td></tr>
                    <?php if ($cotizacion['tiempo_entrega_dias']): ?>
                    <tr><td class="etq">Tiempo de entrega:</td><td><?= (int) $cotizacion['tiempo_entrega_dias'] ?> días</td></tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>

    <!-- Items -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">Ítem</th>
                <th style="width:12%">Código</th>
                <th style="width:12%">Marca</th>
                <th style="width:8%" class="num">Cant.</th>
                <th style="width:33%">Descripción</th>
                <th style="width:15%" class="num">P. Unit</th>
                <th style="width:15%" class="num">P. Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cotizacion['items'] as $item): ?>
            <?php $totalFila = (float) $item['precio_cliente_unitario'] * (float) $item['cantidad']; ?>
            <tr>
                <td><?= (int) $item['linea'] ?></td>
                <td><?= e($item['codigo'] ?? '') ?></td>
                <td><?= e($item['marca'] ?? '') ?></td>
                <td class="num"><?= money($item['cantidad'], 0) ?></td>
                <td><?= e($item['descripcion']) ?></td>
                <td class="num"><?= money($item['precio_cliente_unitario']) ?></td>
                <td class="num"><?= money($totalFila) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totales -->
    <table class="totales">
        <tr>
            <td class="etq">Subtotal</td>
            <td class="num"><?= e($simbolo) ?> <?= money($cotizacion['cliente_subtotal']) ?></td>
        </tr>
        <tr>
            <td class="etq">I.G.V (18%)</td>
            <td class="num"><?= e($simbolo) ?> <?= money($cotizacion['cliente_igv']) ?></td>
        </tr>
        <tr class="final">
            <td class="etq">Total</td>
            <td class="num"><?= e($simbolo) ?> <?= money($cotizacion['cliente_total']) ?></td>
        </tr>
    </table>
    <div class="limpiar"></div>

    <p class="nota" style="margin-top:6px">
        Precio en <?= $cotizacion['moneda'] === 'USD' ? 'Dólares Americanos' : 'Soles Peruanos' ?>.
    </p>

    <?php if (!empty($cotizacion['observaciones'])): ?>
    <div class="bloque">
        <h3>Observaciones</h3>
        <div class="caja"><?= nl2br(e($cotizacion['observaciones'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Condiciones y cuentas bancarias -->
    <table class="bloque">
        <tr>
            <td style="width:52%; vertical-align:top; padding-right:10px">
                <h3>Condiciones</h3>
                <div class="caja">
                    <?php if (!empty($cotizacion['condiciones'])): ?>
                        <?= nl2br(e($cotizacion['condiciones'])) ?><br>
                    <?php endif; ?>
                    <?php if ($cotizacion['tiempo_entrega_dias']): ?>
                        Tiempo de entrega: <?= (int) $cotizacion['tiempo_entrega_dias'] ?> días.
                    <?php endif; ?>
                </div>
            </td>
            <td style="width:48%; vertical-align:top">
                <h3>Cuenta bancaria</h3>
                <div class="caja">
                    <table class="datos">
                        <tr><td class="etq">Razón Social</td><td><?= e($empresa['razon_social']) ?></td></tr>
                        <tr><td class="etq">RUC</td><td><?= e($empresa['ruc']) ?></td></tr>
                        <?php foreach ($empresa['cuentas'] as $cuenta): ?>
                        <tr>
                            <td class="etq"><?= e($cuenta['banco']) ?></td>
                            <td><?= e($cuenta['numero']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Firma -->
    <div class="firma">
        <p>Cordialmente,</p>
        <div class="linea">
            <strong><?= e($firma['nombre']) ?></strong><br>
            <?= e($firma['cargo']) ?><br>
            <?= e($firma['email']) ?><br>
            <?= e($firma['celular']) ?>
        </div>
    </div>

</body>
</html>
    <?php
    return (string) ob_get_clean();
}
