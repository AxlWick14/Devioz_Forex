# Inicio de XGBoost — 9 de octubre de 2026

Este documento conserva el diagnóstico inicial, anterior a la instalación.
**Actualización posterior:** XGBoost **3.4.1** ya está instalado y verificado en
el Python local y el motor ya se integró con Pronósticos. Véanse el
[registro de instalación](2026-10-09-instalacion-xgboost.md) y el
[desarrollo de XGBoost](2026-10-09-desarrollo-xgboost.md).

## Cambio pedido y trabajo realizado

**Pedido:** comenzar con XGBoost, explicar cómo funciona, revisar su aplicación en el proyecto e identificar lo que falta para realizarlo.

**Realizado en esta entrega:** revisión del código, del entorno Python y de los históricos; documentación del estado actual y propuesta de integración. El motor XGBoost todavía no está implementado, instalado ni entrenado. Su botón continúa marcado como **Pendiente**.

## Cómo funciona

XGBoost significa *Extreme Gradient Boosting*. Combina árboles de decisión entrenados sucesivamente: cada árbol nuevo busca reducir el error del conjunto anterior. La predicción resulta de sumar sus aportes. La función de entrenamiento incluye penalizaciones para controlar la complejidad y reducir el sobreajuste. [Explicación oficial de XGBoost](https://xgboost.readthedocs.io/en/stable/tutorials/model.html).

Para este proyecto se propone usar `XGBRegressor`, que permite estimar valores numéricos. Las entradas serían retornos anteriores, medias relativas y volatilidad, calculados únicamente con información disponible en la fecha del pronóstico. El objetivo propuesto es el retorno logarítmico futuro; después se convierte a precio: `precio_estimado = ultimo_cierre × exp(retorno_estimado)`. Es una propuesta de diseño, todavía sin resultados de precisión. [API oficial de XGBRegressor](https://xgboost.readthedocs.io/en/stable/python/python_api.html#xgboost.XGBRegressor).

Ejemplo de uso: a partir del comportamiento reciente de Bitcoin, estimar su precio para cada fecha del gráfico y comparar el objetivo final con el último cierre. Un regresor no entrega automáticamente una probabilidad de subida: esa estimación requiere un procedimiento adicional.

## Cómo está aplicado actualmente

| Componente | Estado comprobado |
| --- | --- |
| `app/views/public/pronosticos.php` y `public/assets/js/forecast.js` | Nombre, explicación y botón pendiente de XGBoost; no genera predicciones. |
| `app/models/ForecastMethods.php` | Ejecuta lineal, logarítmica, Holt y ARIMA. XGBoost no participa en la selección automática. |
| `app/models/Pronostico.php` | Consulta los últimos 120 cierres por activo. No conecta con XGBoost. |
| `scripts/ml/btc_model.py` | Predictor separado con **ExtraTrees**, solo Bitcoin y dirección del siguiente día. No es una implementación de XGBoost. |
| `scripts/ml/.python/python.exe` | Python **3.12.10** disponible. NumPy, pandas y scikit-learn instalados; **XGBoost no está instalado**. |

## Datos disponibles

Consulta local de `historico_cotizaciones`, excluyendo el día actual (`fecha < 2026-10-09`, zona `America/Lima`):

| Activo | Cierres reales | Primera fecha | Último cierre |
| --- | ---: | --- | --- |
| Dólar — USD/PEN | 5.018 | 2007-12-12 | 2026-10-07 |
| Euro — EUR/USD | 5.537 | 2004-04-14 | 2026-10-07 |
| Bitcoin — BTC/USD | 3.329 | 2017-08-28 | 2026-10-08 |
| Ethereum — ETH/USD | 3.182 | 2018-01-17 | 2026-10-08 |

Todos los cierres consultados son positivos y finitos, y su fuente registrada es Twelve Data. Esta comprobación no certifica toda la calidad del proveedor. Hay registros suficientes para iniciar un prototipo; la cantidad de ejemplos utilizables dependerá de las variables y de los huecos.

BTC no presenta fechas faltantes en su tramo. ETH tiene cinco días sin cierre repartidos en dos huecos. Las divisas incluyen días de mercado cerrado; además, EUR/USD tiene **564 días sin registros entre 2006-04-13 y 2007-10-30**. Ese tramo debe separarse, sin rellenarlo como si fueran cotizaciones reales.

## Aplicación propuesta

Flujo previsto: **cierres confirmados → variables históricas → XGBoost en Python → precios y validación → API PHP → gráfico**.

Se propone un cálculo por activo, con resultados reutilizables mediante caché. No conviene iniciar Python y entrenar árboles dentro de cada llamada de `ForecastMethods::project()`, porque la calibración y las probabilidades repiten esa operación muchas veces.

El gráfico mantiene siete días calendario desde hoy. Si el último cierre tiene dos días de antigüedad, el objetivo final está a nueve días de ese cierre; el motor debe contemplarlo. Las etiquetas de entrenamiento deben corresponder a cierres realmente observados. Los días de mercado cerrado pueden conservar la última estimación, identificada como estimación.

Se propone ampliar la lectura de datos para XGBoost y comparar ventanas recientes mediante validación temporal. Usar todo el archivo no es un requisito del algoritmo ni demuestra mayor precisión. Para compararlo con los otros modelos, deben evaluarse las mismas fechas y horizontes.

## Qué falta para tenerlo operativo

1. **Dependencia:** instalar XGBoost en el Python local, comprobar su compatibilidad y registrarlo en `scripts/ml/requirements.txt`.
2. **Preparación:** obtener los cuatro históricos, construir variables sin información futura y tratar los huecos y calendarios.
3. **Motor:** implementar entrenamiento y pronóstico de precios por horizonte con `XGBRegressor`.
4. **Validación:** entrenar con el pasado y evaluar fechas posteriores; excluir ejemplos cuyas etiquetas alcancen el período de prueba. Comparar MAPE con mantener el último cierre y reservar una evaluación final independiente. La validación temporal evita entrenar con el futuro y evaluar el pasado. [Referencia oficial de scikit-learn](https://scikit-learn.org/stable/modules/generated/sklearn.model_selection.TimeSeriesSplit.html).
5. **Probabilidades:** estimar subida y bajada con errores fuera de muestra del horizonte mostrado; devolver “no disponible” si faltan muestras suficientes.
6. **Integración:** conectar Python con PHP, controlar fallos y tiempo máximo, guardar caché que se invalide con nuevos cierres y habilitar el selector del Administrador. Incorporar XGBoost a la selección del Usuario con métricas comparables.
7. **Pruebas:** verificar fechas, precios positivos, ausencia de información futura, probabilidades, caché y permisos de ambos roles.

## Verificación realizada

Lectura del código, consulta SQL sin modificar registros, comprobación de versiones y ausencia del paquete XGBoost. No se ejecutó entrenamiento ni se midió precisión de XGBoost, porque el motor aún no existe.

**Estado: diagnóstico y diseño documentados; implementación pendiente.**
