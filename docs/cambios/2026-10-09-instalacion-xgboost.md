# Instalación de XGBoost — 9 de octubre de 2026

**Actualización posterior:** el motor ya se integró con Pronósticos. Este registro
conserva el estado al terminar la instalación; consulta el
[desarrollo de XGBoost](2026-10-09-desarrollo-xgboost.md) para el funcionamiento actual.

## Cambio pedido

Instalar XGBoost y utilizar Twelve Data como fuente de información.

## Cambio realizado

- Instalado **XGBoost 3.4.1** en el Python **3.12.10** del proyecto:
  `scripts/ml/.python/python.exe`.
- Añadida la dependencia `xgboost==3.4.1` a `scripts/ml/requirements.txt` para
  reproducir la instalación.
- Actualizada la documentación de Machine Learning y enlazado este registro
  desde el README principal.

Comando ejecutado para instalar:

```powershell
& scripts/ml/.python/python.exe -m pip install 'xgboost>=3.0,<4'
```

PyPI proporcionó la distribución para Windows de 64 bits. La instalación
reutilizó NumPy y SciPy presentes en el entorno local.

## Fuente de datos: Twelve Data

La aplicación ya obtiene datos de Twelve Data mediante `app/models/Historial.php`
y guarda los cierres en MySQL, en `historico_cotizaciones`. Los pares previstos
para XGBoost son **USD/PEN, EUR/USD, BTC/USD y ETH/USD**.

El modelo puede entrenarse localmente con esos históricos. La clave de Twelve
Data continúa gestionada por PHP; no hace falta copiarla a Python. La descarga
permanece sujeta a los límites del proveedor.

Para preparar el entrenamiento se propone reutilizar el patrón PHP → archivo
privado → Python de `scripts/exportar_btc_ml.php`, ampliándolo a los cuatro
activos. Esa ampliación todavía no está implementada. Hay que excluir el día
actual (`fecha < hoy`, zona `America/Lima`) y revisar los huecos antes de generar
variables y objetivos.

## Verificación realizada

- Importación de `xgboost`: versión **3.4.1** confirmada.
- Carga de la biblioteca nativa y entrenamiento de `XGBRegressor` en CPU.
- Predicción de 40 muestras sintéticas con resultados finitos y error inferior
  a una referencia constante en esa prueba.
- `python -m pip check`: **No broken requirements found**.

La prueba usa datos sintéticos y confirma que la instalación funciona. No es una
evaluación de precisión financiera. No se entrenó ni guardó un modelo operativo.

## Qué queda pendiente

1. Preparar la entrada de los cuatro activos desde los históricos de Twelve Data.
2. Implementar entrenamiento y pronóstico de precios por horizonte.
3. Validar cronológicamente, comparar errores y estimar probabilidades.
4. Conectar el motor con PHP, añadir caché y habilitarlo en Pronósticos.

El botón XGBoost sigue marcado como **Pendiente** hasta completar esa integración.
El predictor Bitcoin existente utiliza ExtraTrees.

**Estado: dependencia instalada y verificada; motor de pronósticos pendiente.**
