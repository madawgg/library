# Spec 001: Usuarios y roles

- **Estado:** aprobada
- **Fecha:** 2026-09-29

## Objetivo

Permitir que las personas tengan una cuenta en la aplicación y establecer los roles (usuario, administrador y super administrador) que determinan a qué recursos puede acceder cada una.

## Alcance

- Gestión de la propia cuenta: registro, inicio y cierre de sesión, recuperación de contraseña, verificación de email (preparada, sin activar) y perfil.
- Roles y sus reglas de acceso.
- Creación del super administrador.
- Comandos artisan para promover y degradar administradores.
- Panel de gestión de usuarios para administradores y super administrador.

## Fuera de alcance

- Recursos de la biblioteca (libros, etc.): cada uno tendrá su propia spec, que aplicará las reglas de acceso definidas aquí.
- Activar la verificación de email obligatoria.

## Roles

| Rol | Descripción |
|---|---|
| Usuario | Accede solo a sus propios recursos. |
| Administrador | Accede a los recursos de cualquier usuario. Gestiona cuentas con rol usuario. Puede haber varios. |
| Super administrador | Único. Está por encima de todos. Gestiona cuentas de usuario y de administrador. Nadie más puede modificarlo ni eliminarlo. |

## Requisitos

### RF-01 Registro público
- Cualquier visitante puede registrarse.
- Toda cuenta creada mediante el registro público tiene el rol usuario.

### RF-02 Inicio y cierre de sesión
- Una persona registrada puede iniciar sesión con su email y contraseña.
- Una persona con la sesión iniciada puede cerrarla.

### RF-03 Recuperación de contraseña
- Una persona registrada puede solicitar un email para restablecer su contraseña y fijar una nueva.

### RF-04 Verificación de email (preparada, no activada)
- La funcionalidad de verificación de email existe en la aplicación.
- De momento no se exige: un usuario con el email sin verificar puede usar la aplicación con normalidad.

### RF-05 Perfil propio
- Una persona con la sesión iniciada puede editar su nombre, su email y su contraseña.
- Puede eliminar su propia cuenta, salvo el super administrador (RF-06).

### RF-06 Super administrador
- Existe exactamente un super administrador, creado por el seeder.
- No puede eliminarse por ninguna vía, tampoco desde su propio perfil.
- Ningún otro usuario ni administrador puede modificarlo.
- Ni la interfaz ni los comandos pueden crear un segundo super administrador ni quitarle el rol.

### RF-07 Comandos artisan de administradores
- `php artisan admin:grant {email}` promueve a administrador a un usuario.
- `php artisan admin:revoke {email}` degrada a un administrador al rol usuario.
- Ninguno de los dos comandos afecta al super administrador.
- El sistema admite varios administradores.

### RF-08 Panel de gestión de usuarios
- Solo acceden los administradores y el super administrador.
- Permite listar, crear, editar (nombre y email) y eliminar usuarios.
- Al crear una cuenta desde el panel, quien la crea fija la contraseña (con confirmación).
- Un administrador solo gestiona cuentas con rol usuario: no puede editar ni eliminar a otros administradores ni al super administrador. Las cuentas que crea tienen el rol usuario.
- El super administrador gestiona cuentas de usuario y de administrador. Al crear una cuenta elige su rol: usuario o administrador.
- El super administrador puede cambiar el rol de una cuenta entre usuario y administrador desde el panel.

### RF-09 Acceso a recursos
- Un usuario solo puede ver y modificar sus propios recursos.
- Los administradores y el super administrador pueden acceder a los recursos de cualquier usuario.
- La autorización se comprueba en el servidor.

## Criterios de aceptación

### Registro (RF-01)
- **CA-01** Dado un visitante, cuando se registra con datos válidos, entonces se crea su cuenta con el rol usuario y queda con la sesión iniciada.
- **CA-02** Dado un visitante, cuando se registra con un email que ya existe, entonces se rechaza el registro y se muestra un error asociado al campo email.

### Sesión (RF-02)
- **CA-03** Dada una persona registrada, cuando inicia sesión con credenciales correctas, entonces accede a la aplicación.
- **CA-04** Dada una persona registrada, cuando inicia sesión con una contraseña incorrecta, entonces se deniega el acceso y se muestra un error.
- **CA-05** Dada una persona con la sesión iniciada, cuando cierra sesión, entonces deja de estar autenticada.
- **CA-06** Dado un visitante sin sesión iniciada, cuando accede a una zona protegida, entonces se le redirige al inicio de sesión.

### Recuperación de contraseña (RF-03)
- **CA-07** Dada una persona registrada, cuando solicita recuperar su contraseña, entonces se le envía un email con un enlace de restablecimiento.
- **CA-08** Dado un enlace de restablecimiento válido, cuando la persona fija una nueva contraseña, entonces puede iniciar sesión con ella.

### Verificación de email (RF-04)
- **CA-09** Dado un usuario con el email sin verificar, cuando accede a una zona protegida, entonces accede con normalidad.

### Perfil (RF-05)
- **CA-10** Dada una persona con la sesión iniciada, cuando edita su nombre y su email con datos válidos, entonces se guardan los cambios.
- **CA-11** Dada una persona con la sesión iniciada, cuando cambia su contraseña indicando correctamente la actual, entonces se guarda la nueva.
- **CA-12** Dado un usuario o administrador, cuando elimina su propia cuenta, entonces la cuenta se elimina y se cierra su sesión.

### Super administrador (RF-06)
- **CA-13** Dada una base de datos recién preparada, cuando se ejecuta el seeder, entonces existe exactamente un super administrador.
- **CA-14** Dado el super administrador, cuando intenta eliminar su propia cuenta, entonces se deniega.
- **CA-15** Dado un administrador, cuando intenta editar o eliminar al super administrador, entonces se deniega (403).
- **CA-16** Dado el super administrador, cuando se ejecuta sobre él el comando de degradar, entonces el comando falla y su rol no cambia.
- **CA-17** Dada una cuenta cualquiera, cuando el super administrador intenta asignarle el rol super administrador desde el panel, entonces no es posible.

### Comandos artisan (RF-07)
- **CA-18** Dado un usuario con rol usuario, cuando se ejecuta el comando de promover con su email, entonces pasa a ser administrador.
- **CA-19** Dado un administrador, cuando se ejecuta el comando de degradar con su email, entonces pasa a ser usuario.
- **CA-20** Dado un email que no existe, cuando se ejecuta cualquiera de los dos comandos, entonces el comando falla con un mensaje de error.
- **CA-21** Dado que ya existe un administrador, cuando se promueve a otro usuario, entonces ambos son administradores.

### Panel de gestión (RF-08)
- **CA-22** Dado un usuario con rol usuario, cuando accede al panel de gestión, entonces recibe un 403.
- **CA-23** Dado un administrador, cuando accede al panel, entonces ve el listado de usuarios.
- **CA-24** Dado un administrador, cuando crea un usuario con datos válidos (incluida la contraseña y su confirmación), entonces se crea con el rol usuario y puede iniciar sesión con esa contraseña.
- **CA-24b** Dado un administrador, cuando crea un usuario con una confirmación de contraseña que no coincide, entonces se rechaza y se muestra un error asociado al campo.
- **CA-24c** Dado el super administrador, cuando crea una cuenta eligiendo el rol administrador, entonces la cuenta se crea con el rol administrador.
- **CA-25** Dado un administrador, cuando edita o elimina una cuenta con rol usuario, entonces se aplica el cambio.
- **CA-26** Dado un administrador, cuando intenta editar o eliminar a otro administrador, entonces se deniega (403).
- **CA-27** Dado el super administrador, cuando edita o elimina una cuenta de usuario o de administrador, entonces se aplica el cambio.
- **CA-28** Dado el super administrador, cuando cambia el rol de una cuenta entre usuario y administrador, entonces se aplica el cambio.
- **CA-29** Dado un administrador, cuando intenta cambiar el rol de una cuenta, entonces se deniega.

### Acceso a recursos (RF-09)
- Los criterios concretos se definen en la spec de cada recurso, que debe cubrir como mínimo: el propietario accede, otro usuario recibe un 403 y un administrador accede.

## Decisiones tomadas

- Al eliminar una cuenta se eliminan también sus libros (ver spec 002, RF-10).
- La contraseña de una cuenta creada desde el panel la fija quien la crea.
- El super administrador puede crear directamente cuentas con el rol administrador.
- Comandos: `admin:grant` y `admin:revoke`.
