# Cambios 4 — Home — 9 de octubre de 2026

Fecha de referencia: America/Lima.

## Cambio pedido

Mostrar el punto 1 de **Empieza por lo importante** también cuando el visitante no haya iniciado sesión. Añadir un candado pequeño que, al pulsarlo, abra el acceso para iniciar sesión o registrarse con sus datos.

## Cambio realizado

- La tarjeta **01 · Overview** aparece para Invitado, Usuario y Administrador.
- Para Invitado, muestra un candado de 16 px y el texto **Inicia sesión o crea una cuenta para acceder**.
- Pulsar el candado o cualquier parte de la tarjeta lleva a `login?restringido=1`. En esa pantalla se puede iniciar sesión o pulsar **Crear cuenta** para abrir el registro con correo y contraseña.
- Para Usuario y Administrador, la tarjeta lleva directamente a Overview y muestra la flecha habitual.
- El contador muestra **03 herramientas** para todos los visitantes.
- El candado utiliza el color del tema seleccionado. La tarjeta permite navegación con teclado y tiene un contorno de enfoque visible y un nombre accesible que explica el acceso requerido.

## Archivos

- `app/views/public/home.php`: tarjeta visible para todos, destino según permisos, candado y contador.
- `public/assets/css/theme.css`: color del candado y contorno de enfoque.
- `README.md`: enlace a este registro.

## Verificación realizada

- Sintaxis PHP de Home: sin errores.
- Consulta HTTP sin sesión: confirmó el punto 01, el candado, el enlace a login y el contador de tres herramientas.
- Consulta HTTP a login: confirmó el enlace al registro.
- `tests/AccountsHttpTest.php`: aprobó las comprobaciones existentes de acceso de invitados y usuarios, login, registro, administración y permisos.
- Revisión de diferencias: sin errores de espacios.

Estado: **implementado y verificado**.
