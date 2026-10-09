# Login con imagen, GIF y animaciones — 9 de octubre de 2026

## Cambio pedido

Añadir imágenes reales, GIFs y animaciones al diseño del login.

## Cambio realizado

- Se sustituyó el gráfico decorativo por una fotografía real de la Bolsa de Nueva York, con encuadre animado lento.
- Se añadió un GIF del planeta girando en la tarjeta **Mercado global**.
- El formulario aparece con una transición breve de entrada.
- El botón **Pausar animación** detiene el movimiento de la fotografía y sustituye el GIF por un globo vectorial fijo. Permite reactivar el movimiento.
- La preferencia de reducir movimiento desactiva las animaciones y utiliza el globo fijo. Sin JavaScript, se muestra el globo fijo y el formulario funciona normalmente.
- La fotografía y el GIF se sirven desde archivos locales. Se conservan el diseño según el tema y la prioridad del formulario en móvil.
- Se incluyen créditos visibles y fuentes/licencias en `public/assets/images/login/SOURCES.md`. Ambos recursos externos usan CC BY-SA 3.0.

## Archivos

`app/views/auth/login.php`, `public/assets/css/accounts.css`, `public/assets/js/login.js` y `public/assets/images/login/`.

## Verificación

- Fotografía válida de 500 × 586 px; GIF válido de 400 × 400 px, 44 fotogramas y aproximadamente 1 MB.
- Sintaxis PHP correcta y pruebas existentes de autenticación aprobadas.
- Chrome: foto y GIF cargados, animación de fotografía activa, pausa/reactivación correctas, tres temas sin desbordamiento horizontal, móvil de 390 px sin desbordamiento y formulario primero.
- Preferencia de reducir movimiento simulada al inicializar JavaScript: globo fijo y control de animación oculto.
- Página temporal de comprobación retirada al finalizar.

Estado: **implementado y verificado técnicamente**.
