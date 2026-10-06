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

## 6. Cargar históricos para Machine Learning

Ejecuta `database/forex_db.sql` en MySQL para crear la tabla `velas_historicas`. Después descarga un CSV diario y utiliza este formato mínimo:

```csv
datetime,open,high,low,close,volume
2020-01-02,1.1200,1.1250,1.1180,1.1230,0
```

Desde PowerShell, importa el archivo con el PHP de XAMPP:

```powershell
C:\xampp\php\php.exe scripts\importar_historico.php C:\datos\eur_usd.csv EUR/USD Dukascopy 1day
```

Puedes repetir el comando para cada par. El importador evita duplicados por par, intervalo y fecha. Para criptomonedas usa como fecha inicial la primera vela de mercado disponible; no agregues filas anteriores a su existencia.

La pestaña Historial permite descargar archivos CSV separados para dólar (`USD/PEN`), euro (`EUR/USD`), Bitcoin (`BTC/USD`) y Ethereum (`ETH/USD`). El separador es `;`, compatible con Excel en configuración regional española, y las filas quedan ordenadas por fecha ascendente.

## Nota sobre “tiempo real”

La versión incluida usa REST y actualización automática. Para streaming tick a tick se necesitaría pasar a WebSocket.

## Actualización diaria del histórico




El calendario abre desde siete días atrás hasta hoy, usando la zona `America/Lima`. Las fechas seleccionadas se conservan aunque falten cierres. La página comprueba el cambio de día cada minuto y al volver a la pestaña; el rango predeterminado avanza automáticamente.

El backend descarga los cierres publicados para todos los activos del historial: USD/PEN, EUR/USD, JPY/USD, BTC/USD, ETH/USD, USDT/USD, BNB/USD y SOL/USD. El cierre de hoy se incorpora después de finalizar el día. El control compartido por activo en `storage/cache/history-sync-*.json` evita descargas duplicadas. Los límites de consultas permiten reintentar después de un minuto; otros errores y cierres pendientes se revisan cada hora. La tarea diaria espera un minuto y reintenta una vez si el proveedor limita las consultas. Si faltan cierres esperados, la interfaz muestra el estado pendiente.

En este equipo se registró la tarea Windows `ForexSystemRealtime-HistoricoDiario`, que ejecuta `scripts/actualizar_historico_diario.ps1` cada hora con la ventana oculta. Requiere una sesión de Windows abierta, MySQL disponible y conexión al proveedor. Cada ejecución agrega sus resultados, hora de inicio y código de salida a `storage/cache/history-daily.log` en UTF-8. Los registros antiguos en UTF-16 se conservan en un archivo `history-daily-legacy-*.log`. La aplicación también comprueba la actualización al consultar el histórico reciente.

Para ejecutar una actualización manual inmediata:

```powershell
C:\xampp\php\php.exe scripts\actualizar_historico_diario.php --force
```
