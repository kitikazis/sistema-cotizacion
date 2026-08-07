<?php
/**
 * Pantalla de acceso. No usa el layout normal: sin barra de navegacion,
 * porque todavia no hay sesion.
 *
 * @var string      $titulo
 * @var string|null $error
 * @var bool        $sinUsuarios
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · Enlix Cotizaciones</title>
    <link rel="stylesheet" href="<?= e(asset('public/assets/css/app.css')) ?>">
</head>
<body class="pantalla-login">

    <main class="caja-login">
        <div class="login-marca">
            <?= icono('calculadora', 26) ?>
            <span>Enlix · Cotizaciones</span>
        </div>

        <?php if ($sinUsuarios): ?>
            <div class="aviso aviso-info">
                No hay ningún usuario creado todavía. Crea el primero desde la
                consola del servidor:
                <code>php tools/crear_usuario.php</code>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="aviso aviso-error"><?= icono('alerta', 14) ?> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('entrar')) ?>">
            <?= campoCsrf() ?>

            <div style="margin-bottom:14px">
                <label for="usuario"><?= icono('usuario', 13) ?> Usuario</label>
                <input type="text" id="usuario" name="usuario" required autofocus
                       autocomplete="username" autocapitalize="none" spellcheck="false">
            </div>

            <div style="margin-bottom:20px">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" required
                       autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primario" style="width:100%; justify-content:center">
                Entrar
            </button>
        </form>
    </main>

    <p class="pie">Módulo de cotizaciones · enlix.pe</p>

</body>
</html>
