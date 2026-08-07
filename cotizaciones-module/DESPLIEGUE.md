# Despliegue en cPanel — cotizacion.enlix.pe

Estado del subdominio al momento de escribir esto: resuelve a `192.250.227.240`,
responde **404** (vhost creado, carpeta vacía) y **no tiene SSL**.

## 1. Base de datos

En cPanel → **Bases de datos MySQL**:

1. Crear la base, p. ej. `cotizador`. cPanel le antepone el prefijo de la
   cuenta y queda como `CUENTA_cotizador`.
2. Crear un usuario y **generar una contraseña larga** con el botón de cPanel.
3. Asignar el usuario a la base con **todos los privilegios**.

Luego, en **phpMyAdmin**, seleccionar la base e importar:

```
cotizaciones-module/database/schema.sql
```

> `schema.sql` empieza con `CREATE DATABASE enlix_cotizaciones` y `USE`.
> En cPanel no se pueden crear bases desde SQL: hay que **borrar esas dos
> primeras sentencias** antes de importar, y ejecutar el resto sobre la base
> ya creada.

## 2. Archivos

Subir el **contenido** de `cotizaciones-module/` a la carpeta del subdominio
(`/cotizacion.enlix.pe`), de modo que `index.php` quede en la raíz:

```
/cotizacion.enlix.pe/
├── .htaccess
├── index.php
├── config/  controllers/  helpers/  models/  views/  pdf/  database/
├── public/
└── vendor/
```

Dos archivos **no** están en el repositorio y hay que subirlos aparte:

| Archivo | De dónde sale |
|---|---|
| `config/empresa.php` | copiar de `config/empresa.example.php` y completar |
| `pdf/firma-transparente.png` | la firma escaneada |

Y uno se crea solo en el servidor:

| Archivo | De dónde sale |
|---|---|
| `config/credenciales.php` | copiar de `config/credenciales.example.php` y poner los datos del paso 1 |

### vendor/

Si el hosting tiene Composer (cPanel → Terminal):

```bash
cd ~/cotizacion.enlix.pe && composer install --no-dev --optimize-autoloader
```

Si no lo tiene, subir la carpeta `vendor/` completa por FTP desde tu equipo.

## 3. SSL

cPanel → **SSL/TLS Status** → ejecutar **AutoSSL** sobre `cotizacion.enlix.pe`.

El `.htaccess` **fuerza HTTPS**. Si el certificado todavía no está activo, el
sitio entra en bucle de redirecciones: comentar ese bloque hasta tener el
certificado.

## 4. Verificación

```
https://cotizacion.enlix.pe/                     → listado
https://cotizacion.enlix.pe/config/database.php  → debe dar 404
https://cotizacion.enlix.pe/database/schema.sql  → debe dar 404
https://cotizacion.enlix.pe/pdf/firma-transparente.png → debe dar 404
```

Los tres últimos son la prueba de que `.htaccess` está activo. **Si alguno
devuelve el archivo, parar y revisar** antes de cargar datos reales: significa
que `AllowOverride` está deshabilitado y todo el módulo queda expuesto.

## Qué protege el .htaccess

- 404 en `config/`, `database/`, `models/`, `controllers/`, `helpers/`,
  `tests/`, `vendor/`, `storage/` y `pdf/`
- 404 en `composer.json`, `composer.lock`, `README.md`, `.git`
- Bloqueo por extensión de `.sql`, `.md`, `.log`, `.bak` y `*.example.php`
- Sin listados de directorio
- `display_errors` apagado
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

La firma ya no se sirve por URL: el formulario la incrusta como data URI leída
desde disco, así que bloquear `pdf/` no rompe la vista previa.

## Entornos

`esProduccion()` decide por el host: `127.0.0.1`, `localhost`, `.local` y
`.test` son desarrollo; cualquier otro es producción. En producción los
errores van al log y nunca a pantalla, y las credenciales salen de
`config/credenciales.php`.

## Usuarios

El panel exige login. No hay registro por web a propósito: el primer usuario
se crea desde cPanel → **Terminal**:

```bash
cd ~/cotizacion.enlix.pe
php tools/crear_usuario.php
```

Pide nombre, usuario y contraseña (mínimo 10 caracteres) y la guarda con
`password_hash` / bcrypt coste 12. Si el hosting no tiene Terminal, ejecutarlo
en local contra la base de producción cambiando temporalmente
`config/credenciales.php`.

Además hay que importar la tabla:

```
cotizaciones-module/database/migracion_002_usuarios.sql
```

(o usar `schema.sql`, que ya la incluye).

### Qué protege el login

- Todas las rutas exigen sesión salvo `login` y `entrar`.
- Cookie de sesión `httponly`, `samesite=Lax` y `secure` en producción.
- El id de sesión se regenera al entrar (evita fijación de sesión).
- Token CSRF en guardar, actualizar, eliminar y cerrar sesión; se rechaza
  con **419** si no cuadra.
- Mensaje de error genérico y espera de 400 ms en cada fallo, para no
  revelar qué usuarios existen ni facilitar la fuerza bruta.

### Lo que todavía no tiene

No hay bloqueo por intentos repetidos ni segundo factor. Para una herramienta
interna con pocos usuarios es aceptable, pero si la expones a internet abierta
conviene sumar **Directory Privacy** de cPanel como segunda barrera.
