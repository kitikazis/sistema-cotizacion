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
    $simbolo = simboloMoneda((string) $cotizacion['moneda']);
    $firma   = $empresa['firma'];
    $fecha   = date('d/m/Y', strtotime((string) $cotizacion['fecha_emision']));

    // Fecha hasta la que la oferta sigue vigente.
    $vence = date(
        'd/m/Y',
        strtotime((string) $cotizacion['fecha_emision'] . ' +' . (int) $cotizacion['validez_dias'] . ' days')
    );

    // El emisor guardado con la cotizacion manda sobre el de config: asi un
    // documento emitido hace meses conserva la razon social y el RUC de
    // entonces. Las cotizaciones anteriores a este campo tienen NULL y caen
    // al valor de configuracion.
    $emisorRazonSocial = trim((string) ($cotizacion['emisor_razon_social'] ?? '')) !== ''
        ? (string) $cotizacion['emisor_razon_social']
        : (string) $empresa['razon_social'];

    $emisorRuc = trim((string) ($cotizacion['emisor_ruc'] ?? '')) !== ''
        ? (string) $cotizacion['emisor_ruc']
        : (string) $empresa['ruc'];

    $formaPago = ucfirst((string) $cotizacion['forma_pago']);
    if ($cotizacion['forma_pago'] === 'credito' && $cotizacion['credito_dias']) {
        $formaPago .= ' a ' . (int) $cotizacion['credito_dias'] . ' días';
    }

    // Firma escaneada. Es opcional: si el archivo no esta, el PDF sale igual
    // con la linea de firma en blanco en vez de romperse.
    $rutaFirma = null;
    if (!empty($firma['imagen'])) {
        $candidata = __DIR__ . '/../' . ltrim((string) $firma['imagen'], '/');
        $absoluta  = realpath($candidata);

        if ($absoluta !== false && is_file($absoluta)) {
            $rutaFirma = $absoluta;
        } else {
            error_log('generar_pdf: no se encontro la imagen de firma en ' . $candidata);
        }
    }

    $moneda = $cotizacion['moneda'] === 'USD' ? 'Dólares Americanos' : 'Soles Peruanos';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 14mm 13mm 20mm 13mm; }

    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 8.6pt;
        color: #1f2937;
        line-height: 1.35;
    }

    table { width: 100%; border-collapse: collapse; }

    /* ---------- Cabecera ---------- */
    .cabecera { border-bottom: 2.5pt solid #1e40af; padding-bottom: 7pt; margin-bottom: 12pt; }
    .marca { font-size: 17pt; font-weight: bold; color: #1e40af; letter-spacing: -0.3pt; }
    .marca-sub { font-size: 7.6pt; color: #6b7280; margin-top: 1pt; }
    .folio {
        background: #1e40af; color: #fff;
        padding: 6pt 12pt; text-align: center; border-radius: 3pt;
    }
    .folio-etq { font-size: 6.8pt; text-transform: uppercase; letter-spacing: 0.8pt; opacity: .85; }
    .folio-num { font-size: 15pt; font-weight: bold; }

    /* ---------- Bloques de datos ---------- */
    .panel { border: 0.6pt solid #e5e7eb; border-radius: 3pt; }
    .panel-tit {
        background: #f3f4f6; padding: 3.5pt 7pt;
        font-size: 6.9pt; font-weight: bold; text-transform: uppercase;
        letter-spacing: 0.6pt; color: #4b5563;
        border-bottom: 0.6pt solid #e5e7eb;
    }
    .panel-cuerpo { padding: 6pt 7pt; }
    .campo { padding: 1.4pt 0; }
    .campo-etq { color: #6b7280; font-size: 7.6pt; }
    .campo-val { font-weight: bold; }

    /* ---------- Items ---------- */
    .items { margin-top: 11pt; }
    .items th {
        background: #1e40af; color: #fff;
        padding: 5.5pt 5pt; font-size: 7.2pt;
        text-align: left; text-transform: uppercase; letter-spacing: 0.4pt;
    }
    .items td { padding: 5pt; border-bottom: 0.6pt solid #eef0f3; font-size: 8.4pt; }
    .items tr.par td { background: #fafbfc; }
    .items .desc { font-weight: bold; }
    .items .meta { color: #6b7280; font-size: 7.4pt; }
    .num { text-align: right; }
    .cen { text-align: center; }

    /* ---------- Totales ---------- */
    .tot td { padding: 3.5pt 8pt; font-size: 8.8pt; }
    .tot .etq { text-align: right; color: #4b5563; }
    .tot .val { text-align: right; font-weight: bold; width: 34%; }
    .tot .final td {
        background: #1e40af; color: #fff;
        font-size: 11pt; font-weight: bold; padding: 6pt 8pt;
    }

    /* ---------- Pie ---------- */
    .bloque { margin-top: 11pt; }
    .texto-libre { font-size: 8pt; }
    .cuentas td { padding: 2pt 0; font-size: 7.8pt; }
    .cuentas .banco { color: #6b7280; }
    .cuentas .nro { text-align: right; font-weight: bold; }

    .firma-caja { margin-top: 26pt; }
    .firma-img { height: 46pt; margin-left: 10pt; margin-bottom: -4pt; }
    .firma-linea { border-top: 0.8pt solid #374151; width: 200pt; padding-top: 3pt; }
    .firma-nom { font-weight: bold; }
    .firma-dato { color: #4b5563; font-size: 7.8pt; }

    .nota { font-size: 7.2pt; color: #6b7280; }
    .pie-legal {
        margin-top: 14pt; padding-top: 5pt;
        border-top: 0.6pt solid #e5e7eb;
        font-size: 6.9pt; color: #9ca3af; text-align: center;
    }
</style>
</head>
<body>

    <!-- ================= Cabecera ================= -->
    <div class="cabecera">
        <table>
            <tr>
                <td style="vertical-align:top">
                    <div class="marca"><?= e($emisorRazonSocial) ?></div>
                    <div class="marca-sub">
                        RUC <?= e($emisorRuc) ?> &nbsp;·&nbsp; <?= e($empresa['web']) ?>
                    </div>
                </td>
                <td style="width:34%; vertical-align:top">
                    <div class="folio">
                        <div class="folio-etq">Cotización</div>
                        <div class="folio-num">N° <?= e($cotizacion['numero']) ?></div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ================= Cliente y condiciones ================= -->
    <table>
        <tr>
            <td style="width:49%; vertical-align:top; padding-right:6pt">
                <div class="panel">
                    <div class="panel-tit">Cliente</div>
                    <div class="panel-cuerpo">
                        <div class="campo"><span class="campo-val" style="font-size:9.4pt"><?= e($cotizacion['empresa']) ?></span></div>
                        <div class="campo"><span class="campo-etq">RUC:</span> <?= e($cotizacion['ruc'] ?? '—') ?></div>
                        <div class="campo"><span class="campo-etq">Dirección:</span> <?= e($cotizacion['direccion'] ?? '—') ?></div>
                    </div>
                </div>
            </td>
            <td style="width:51%; vertical-align:top">
                <div class="panel">
                    <div class="panel-tit">Condiciones</div>
                    <div class="panel-cuerpo">
                        <table>
                            <tr>
                                <td class="campo"><span class="campo-etq">Emisión:</span> <span class="campo-val"><?= e($fecha) ?></span></td>
                                <td class="campo"><span class="campo-etq">Válida hasta:</span> <span class="campo-val"><?= e($vence) ?></span></td>
                            </tr>
                            <tr>
                                <td class="campo"><span class="campo-etq">Pago:</span> <span class="campo-val"><?= e($formaPago) ?></span></td>
                                <td class="campo">
                                    <span class="campo-etq">Entrega:</span>
                                    <span class="campo-val"><?= $cotizacion['tiempo_entrega_dias'] ? (int) $cotizacion['tiempo_entrega_dias'] . ' días' : '—' ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="campo" colspan="2"><span class="campo-etq">Moneda:</span> <span class="campo-val"><?= e($moneda) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= Items ================= -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:6%" class="cen">Ítem</th>
                <th style="width:47%">Descripción</th>
                <th style="width:9%" class="cen">Cant.</th>
                <th style="width:19%" class="num">P. Unitario</th>
                <th style="width:19%" class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cotizacion['items'] as $i => $item): ?>
            <?php
                $totalFila = (float) $item['precio_cliente_unitario'] * (float) $item['cantidad'];

                // Codigo y marca van como subtitulo: liberan dos columnas y
                // dejan mucho mas aire para la descripcion.
                $meta = array_filter([
                    $item['codigo'] ?? null,
                    $item['marca'] ?? null,
                ]);
            ?>
            <tr class="<?= $i % 2 === 1 ? 'par' : '' ?>">
                <td class="cen"><?= (int) $item['linea'] ?></td>
                <td>
                    <span class="desc"><?= e($item['descripcion']) ?></span>
                    <?php if ($meta !== []): ?>
                        <br><span class="meta"><?= e(implode(' · ', $meta)) ?></span>
                    <?php endif; ?>
                </td>
                <td class="cen"><?= money($item['cantidad'], 0) ?></td>
                <td class="num"><?= e($simbolo) ?> <?= money($item['precio_cliente_unitario']) ?></td>
                <td class="num"><?= e($simbolo) ?> <?= money($totalFila) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ================= Totales ================= -->
    <table style="margin-top:9pt">
        <tr>
            <td style="width:56%; vertical-align:top; padding-right:8pt">
                <?php if (!empty($cotizacion['observaciones'])): ?>
                    <div class="panel">
                        <div class="panel-tit">Observaciones</div>
                        <div class="panel-cuerpo texto-libre"><?= nl2br(e($cotizacion['observaciones'])) ?></div>
                    </div>
                <?php endif; ?>
            </td>
            <td style="width:44%; vertical-align:top">
                <table class="tot">
                    <tr>
                        <td class="etq">Subtotal</td>
                        <td class="val"><?= e($simbolo) ?> <?= money($cotizacion['cliente_subtotal']) ?></td>
                    </tr>
                    <tr>
                        <td class="etq">I.G.V (18%)</td>
                        <td class="val"><?= e($simbolo) ?> <?= money($cotizacion['cliente_igv']) ?></td>
                    </tr>
                    <tr class="final">
                        <td>TOTAL</td>
                        <td class="num"><?= e($simbolo) ?> <?= money($cotizacion['cliente_total']) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= Condiciones y cuentas ================= -->
    <table class="bloque">
        <tr>
            <td style="width:49%; vertical-align:top; padding-right:6pt">
                <div class="panel">
                    <div class="panel-tit">Términos</div>
                    <div class="panel-cuerpo texto-libre">
                        <?php if (!empty($cotizacion['condiciones'])): ?>
                            <?= nl2br(e($cotizacion['condiciones'])) ?><br>
                        <?php endif; ?>
                        Validez de la oferta: <?= (int) $cotizacion['validez_dias'] ?> días.
                        <?php if ($cotizacion['tiempo_entrega_dias']): ?>
                            <br>Tiempo de entrega: <?= (int) $cotizacion['tiempo_entrega_dias'] ?> días.
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td style="width:51%; vertical-align:top">
                <div class="panel">
                    <div class="panel-tit">Cuentas bancarias · <?= e($emisorRazonSocial) ?> · RUC <?= e($emisorRuc) ?></div>
                    <div class="panel-cuerpo">
                        <table class="cuentas">
                            <?php foreach ($empresa['cuentas'] as $cuenta): ?>
                                <tr>
                                    <td class="banco"><?= e($cuenta['banco']) ?></td>
                                    <td class="nro"><?= e($cuenta['numero']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= Firma ================= -->
    <div class="firma-caja">
        <p style="margin:0 0 2pt">Cordialmente,</p>
        <?php if ($rutaFirma !== null): ?>
            <img class="firma-img" src="<?= e($rutaFirma) ?>" alt="">
        <?php endif; ?>
        <div class="firma-linea">
            <span class="firma-nom"><?= e($firma['nombre']) ?></span><br>
            <span class="firma-dato">
                <?= e($firma['cargo']) ?><br>
                <?= e($firma['email']) ?> &nbsp;·&nbsp; <?= e($firma['celular']) ?>
            </span>
        </div>
    </div>

    <div class="pie-legal">
        Precios expresados en <?= e($moneda) ?>, IGV incluido.
        Documento generado el <?= e(date('d/m/Y')) ?>.
    </div>

</body>
</html>
    <?php
    return (string) ob_get_clean();
}
