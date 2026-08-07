# Despliegue en cPanel — cotizacion.enlix.pe

Modelo: el sitio se **clona desde GitHub** y se actualiza con `git pull`.

Estado del subdominio: resuelve a `192.250.227.240`, responde **404** (vhost
creado, carpeta vacía) y **no tiene SSL** todavía.

> El repositorio es **privado**, así que el servidor necesita una llave para
> clonarlo. Por eso el paso 1 es una clave SSH.

---

## Paso 1 — Llave SSH del servidor

En cPanel → **Terminal**:

```bash
ssh-keygen -t ed25519 -C "cpanel-cotizacion" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
```

Copia lo que imprime el `cat` (empieza con `ssh-ed25519 ...`).

En GitHub → repositorio `sistema-cotizacion` → **Settings** → **Deploy keys**
→ **Add deploy key**:

- Title: `cPanel cotizacion.enlix.pe`
- Key: lo que copiaste
- **NO** marcar "Allow write access" (el servidor solo necesita leer)

Vuelve a la Terminal y autoriza el host:

```bash
ssh -o StrictHostKeyChecking=accept-new -T git@github.com
```

Debe responder algo como `Hi kitikazis/sistema-cotizacion! You've successfully
authenticated, but GitHub does not provide shell access.` Ese mensaje es el
correcto: no da shell, solo confirma que la llave funciona.

---

## Paso 2 — Traer el código

```bash
cd ~/cotizacion.enlix.pe
git init -b main
git remote add origin git@github.com:kitikazis/sistema-cotizacion.git
git fetch origin main
git reset --hard origin/main
```

Se usa `git init` + `reset` en vez de `git clone` porque la carpeta del
subdominio ya existe y `clone` exige que esté vacía.

Si cPanel dejó un `index.html` de bienvenida, bórralo para que no compita con
`index.php`:

```bash
rm -f ~/cotizacion.enlix.pe/index.html
```

---

## Paso 3 — Dependencias

```bash
cd ~/cotizacion.enlix.pe
composer install --no-dev --optimize-autoloader
```

Si `composer` no existe en el hosting, se instala una vez en la carpeta:

```bash
cd ~/cotizacion.enlix.pe
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

---

## Paso 4 — Base de datos

En cPanel → **Bases de datos MySQL**:

1. Crear la base, p. ej. `cotizador` → queda como `CUENTA_cotizador`.
2. Crear un usuario y **generar la contraseña** con el botón de cPanel.
3. Asignar el usuario a la base con **todos los privilegios**.

Las tablas **no** se crean a mano: hay un migrador que las arma leyendo la
configuración del `.env`.

```bash
cd ~/cotizacion.enlix.pe
php tools/migrar.php
```

Ventajas sobre importar el `.sql` con el cliente de MySQL:

- No pide contraseña: la toma del `.env`.
- No hay que quitarle el `CREATE DATABASE` a nada.
- Es **idempotente**: se puede volver a correr sin miedo. Cada archivo usa
  `CREATE TABLE IF NOT EXISTS` y queda anotado en la tabla `migraciones`,
  así que lo ya aplicado no se repite y **los datos existentes no se tocan**.
- Si algo falla, se detiene ahí y dice en qué archivo.

Para ver qué falta sin aplicar nada:

```bash
php tools/migrar.php --estado
```

Las migraciones viven en `database/migraciones/`, numeradas: el orden importa
porque las claves foráneas dependen de las tablas anteriores.

> `database/schema.sql` sigue existiendo para levantar una base desde cero en
> desarrollo, pero en el servidor se usa el migrador.

---

## Paso 5 — Archivos que no vienen en el repositorio

Solo dos cosas quedan fuera de git: el `.env` (paso 3) y la firma escaneada.

Los **datos del emisor** —razón social, RUC, cuentas bancarias y firma— sí
vienen en el repositorio, en `config/empresa.php`, y llegan cargados con el
`git pull`. No hay que configurarlos.

Para cambiarlos se edita ese archivo, se commitea y se hace `git pull` en el
servidor. La razón social y el RUC además se pueden sobrescribir por
cotización desde el propio formulario.

La **firma escaneada** se sube con cPanel → Administrador de archivos a:

```
cotizacion.enlix.pe/pdf/firma-transparente.png
```

Si falta, el PDF se genera igual pero sin firma sobre la línea.

---

## Paso 6 — Permisos

```bash
cd ~/cotizacion.enlix.pe
mkdir -p storage && chmod 755 storage
chmod 600 .env
```

---

## Paso 7 — Comprobar

Abrir en el navegador:

```
http://cotizacion.enlix.pe/                     → el listado
http://cotizacion.enlix.pe/config/database.php  → debe dar 404
http://cotizacion.enlix.pe/database/schema.sql  → debe dar 404
http://cotizacion.enlix.pe/pdf/firma-transparente.png → debe dar 404
http://cotizacion.enlix.pe/.git/config          → debe dar 404
```

Los cuatro 404 son la prueba de que `.htaccess` está activo. **Si alguno
devuelve el archivo, parar**: significa que `AllowOverride` está deshabilitado
y todo el código, la base y la firma quedan expuestos. En ese caso hay que
pedir al hosting que habilite `AllowOverride All`.

---

## Paso 8 — SSL

cPanel → **SSL/TLS Status** → ejecutar **AutoSSL** sobre `cotizacion.enlix.pe`.

Cuando `https://cotizacion.enlix.pe` abra bien, activar la redirección
quitando los `#` del bloque HTTPS en `.htaccess` (está comentado a propósito:
con el certificado sin emitir dejaría el sitio en bucle de redirecciones).

---

## Actualizar el sitio después

Cada vez que haya cambios nuevos en GitHub:

```bash
cd ~/cotizacion.enlix.pe && bash tools/desplegar.sh
```

El script hace `fetch` + `reset --hard`, reinstala dependencias y avisa si
falta alguno de los archivos de configuración. **`reset --hard` descarta
cualquier cambio hecho a mano en los archivos versionados**; los que están en
`.gitignore` (credenciales, empresa, firma) no se tocan.

---

## Acceso: modo prueba

Ahora mismo `config/app.php` tiene:

```php
'acceso_libre' => true,
```

Se entra **sin login**, y la aplicación muestra una cinta roja en todas las
pantallas recordándolo. Es lo pedido para probar.

**Antes de cargar cotizaciones reales**, ponerlo en `false`:

```bash
cd ~/cotizacion.enlix.pe
nano config/app.php     # 'acceso_libre' => false
```

Y crear el primer usuario:

```bash
php tools/crear_usuario.php
```

Pide nombre, usuario y contraseña (mínimo 10 caracteres) y la guarda con
bcrypt coste 12. No hay registro por web a propósito.

Si `tools/crear_usuario.php` se corre antes de poner `acceso_libre => false`,
funciona igual: el usuario queda creado y listo para cuando cierres la puerta.

### Qué protege el login cuando está activo

- Todas las rutas exigen sesión salvo `login` y `entrar`.
- Cookie `httponly`, `samesite=Lax` y `secure` en producción.
- El id de sesión se regenera al entrar (evita fijación de sesión).
- Token CSRF en guardar, actualizar, eliminar y salir; responde **419** si no
  cuadra.
- Mensaje genérico y espera de 400 ms en cada fallo, para no revelar qué
  usuarios existen.

No hay bloqueo por intentos repetidos ni segundo factor.

---

## Qué protege el .htaccess

- 404 en `config/`, `database/`, `models/`, `controllers/`, `helpers/`,
  `tests/`, `vendor/`, `storage/`, `pdf/` y `.git`
- 404 en `composer.json`, `composer.lock`, `README.md`
- Bloqueo por extensión de `.sql`, `.md`, `.log`, `.bak` y `*.example.php`
- Sin listados de directorio
- `display_errors` apagado
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

La firma no se sirve por URL: el formulario la incrusta como data URI leída
desde disco, así que bloquear `pdf/` no rompe la vista previa.

---

## Entornos

`esProduccion()` decide por el host: `127.0.0.1`, `localhost`, `.local` y
`.test` son desarrollo; cualquier otro es producción. En producción los
errores van al log y nunca a pantalla, y las credenciales salen de
`config/credenciales.php`.
