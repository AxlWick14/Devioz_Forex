# Forex System Realtime — MVC PHP

Proyecto académico listo para XAMPP, con arquitectura MVC y cotizaciones de Forex + criptomonedas obtenidas desde Twelve Data.

## Tecnologías

- HTML5
- CSS3
- JavaScript
- PHP
- MySQL
- XAMPP
- MVC

## Cotizaciones incluidas

Forex:
- USD/PEN
- EUR/USD
- GBP/USD
- USD/JPY

Crypto:
- BTC/USD
- ETH/USD
- SOL/USD
- XRP/USD

## 1. Configurar la API Key

Crea una cuenta gratuita en Twelve Data y copia tu API Key.

Abre:

`config/config.php`

Reemplaza:

`PEGA_AQUI_TU_API_KEY`

por tu clave real.

## 2. Ejecutar en XAMPP

Copia la carpeta completa a:

`C:\xampp\htdocs\forex_system_realtime`

Inicia Apache y abre:

`http://localhost/forex_system_realtime/`

Calculadora:

`http://localhost/forex_system_realtime/public/calculadora`

Endpoint JSON:

`http://localhost/forex_system_realtime/public/api/mercado`

## 3. Cómo funciona

Navegador → calculadora.js → /api/mercado → Router → CotizacionController → Cotizacion Model → Twelve Data → JSON → JavaScript → View

La API Key permanece en PHP y no se expone en JavaScript.

## 4. Actualización

El frontend actualiza automáticamente cada 5 minutos.

El backend usa un cache de 60 segundos en:

`storage/cache/market.json`

Puedes cambiar ambos valores en `config/config.php`.

## 5. MySQL

La visualización de cotizaciones no necesita MySQL todavía.

El archivo `database/forex_db.sql` deja preparadas las tablas para la siguiente etapa: guardar históricos y desarrollar el CRUD.

## Nota sobre “tiempo real”

La versión incluida usa REST y actualización automática. Para streaming tick a tick se necesitaría pasar a WebSocket.
