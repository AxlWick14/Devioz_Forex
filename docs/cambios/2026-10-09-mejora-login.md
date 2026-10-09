# Mejora del login — 9 de octubre de 2026

Fecha de referencia: America/Lima.

## Cambio pedido

Mejorar el login de la plataforma.

## Cambio realizado

- Nuevo diseño en dos columnas: presentación de Devioz FX y formulario de acceso destacado.
- En móvil, el formulario aparece primero y se reduce el contenido decorativo.
- Colores y superficies adaptados a los temas nocturno, carmesí y diurno.
- Botón **Mostrar / Ocultar** contraseña con estado accesible. Sin JavaScript, el campo de contraseña sigue funcionando y el botón permanece oculto.
- Acceso destacado a **Crear una cuenta** y enlace para volver al inicio.
- Mensajes diferenciados para errores, cuenta creada y herramientas que requieren iniciar sesión.
- Conservación del correo introducido al fallar el acceso; la contraseña no se vuelve a incluir en el HTML.
- Etiquetas, autocompletado, teclado de correo en móviles y enfoque visible para navegación con teclado.

El gráfico de presentación es una ilustración decorativa, sin cotizaciones ni valores de mercado. El formulario mantiene el ingreso mediante POST y el token CSRF existentes.

## Archivos

- `app/views/auth/login.php`: estructura visual y formulario.
- `public/assets/css/accounts.css`: estilos específicos del login y adaptación móvil.
- `public/assets/js/login.js`: control para mostrar u ocultar la contraseña.
- `README.md`: enlace a este registro.

## Verificación

- Sintaxis PHP correcta.
- `tests/AccountsHttpTest.php`: pruebas existentes de autenticación, registro, permisos, CSRF y gestión de cuentas aprobadas.
- Chrome con la página real: mostrar y ocultar conserva el valor del campo; registro accesible; formulario POST con CSRF; sin desbordamiento horizontal en escritorio para los tres temas ni a 390 px; formulario primero y botón de al menos 44 px en móvil.
- La página temporal de comprobación se retiró al finalizar.

Estado: **implementado y verificado técnicamente**.
