# Selección de cinco modelos — 9 de octubre de 2026

## Solicitud

Determinar cuáles modelos conviene conservar según la evidencia científica y reducir las once opciones de Pronósticos a cinco.

## Selección realizada

| Modelo | Motivo de conservación | Estado |
| --- | --- | --- |
| Regresión lineal | Referencia simple para tendencias; ya permite evaluar su error local. | Disponible |
| Regresión logarítmica | Representa cambios proporcionales y genera precios positivos. | Disponible |
| Holt amortiguado | Representa nivel y tendencia con amortiguación. | Disponible |
| ARIMA | Candidato estadístico para comparar dependencia temporal. | Pendiente |
| XGBoost | Candidato de árboles para comparar relaciones no lineales entre variables históricas. | Pendiente |

Esta selección es una decisión práctica para el proyecto, que conserva los tres métodos implementados y dos candidatos distintos para desarrollar. No es un ranking científico universal de precisión. ARIMA y XGBoost no generan predicciones todavía y siguen identificados como pendientes.

Se retiraron las otras seis opciones del selector y sus descripciones en JavaScript. La opción combinada LightGBM / XGBoost se concretó en XGBoost y ARIMA quedó identificado por su nombre propio. Se retiró el enlace de prueba ExtraTrees de esta página para dejar únicamente las cinco opciones solicitadas.

## Comparación con el histórico local

Resultados obtenidos con la validación temporal y selección de ventanas existentes en el proyecto. MAPE indica error porcentual absoluto medio: cuanto menor, mejor.

| Activo | Lineal | Logarítmica | Holt | Menor error |
| --- | ---: | ---: | ---: | --- |
| USD/PEN | 0,850% | 0,850% | 0,535% | Holt |
| EUR/USD | 0,608% | 0,612% | 0,357% | Holt |
| BTC/USD | 3,033% | 3,354% | 3,155% | Lineal |
| ETH/USD | 3,296% | 3,350% | 3,234% | Holt |

Estos errores provienen del procedimiento usado para seleccionar ventanas y modelos, con comparaciones que pueden compartir fechas. No representan una evaluación final independiente ni porcentajes de acierto direccional. El ganador puede cambiar al incorporar nuevos datos. No se compararon los modelos pendientes porque aún no están implementados.

## Fundamento científico

La precisión debe evaluarse con datos que no se utilizaron para ajustar el modelo y con el horizonte que se quiere pronosticar. Para determinar un ganador definitivo por activo, se requiere una prueba temporal final independiente y comparar también con mantener el último cierre. Fuente: [Hyndman y Athanasopoulos, evaluación de precisión](https://otexts.com/fpp3/accuracy.html).

El estudio [Zeng et al., AAAI 2023](https://ojs.aaai.org/index.php/AAAI/article/view/26317) encontró ventajas de modelos lineales sencillos frente a determinados Transformers en sus conjuntos de series temporales a largo plazo. Esto apoya que la complejidad por sí sola no asegura mayor precisión; no demuestra qué modelo gana en las divisas o criptomonedas de este proyecto.

## Archivos y validación

- `app/views/public/pronosticos.php`: cinco opciones y texto de disponibilidad.
- `public/assets/js/forecast.js`: nombres y descripciones de las opciones conservadas.
- Sintaxis PHP correcta y pruebas HTTP de Pronósticos para ambos roles aprobadas. Revisión de diferencias sin errores de espacios.

Estado: **selector reducido a cinco opciones; tres disponibles y dos pendientes**.
