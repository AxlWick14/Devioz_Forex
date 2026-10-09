# XGBoost aplicado a Pronósticos — 9 de octubre de 2026

## Cambio pedido

Hacer funcionar XGBoost en Pronósticos después de instalarlo, utilizando la
información de Twelve Data.

## Cambio realizado

- Nuevo motor real `scripts/ml/xgboost_forecast.py` con `XGBRegressor` de
  **XGBoost 3.4.1**, ejecutado en CPU.
- Puente `app/models/XgboostForecast.php`: PHP entrega los históricos de los
  cuatro activos en un único proceso Python y recibe los resultados en JSON.
- `Pronostico.php` integra la proyección, las probabilidades y la validación.
  El Administrador puede elegir XGBoost; el Usuario puede recibirlo cuando
  obtiene menor MAPE que los otros modelos sobre las fechas comparadas.
- Cinco modelos seleccionables en la interfaz. XGBoost deja de estar pendiente
  y sus precios y probabilidades aparecen en las tarjetas, el gráfico y los
  cuadros de información al pasar el cursor.
- Caché privada por activo, invalidada cuando cambia la fecha, el histórico,
  el motor o el archivo de dependencias. No requiere mantener Flask abierto.

## Datos y funcionamiento

Se reutiliza `historico_cotizaciones`, donde PHP guarda la información descargada
de **Twelve Data**. Python no necesita la clave del proveedor. Se excluyen el día
actual y fechas posteriores, en la zona `America/Lima`.

PHP entrega hasta **600 registros** por activo para disponer de información
anterior a los orígenes de validación. Cada entrenamiento usa como máximo los
últimos **500 cierres** disponibles en su fecha de origen. El mínimo de 120
observaciones es una decisión operativa; también se exige suficiente validación
antes de aceptar el resultado.

Las entradas se calculan con los últimos 30 cierres de cada origen: retornos
anteriores, medias relativas, volatilidad y distancia entre fechas. Se añaden el
horizonte calendario y el día de la semana de la fecha objetivo. Un modelo por
activo aprende el retorno logarítmico futuro y lo transforma a precio mediante
`ultimo_cierre × exp(retorno_estimado)`.

Parámetros de esta primera versión: 80 árboles, profundidad máxima 3, tasa de
aprendizaje 0,05, regularización L2 de 10, `min_child_weight=8`, `max_bin=32`,
`tree_method=hist` y un hilo de CPU. Son parámetros fijos, sin búsqueda automática.
La profundidad y regularización controlan la complejidad del modelo.
[Referencia oficial de parámetros](https://xgboost.readthedocs.io/en/stable/parameter.html).

El resultado incluye una estimación de hoy y siete fechas futuras. El horizonte
desde el último cierre puede ser mayor: con dos días de antigüedad, el objetivo
final está nueve días después de ese cierre. Se admiten hasta 14 días desde el
último cierre; si el histórico está más atrasado, debe actualizarse.

Las etiquetas de entrenamiento corresponden exclusivamente a fechas con
registros. Se respetan también los registros de fin de semana que existen en
esta base. No se añaden cierres para rellenar huecos. Si dos registros están
separados por más de siete días, se conserva solo el tramo posterior al último
intervalo largo. Todos los puntos futuros son estimaciones.

## Validación y probabilidades

Para comparar modelos se repiten los pronósticos de los últimos **21 orígenes**
y se evalúan los cierres disponibles hasta siete días después de cada origen.
XGBoost solo usa variables y etiquetas conocidas en el momento de cada ajuste.
Se calcula MAPE y la referencia de mantener el último cierre, sobre las mismas
fechas que los modelos PHP en el histórico local reciente.

Las probabilidades usan errores fuera de muestra de los últimos **60 orígenes**
en el horizonte exacto del objetivo actual. Solo se comparan fechas con cierre
guardado. Cada error relativo se aplica al precio estimado actual y se cuenta
si el escenario queda por encima o por debajo del último cierre. Se añade una
muestra por dirección para evitar declarar certeza absoluta. Con menos de diez
comparaciones, las probabilidades aparecen como no disponibles.

Estas probabilidades son estimaciones empíricas, no la salida de un clasificador.
Los errores de validación sirven para seleccionar el modelo; comparten fechas
y no constituyen una evaluación final independiente de precisión futura.

## Operación y fallos

Al elegir XGBoost o solicitar la selección automática, el backend prepara el
cálculo y reutiliza la caché. La actualización del histórico invalida el resultado
cuando incorpora o corrige registros. La primera consulta tras un cambio tarda
más porque vuelve a entrenar y validar.

Los archivos quedan en `storage/ml`, bloqueado por HTTP y excluido de Git. El
proceso tiene un límite de 75 segundos; la espera por otro entrenamiento se
limita a 15 segundos. Si falla XGBoost, la selección automática conserva los
modelos PHP disponibles. La selección manual devuelve un error legible, sin
presentar otro modelo como si fuera XGBoost.

Para preparar la caché manualmente, desde la raíz del proyecto:

```powershell
& C:/xampp/php/php.exe scripts/precalcular_xgboost.php
```

Python se busca en `.python` o `.venv` dentro de `scripts/ml`. En otra instalación
puede configurarse la ruta del ejecutable mediante `FOREX_ML_PYTHON` en el entorno
del servidor PHP. Las dependencias están en `scripts/ml/requirements.txt`.

## Archivos principales

- `scripts/ml/xgboost_forecast.py`: cálculo, validación y probabilidades.
- `app/models/XgboostForecast.php`: proceso, caché y comprobación de respuesta.
- `app/models/Pronostico.php`: integración y selección automática.
- `app/controllers/PronosticoController.php`: aceptación del método y errores.
- `app/views/public/pronosticos.php` y `public/assets/js/forecast.js`: selector
  y presentación del resultado.
- `scripts/precalcular_xgboost.php`: preparación manual de los cuatro activos.

## Resultados y verificación local

Con la base actual, cada activo utiliza 500 cierres en el ajuste operativo.
Objetivo mostrado: **2026-10-16**, siete días después de hoy.

| Activo | Último cierre | MAPE XGBoost | MAPE último cierre | Comparaciones de error | Escenarios de probabilidad |
| --- | --- | ---: | ---: | ---: | ---: |
| USD/PEN | 2026-10-07 | 0,733% | 0,553% | 126 | 52 |
| EUR/USD | 2026-10-07 | 0,621% | 0,510% | 126 | 52 |
| BTC/USD | 2026-10-08 | 2,423% | 2,326% | 126 | 53 |
| ETH/USD | 2026-10-08 | 3,197% | 2,286% | 126 | 53 |

En esta muestra XGBoost no mejora la referencia de mantener el último cierre.
La selección automática no lo fuerza: compara su error con los otros modelos.
Estos resultados comprueban funcionamiento, sin afirmar superioridad predictiva.
La consulta de los cuatro resultados con la caché preparada tardó 0,06 segundos
en la comprobación local; ese tiempo no incluye el entrenamiento inicial.

Verificación aprobada:

- **13 pruebas Python:** aislamiento de datos futuros, etiquetas reales, ventanas,
  huecos, fechas, precios, probabilidades y errores.
- `tests/XgboostForecastTest.php`: proceso real de los cuatro activos, reutilización
  de caché, invalidación con correcciones de precio y fallo del ejecutable.
- `tests/ForecastMethodsTest.php` y `tests/ArimaTest.php`: modelos existentes.
- `tests/ForecastRolesHttpTest.php`: API real, cinco métodos, siete fechas futuras,
  precios y probabilidades; selección manual del Administrador y automática del
  Usuario. Las cuentas de prueba se eliminaron al terminar.
- Chrome: selección y ayuda de XGBoost, actualización del gráfico, probabilidades
  y presentación de errores. Esta prueba de interfaz sustituye API y Chart.js
  por respuestas controladas; la API real se verifica en la prueba HTTP.
- Sintaxis PHP y revisión de enlaces de documentación.

La página temporal de comprobación se retiró. El cálculo está preparado en
la caché privada para su uso inmediato.

**Estado: XGBoost operativo en Pronósticos para los cuatro activos.**
