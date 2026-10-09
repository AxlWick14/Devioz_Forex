# Desarrollo de ARIMA — 9 de octubre de 2026

## Solicitud

Comenzar el desarrollo de ARIMA, un modelo a la vez, e identificar qué faltaba para que funcionara.

## Qué faltaba y qué se implementó

1. **Motor de cálculo:** nuevo `app/models/Arima.php`, implementado en PHP sin paquetes externos.
2. **Ajuste del modelo:** primera versión ARIMA(1,1,0) con deriva, de orden fijo. Diferencia los precios y ajusta un AR(1) sobre las diferencias mediante mínimos cuadrados condicionales. Limita el coeficiente autorregresivo al intervalo [-0,98; 0,98] para mantener estabilidad en la serie diferenciada. Una serie de diferencias constante usa coeficiente cero y conserva su deriva.
3. **Proyección:** pronostica los cambios diarios recursivamente y los acumula sobre el último precio. Genera la estimación de hoy y siete fechas futuras; considera los días transcurridos desde el último cierre.
4. **Integración:** `ForecastMethods::METHODS` incluye ARIMA. Participa en las ventanas de validación de 30, 60 y 120 cierres, la selección automática por activo y la estimación de probabilidades existente.
5. **Interfaz:** ARIMA dejó de estar pendiente. El Administrador puede seleccionarlo y el Usuario puede recibirlo si gana la selección automática. El selector conserva cinco opciones: cuatro disponibles y XGBoost pendiente.
6. **Pruebas:** ajuste de parámetros conocidos, deriva, recursión, fechas, datos inválidos, estabilidad, precios negativos, probabilidades, API y selección en la interfaz.

## Alcance de esta primera versión

El orden es `p=1`, `d=1`, `q=0`: no incorpora un término de medias móviles, estacionalidad ni búsqueda automática de órdenes. Es un caso específico de la familia ARIMA. Su formulación parte de los [modelos ARIMA no estacionales](https://otexts.com/fpp3/non-seasonal-arima.html); el ajuste usado aquí es condicional por mínimos cuadrados, y no reproduce el ajuste completo por máxima verosimilitud de paquetes estadísticos. [Referencia de estimación](https://otexts.com/fpp3/arima-estimation.html).

Los días sin cierres se completan para el cálculo diario con el último precio disponible, igual que la política existente de Holt; no se guardan como nuevos cierres. Se requieren al menos diez observaciones reales antes de proyectar. Se rechazan precios inválidos y proyecciones no positivas. Las probabilidades mantienen el método empírico y las limitaciones documentadas para los otros modelos.

## Verificación con el histórico local

| Activo | MAPE de validación | Ventana seleccionada | Predicciones futuras |
| --- | ---: | ---: | ---: |
| USD/PEN | 0,528% | 30 cierres | 7 |
| EUR/USD | 0,355% | 30 cierres | 7 |
| BTC/USD | 2,388% | 120 cierres | 7 |
| ETH/USD | 2,717% | 120 cierres | 7 |

Resultados del procedimiento de selección existente sobre el histórico local; no representan una prueba final independiente ni un porcentaje de acierto direccional.

Pruebas aprobadas: `tests/ArimaTest.php`, `tests/ForecastMethodsTest.php` y `tests/ForecastRolesHttpTest.php`. Sintaxis PHP correcta. Chrome verificó la selección de ARIMA y los datos enviados al gráfico mediante respuestas controladas y un sustituto de Chart.js. La página temporal se retiró.

## Posibles ampliaciones posteriores

Búsqueda de órdenes `(p,d,q)`, términos de medias móviles, diagnóstico de residuos, tratamiento más específico del calendario de divisas y evaluación final en fechas independientes. Estas ampliaciones no son necesarias para utilizar la versión de orden fijo implementada.

Estado: **ARIMA(1,1,0) disponible y verificado técnicamente**.
