# Pronósticos: probabilidades resaltadas — 9 de octubre de 2026

## Cambio pedido

Añadir las probabilidades de subida y bajada en Pronósticos y mostrarlas de manera resaltada.

## Cambio realizado

Cada tarjeta de dólar, euro, Bitcoin y Ethereum muestra dos bloques: **Prob. de subida**, en verde, y **Prob. de bajada**, en rojo, con porcentajes grandes. Aparecen para Usuario y Administrador y se actualizan con las predicciones y con el modelo elegido por el Administrador.

Los valores se incluyen en `probabilidades` en la API. No reutilizan la puntuación orientativa `confianza`, que sigue siendo una métrica diferente.

## Cálculo y límites

Se reproducen pronósticos pasados del método y ventana actuales, utilizando exclusivamente los cierres anteriores a cada fecha pronosticada. Se consideran hasta 60 puntos de corte recientes y se comparan los objetivos con cierres reales de la misma fecha. El horizonte incluye los días transcurridos desde el último cierre hasta hoy y los siete días de proyección.

Cada error relativo histórico se aplica al objetivo actual como un escenario alternativo. Se cuenta cuántos escenarios quedan por encima o por debajo del último cierre. Se añade una observación por dirección para evitar extremos de certeza: subida = `100 × (escenarios de subida + 1) / (escenarios evaluados + 2)`; bajada es su complemento. Los escenarios sin cambio se reparten por igual entre ambas direcciones.

Se requieren al menos diez comparaciones válidas. Si faltan datos o el horizonte desde el último cierre supera 30 días, se muestran **--** y un mensaje de histórico insuficiente. Los porcentajes disponibles suman 100% y usan un decimal.

Son estimaciones basadas en la distribución de errores históricos, bajo el supuesto de que esos errores representan escenarios útiles para la predicción actual. Las comparaciones pueden compartir fechas y el método y ventana se seleccionan sobre el mismo histórico. No constituyen probabilidades calibradas de forma independiente ni garantizan resultados futuros. Esta limitación se resume junto a las tarjetas.

## Archivos y verificación

- `app/models/ForecastMethods.php`: cálculo de escenarios y probabilidades.
- `app/models/Pronostico.php`: integración en la respuesta de cada activo.
- `public/assets/js/forecast.js`, `public/assets/css/forecast.css` y `app/views/public/pronosticos.php`: bloques resaltados y explicación.
- Pruebas matemáticas aprobadas para subida, bajada, equilibrio en una serie plana, suma de porcentajes e histórico insuficiente.
- Pruebas HTTP aprobadas para los cuatro activos y ambos roles, incluyendo estructura y coherencia de probabilidades.
- Chrome: verificó los dos bloques en las cuatro tarjetas de ambos roles y las interacciones existentes, con respuestas controladas y un sustituto de Chart.js.
- Sintaxis PHP y revisión de diferencias correctas. Página temporal de comprobación retirada.

Estado: **implementado y verificado técnicamente**.

## Ampliación: información al pasar sobre el gráfico

Solicitud adicional: mostrar probabilidades y precio pronosticado al pasar sobre los puntos, con una presentación más grande.

El cuadro flotante de Chart.js ahora muestra el activo y la fecha completa, el precio del punto en la moneda correspondiente, el objetivo y cambio estimado a siete días y las probabilidades de subida y bajada. Distingue entre cierre registrado, precio pronosticado y conexión estimada. Las probabilidades corresponden al objetivo de siete días, no a cada fecha individual del gráfico; esa referencia se indica explícitamente. Cuando faltan datos se muestra el mensaje correspondiente.

Se aumentó el cuerpo del texto a 18 px en escritorio y 15 px en pantallas pequeñas, con título de 17 px, relleno de 18 px y mayor separación entre líneas. Los puntos tienen mayor tamaño y área de detección para facilitar el uso del ratón y el tacto.

Archivos: `public/assets/js/forecast.js` y `tests/fixtures/forecast-browser.php`. Pruebas HTTP aprobadas. La comprobación de Chrome con el sustituto de Chart.js verificó el contenido de los callbacks del cuadro, las unidades y tamaños configurados para los cuatro activos y ambos roles; no verificó el dibujo real del cuadro en el canvas. La página temporal se retiró.
