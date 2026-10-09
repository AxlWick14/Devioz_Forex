# Forex System Realtime — MVC PHP

Proyecto académico listo para XAMPP, con arquitectura MVC y cotizaciones de Forex + criptomonedas obtenidas desde Twelve Data.
e
## Usuarios, roles y acceso

La pestaña **Administración** abre `public/admin`, exclusiva de administradores, con un resumen real de cuentas, accesos a gestión de usuarios y roles, y supervisión de los cierres históricos por activo. Desde allí se puede solicitar una actualización, respetando el bloqueo y los reintentos compartidos del proveedor. Las acciones usan POST y CSRF; invitados y usuarios no pueden acceder a esta página ni ejecutar sus acciones.

El acceso a la sesión está junto al botón de tema, en la parte izquierda del encabezado. Para crear las cuentas de prueba `admin.testing@forex.local` y `user.testing@forex.local`, ejecuta `C:\xampp\php\php.exe scripts\crear_usuarios_testing.php`. Sus contraseñas aleatorias se guardan en `storage/testing-accounts.json`, excluido de Git y bloqueado por HTTP. Repetir el comando conserva las cuentas existentes y sus contraseñas; si ya existe la antigua cuenta de prueba, conserva su correo. Para probar el invitado, cierra sesión o utiliza una ventana privada.

Existen exactamente tres roles: **Administrador** (`admin`), **Usuario** (`user`) e **Invitado** (`guest`). Invitado corresponde a visitantes sin sesión y puede utilizar Home, Mercado, Calculadora e Histórico. Usuario tiene acceso a los módulos operativos (Overview, Pronósticos, ETL y predictor Bitcoin incluidos). Administrador también administra las cuentas y consulta la matriz de permisos. El registro público en `public/registro` crea cuentas de Usuario; también pueden crearse desde Administración > Usuarios. Invitado no se asigna a cuentas registradas.

El ingreso está en `public/login`. El administrador puede crear, editar, activar, desactivar y eliminar cuentas y asignar sus roles desde `public/admin/usuarios`. La matriz está en `public/admin/permisos`. La navegación y las rutas utilizan la política central `app/core/Access.php`; las APIs y las entradas directas del predictor también validan acceso en el servidor. Los endpoints de administración rechazan a los usuarios.

Las contraseñas se guardan con `password_hash` y se verifican con `password_verify`. Los formularios de cuentas usan CSRF; las sesiones renuevan su identificador al entrar y salir, tienen cookies HttpOnly/SameSite y vencen tras 30 minutos sin solicitudes. Cada solicitud vuelve a consultar el rol y el estado de la cuenta. La desactivación o cambio de contraseña revoca sesiones anteriores. Los cambios de cuentas se serializan para conservar al menos un administrador activo y evitar que un administrador elimine su propia cuenta o se quite su acceso. Los intentos de login se limitan por IP a diez por quince minutos.

Para una instalación existente, ejecuta sin volver a importar el esquema completo:

```powershell
C:\xampp\php\php.exe scripts\migrar_usuarios.php
C:\xampp\php\php.exe scripts\migrar_roles.php
C:\xampp\php\php.exe scripts\crear_admin.php admin@forex.local "Administrador"
```

La migración es idempotente, revisa las tablas existentes, conserva su esquema y conteos en `storage/backups` y añade únicamente la tabla `usuarios`. Si encuentra otra estructura de cuentas, se detiene para evitar duplicarla. La creación inicial genera una contraseña aleatoria y guarda las credenciales en `storage/initial-admin.txt`; cambia esa contraseña desde Administración > Usuarios y elimina el archivo después de usarlo. El script no crea otra cuenta inicial si ya existe un administrador activo. Los secretos y registros de autenticación se excluyen de Git.

Apache debe permitir `.htaccess` y tener `mod_rewrite` habilitado, como en la instalación XAMPP actual. El `.htaccess` de la raíz bloquea el acceso HTTP a configuración, código interno, scripts y almacenamiento; el predictor tiene protección de sesión adicional. MySQL debe estar disponible para autenticarse y validar las cuentas. En un servidor público, sirve la aplicación mediante HTTPS para que la cookie de sesión use Secure.

Pruebas de permisos y cuentas (la segunda requiere Apache y MySQL locales y crea cuentas temporales que elimina al terminar):

```powershell
C:\xampp\php\php.exe tests\AccessControlTest.php
C:\xampp\php\php.exe tests\AccountsHttpTest.php
```

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

## Registro de cambios

- [9 de octubre de 2026: brillo animado de Administración según el tema](docs/cambios/2026-10-09-administracion.md).
- [9 de octubre de 2026, lote 2: Administrador, Usuario e Invitado](docs/cambios/2026-10-09-lote-2-roles.md).
- [9 de octubre de 2026, lote 03: Pronósticos por rol y cuatro activos](docs/cambios/2026-10-09-lote-03-pronosticos.md).
- [9 de octubre de 2026, cambios 4: Overview visible para invitados con candado](docs/cambios/2026-10-09-cambios-4-home.md).
- [9 de octubre de 2026: mejora del login](docs/cambios/2026-10-09-mejora-login.md).
- [9 de octubre de 2026: fotografía, GIF y animaciones en el login](docs/cambios/2026-10-09-login-imagenes-animaciones.md).
- [9 de octubre de 2026: probabilidades de subida y bajada resaltadas](docs/cambios/2026-10-09-pronosticos-probabilidades.md).
- [9 de octubre de 2026: selección de cinco modelos y comparación local](docs/cambios/2026-10-09-cinco-modelos-pronosticos.md).
- [9 de octubre de 2026: desarrollo de ARIMA (1,1,0)](docs/cambios/2026-10-09-desarrollo-arima.md).
- [9 de octubre de 2026: inicio de XGBoost, funcionamiento y requisitos pendientes](docs/cambios/2026-10-09-inicio-xgboost.md).
- [9 de octubre de 2026: instalación de XGBoost y uso de históricos de Twelve Data](docs/cambios/2026-10-09-instalacion-xgboost.md).
- [9 de octubre de 2026: XGBoost operativo en Pronósticos, validación y probabilidades](docs/cambios/2026-10-09-desarrollo-xgboost.md).

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
