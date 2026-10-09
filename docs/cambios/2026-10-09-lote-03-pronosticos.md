# Lote 03 — Pronósticos — 9 de octubre de 2026

Fecha de referencia: America/Lima.

## Cambios pedidos

1. Para Usuario, retirar los datos y controles técnicos de la imagen proporcionada y mostrar un gráfico claro que facilite comparar los activos.
2. Mostrar predicciones de dólar, euro, Bitcoin y Ethereum para Usuario y Administrador.
3. Mantener para Administrador el selector y la posibilidad de escoger el modelo.

## Cambios realizados

### Vista de Usuario

Se retiraron del HTML el bloque de completar históricos, sus mensajes de sincronización, el selector y las descripciones de modelos, el botón de prueba ExtraTrees, las métricas de validación (MAPE, ventanas, cierres, medias y error base), el resumen técnico superior, las herramientas y el panel de puntuación.

Se muestran cuatro tarjetas con la dirección de la predicción, el cambio porcentual a siete días, el último cierre, el precio previsto y un botón para ver el gráfico. El gráfico utiliza un modelo escogido automáticamente por activo entre regresión lineal, regresión logarítmica y Holt amortiguado, según el menor MAPE en la validación temporal existente. Cada método sigue evaluando ventanas de 30, 60 y 120 cierres. Se descartan modelos que no produzcan una proyección válida. Si hay datos para proyectar pero no para validar, se utiliza el primer método válido; no se inventa un error de validación.

Al cargar, se abre el gráfico del activo con el mayor cambio porcentual previsto. Si ese cambio es positivo, se destaca como **Mayor subida estimada**. Si ninguno sube, se informa **Ningún activo presenta una subida estimada a 7 días**. El usuario puede cambiar entre los cuatro gráficos y conservar su selección al actualizar. Este criterio compara cambios estimados y no garantiza un resultado de inversión.

### Activos para ambos roles

| Activo | Par | Unidad del precio |
| --- | --- | --- |
| Dólar | `USD/PEN` | Soles (PEN) por dólar |
| Euro | `EUR/USD` | Dólares (USD) por euro |
| Bitcoin | `BTC/USD` | Dólares (USD) por Bitcoin |
| Ethereum | `ETH/USD` | Dólares (USD) por Ethereum |

Estos cuatro activos sustituyen el conjunto anterior de Bitcoin, Ethereum, Tether y BNB en Pronósticos. Las predicciones reflejan la dirección calculada: subida, bajada o ausencia de cambio. Si falta histórico suficiente, la vista de Usuario indica que el activo no tiene predicción disponible.

Las unidades del gráfico, sus etiquetas, los detalles administrativos y el texto accesible se adaptan al par seleccionado. Se mantienen separados los cierres observados, la conexión estimada y la proyección a siete días.

### Vista de Administrador

Conserva el selector de modelos, sus descripciones, las métricas y las herramientas. Puede elegir regresión lineal, regresión logarítmica o Holt amortiguado; el servidor respeta la elección para los cuatro activos. Los modelos que ya aparecían como pendientes mantienen ese estado.

El botón de sincronización incluye dólar, euro, Bitcoin y Ethereum. La API también admite la selección automática. Para Usuario, el servidor aplica siempre selección automática, aunque se intente enviar un método manual en la URL.

## Archivos principales

- `app/views/public/pronosticos.php`: contenido distinto por rol y botones para los cuatro activos.
- `app/models/ForecastMethods.php`: selección automática por menor error histórico.
- `app/models/Pronostico.php`: los cuatro pares y proyección con selección automática.
- `app/controllers/PronosticoController.php`: elección de modelo por rol.
- `app/controllers/HistorialController.php`: permite sincronizar dólar y euro.
- `public/assets/js/forecast.js`: vista simplificada, comparación, selección del gráfico y soporte para controles ausentes.
- `public/assets/css/forecast.css`: tarjetas y gráfico adaptados a pantallas grandes y móviles.

## Verificación

- Sintaxis PHP correcta en vistas, cálculos y controladores modificados.
- `tests/ForecastMethodsTest.php`: proyecciones, validación temporal, selección del menor error y comportamiento con histórico insuficiente.
- `tests/ForecastRolesHttpTest.php`: acceso autenticado con ambos roles, cuatro predicciones reales sobre el histórico local, selección automática para Usuario incluso con parámetros manuales, respeto de los tres modelos para Administrador y ausencia/presencia de los controles técnicos en el HTML.
- Comprobación en Chrome con el HTML de ambos roles y el JavaScript de la aplicación: cambios entre los cuatro activos, unidades correctas, proyección enviada al gráfico, selección de Holt, sincronización de dólar/euro y ausencia de sugerencia alcista cuando todos bajan. Esta prueba utiliza respuestas controladas y un sustituto de Chart.js para verificar las interacciones y los datos del gráfico; no valida el dibujo del canvas de Chart.js.
- Las cuentas temporales de pruebas se eliminan al finalizar. La página temporal de comprobación del navegador se retiró.

Para revisar el dibujo final, iniciar sesión con cada rol, abrir **Pronósticos**, cambiar entre los cuatro activos y, como Administrador, probar los tres modelos disponibles.

Estado: **los tres puntos implementados y verificados técnicamente**.

## Cambio posterior: retirar Herramientas para todos

Se solicitó eliminar el miniapartado **Herramientas** de Pronósticos para todos los roles. Se retiró de la vista el bloque desplegable con los enlaces a Proceso ETL y Predictor Bitcoin, junto con sus estilos CSS exclusivos. El bloque ya no se renderiza para Usuario ni Administrador.

Archivos: `app/views/public/pronosticos.php` y `public/assets/css/forecast.css`. Verificación: sintaxis PHP correcta y pruebas HTTP de Pronósticos para ambos roles aprobadas.
