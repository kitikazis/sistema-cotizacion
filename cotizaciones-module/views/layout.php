<?php
/** @var string $titulo */
/** @var string $contenido */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? 'Cotizaciones') ?> · Enlix</title>
    <link rel="stylesheet" href="public/assets/css/app.css">
    <!-- El orden importa: los scripts con defer corren en orden de aparicion y
         cotizacion-form.js debe registrar el listener alpine:init ANTES de que
         Alpine arranque. -->
    <script defer src="public/assets/js/cotizacion-form.js"></script>
    <script defer src="public/assets/js/alpine.min.js"></script>
</head>
<body>
    <header class="barra">
        <a class="marca" href="<?= e(url()) ?>">Enlix · Cotizaciones</a>
        <nav>
            <a href="<?= e(url()) ?>">Listado</a>
            <a class="btn btn-primario" href="<?= e(url('crear')) ?>">Nueva cotización</a>
        </nav>
    </header>

    <main class="contenedor">
        <?= $contenido ?>
    </main>

    <footer class="pie">
        Módulo de cotizaciones · enlix.pe
    </footer>
</body>
</html>
