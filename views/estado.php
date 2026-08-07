<?php
/**
 * Pantalla de estado del sistema. No usa el layout normal porque tiene
 * que poder mostrarse aunque la base de datos no responda.
 *
 * @var array $bloques
 * @var bool  $todoBien
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del sistema · Enlix Cotizaciones</title>
    <link rel="stylesheet" href="public/assets/css/app.css">
</head>
<body>

    <header class="barra">
        <a class="marca" href="<?= e(url()) ?>"><?= icono('calculadora', 18) ?> Enlix · Cotizaciones</a>
        <nav>
            <a href="<?= e(url()) ?>"><?= icono('volver', 14) ?> Ir al listado</a>
        </nav>
    </header>

    <main class="contenedor" style="max-width:820px">

        <div class="estado-cabecera <?= $todoBien ? 'estado-ok' : 'estado-mal' ?>">
            <?= icono($todoBien ? 'check' : 'alerta', 26) ?>
            <div>
                <strong><?= $todoBien ? 'Todo funcionando' : 'Hay problemas por resolver' ?></strong>
                <span>
                    <?= $todoBien
                        ? 'La base responde y el sistema está listo para usarse.'
                        : 'Revisa abajo lo que está en rojo.' ?>
                </span>
            </div>
        </div>

        <?php foreach ($bloques as $bloque): ?>
            <div class="tarjeta">
                <h2><?= e($bloque['titulo']) ?></h2>

                <table class="tabla-estado">
                    <?php foreach ($bloque['verificaciones'] as $v): ?>
                        <tr>
                            <td class="estado-ico <?= $v['ok'] ? 'si' : 'no' ?>">
                                <?= icono($v['ok'] ? 'check' : 'equis', 15) ?>
                            </td>
                            <td class="estado-nombre"><?= e($v['nombre']) ?></td>
                            <td class="estado-detalle"><?= e($v['detalle']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>

        <p class="pista">
            Esta página se puede abrir en cualquier momento desde
            <code>?accion=estado</code>. No muestra contraseñas: el detalle
            técnico de los errores queda en el log del servidor.
        </p>

    </main>

    <footer class="pie">Módulo de cotizaciones · enlix.pe</footer>

</body>
</html>
