# Prueba Bitcoin con ExtraTrees

Prueba adicional para BTC/USD basada en OHLC diarios, sin API externa de predicciones.
ExtraTrees no es Random Forest: los botones pendientes conservan su estado.
El ejemplo compartido estaba incompleto; esta implementación calcula indicadores,
etiqueta el cierre siguiente, evalúa cronológicamente y expone una API local.

## Ejecutar desde PowerShell, en la raíz del proyecto

Necesitas Python 3.11 o posterior y MySQL de XAMPP iniciado.

Si se instaló Python localmente para esta prueba, usa
`scripts\ml\.python\python.exe` directamente en lugar de `py` o del entorno
virtual de los ejemplos. No es necesario activar nada:

```powershell
& scripts\ml\.python\python.exe scripts\ml\btc_model.py train
& scripts\ml\.python\python.exe scripts\ml\btc_model.py serve
```

```powershell
& C:\xampp\php\php.exe scripts\exportar_btc_ml.php
py -m venv scripts\ml\.venv
& scripts\ml\.venv\Scripts\python.exe -m pip install -r scripts\ml\requirements.txt
& scripts\ml\.venv\Scripts\python.exe scripts\ml\btc_model.py train
& scripts\ml\.venv\Scripts\python.exe scripts\ml\btc_model.py serve
```

Abre `http://localhost/forex_system_realtime/public/bitcoin-predictor/`.
La página llama a PHP, que consulta `http://127.0.0.1:5000/predict`.
También puedes ejecutar `btc_model.py predict` para obtener el JSON en consola.

Si dispones del CSV mencionado, pásalo con `--csv "ruta\archivo.csv"`
tanto al entrenar como al servir. Separador `;`, columnas `par`, `fecha`,
`precio_apertura`, `precio_maximo`, `precio_minimo`, `precio_cierre`.
`cambio_porcentual` es opcional para Python.

## Qué mide

- Al menos 250 cierres completos y consecutivos para esta prueba; es un umbral
  operativo, no garantía de suficiencia estadística. Se rechazan días faltantes,
  duplicados, precios inválidos y velas inconsistentes.
- Variables de retornos, medias, volatilidad, RSI, ATR, rango, cuerpo y gap.
- SUBE significa cierre del día siguiente mayor; BAJA incluye igual.
- Primer 80% para entrenamiento, último 20% para prueba, purgando una fila entre
  ambos. Sin mezcla aleatoria ni indicadores que vean datos futuros.
- Se muestra exactitud, exactitud balanceada, matriz de confusión y referencia
  de clase mayoritaria. Después de evaluar se entrena el modelo operativo con
  todas las filas etiquetadas. La última fila sin etiqueta se usa para predecir.
- Probabilidades del clasificador sin calibrar; señal experimental con umbral
  del 60%, en otro caso SIN_SEÑAL_CLARA. No es una instrucción para operar.
- La predicción es para el día posterior al último cierre del CSV, no para hoy
  automáticamente. La página avisa si esa fecha ya pasó.
- Después de actualizar MySQL, repite exportación y entrenamiento. No se
  actualiza automáticamente. No hay conexión Python directa a MySQL todavía.

Los modelos serializados se guardan en `storage/ml`, fuera de `public`.
Carga únicamente el modelo generado por este proyecto; joblib no debe abrir
archivos de origen desconocido. No expongas el servicio Flask a Internet.

Documentación del algoritmo:
https://scikit-learn.org/stable/modules/generated/sklearn.ensemble.ExtraTreesClassifier.html

## XGBoost: dependencia instalada

El Python local `scripts\ml\.python\python.exe` tiene instalado **XGBoost 3.4.1**.
La versión queda registrada en `requirements.txt`. Para instalar las dependencias
en ese mismo entorno:

```powershell
& scripts\ml\.python\python.exe -m pip install -r scripts\ml\requirements.txt
```

Se verificaron la importación, la biblioteca nativa, el entrenamiento de
`XGBRegressor` en CPU y la predicción con datos sintéticos. `pip check` no detectó
dependencias incompatibles. Esta comprobación valida la instalación; no mide
precisión sobre cotizaciones.

Los datos de **Twelve Data** ya se descargan mediante PHP y se guardan en MySQL
(`historico_cotizaciones`). El futuro motor XGBoost puede reutilizarlos para
USD/PEN, EUR/USD, BTC/USD y ETH/USD. Python recibirá los históricos preparados;
la clave del proveedor continúa en el servidor PHP. Se deben excluir las velas
del día actual y tratar los huecos antes del entrenamiento.

El motor `xgboost_forecast.py` ya está integrado con Pronósticos para los cuatro
activos. PHP le entrega JSON y reutiliza resultados privados en `storage/ml`.
El Administrador puede elegir XGBoost; el Usuario recibe la selección automática.
El script `btc_model.py` conserva su implementación ExtraTrees.

Registro: [instalación de XGBoost y fuente Twelve Data](../../docs/cambios/2026-10-09-instalacion-xgboost.md).

Funcionamiento, validación y probabilidades:
[XGBoost aplicado a Pronósticos](../../docs/cambios/2026-10-09-desarrollo-xgboost.md).

Preparar los resultados con el histórico actual de MySQL:

```powershell
& C:/xampp/php/php.exe scripts/precalcular_xgboost.php
```

Pruebas del motor Python y del puente PHP (las de PHP requieren MySQL):

```powershell
& scripts/ml/.python/python.exe scripts/ml/test_xgboost_forecast.py
& C:/xampp/php/php.exe tests/XgboostForecastTest.php
& C:/xampp/php/php.exe tests/ForecastRolesHttpTest.php
```
