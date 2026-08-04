# Módulo de cotizaciones — enlix.pe

PHP MVC vanilla + MySQL/MariaDB + Alpine.js + dompdf.

## Cómo levantarlo

```bash
# 1. Base de datos (MariaDB de XAMPP, puerto 8081 en esta máquina)
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini
C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 -P 8081 < database/schema.sql

# 2. Dependencias
composer install

# 3. Datos del emisor (no vienen en el repo: llevan cuentas bancarias reales)
cp config/empresa.example.php config/empresa.php
# ...y edita razon social, RUC, cuentas y firma

# 4. Servidor
php -S 127.0.0.1:8010 -t .
```

Abrir <http://127.0.0.1:8010/index.php>.

> El puerto de la base es **8081**, no 3306: el 3306 lo ocupa un MySQL 8.0
> standalone instalado aparte. Se configura en `config/database.php`.

## Pruebas

```bash
php tests/test_pricing.php
```

Reproduce celda por celda la fila 44 del Excel original y valida que el total
que ve el cliente cuadre con el total interno.

## La fórmula (fuente de verdad)

Vive en `helpers/PricingCalculator.php`. **No se modifica sin pedido explícito.**

```
IR             = Precio × 1.5%                    [fijo]
IGV            = Precio × 18%                     [fijo]
Detracción     = Precio × 12%                     [opcional, por ítem]
Ganancia       = Precio × %  (10/12/14/20/22/24)  [elegible, por ítem]
Subtotal       = Precio + IR + IGV + Detracción
                 + Licencia S.O + Delivery + Embalaje + Envío + Ganancia
Retención      = Subtotal × 3%                    [opcional, por ítem]
Total unitario = Subtotal + Retención
Total línea    = Total unitario × Cantidad
```

### El puente entre los dos bloques del Excel

El Bloque 1 (lo que ve el cliente) y el Bloque 2 (el motor de precios) se
conectan así:

```
P.UNIT del cliente = Total unitario / 1.18
Subtotal cliente   = Σ (P.UNIT × cantidad)
IGV cliente        = Subtotal cliente × 18%
Total cliente      = Subtotal + IGV     ← igual al Σ Total línea interno
```

Se dedujo de las celdas del Excel: `74.48 / 1.18 = 63.1186440677966`, que es
exactamente el `H12` del original. Así el cliente ve un precio sin IGV y el
total final coincide con el que arroja el motor interno.

## Estructura

```
config/database.php     Conexión PDO
config/empresa.php      Datos del emisor, cuentas bancarias, firma
database/schema.sql     Esquema (cotizaciones + cotizacion_items)
helpers/PricingCalculator.php   La fórmula
helpers/funciones.php   Escape, formato de moneda, urls
models/Cotizacion.php   Acceso a datos
controllers/CotizacionController.php
views/                  Layout + listado + formulario + detalle
pdf/generar_pdf.php     Documento del cliente con dompdf
public/assets/          CSS y Alpine.js (local, sin CDN)
tests/test_pricing.php  Regresión contra el Excel
```

## Decisiones de implementación

- **Los montos se recalculan siempre en el servidor.** Lo que manda el
  navegador es solo entrada; el JS de Alpine calcula únicamente la
  previsualización que ve el vendedor mientras escribe.
- **Los resultados se guardan congelados** en cada fila. Una cotización emitida
  no cambia de precio si mañana se toca una tasa.
- **El desglose interno nunca sale en el PDF.** IR, detracción, ganancia y
  retención son de uso interno; el cliente solo ve P.UNIT, subtotal, IGV y total.
- **Sin redondeos intermedios**, igual que Excel: se redondea solo al mostrar.
