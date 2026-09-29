# Spec 002: Libros

- **Estado:** aprobada
- **Fecha:** 2026-09-29
- **Depende de:** [001 Usuarios y roles](001-usuarios-y-roles.md) y [004 Estantería virtual](004-estanteria-virtual.md) (estructura de ubicaciones)

## Objetivo

Que cada usuario gestione los libros de su biblioteca física (alta, consulta, edición y borrado) y que los administradores puedan gestionar los libros de cualquier usuario.

## Alcance

- Alta rápida de libros con datos básicos y portada.
- Edición completa con datos adicionales, estado de lectura, préstamo, ubicación física y condición.
- Listado con búsqueda, filtros, ordenación y paginación.
- Borrado de libros.
- Vistas de administración: listado global y biblioteca de cada usuario.
- Borrado en cascada de los libros al eliminar una cuenta.

## Fuera de alcance

- Catálogo de géneros gestionable (el género es texto libre).
- Búsqueda de datos del libro en servicios externos por ISBN.
- Préstamos entre usuarios del sistema (quien recibe el préstamo es solo un nombre).

## Datos del libro

| Campo | Tipo / valores | Obligatorio | Formulario |
|---|---|---|---|
| Título | Texto | Sí | Alta y edición |
| Autor | Texto | No | Alta y edición |
| ISBN | ISBN-10 o ISBN-13 válido | No | Alta y edición |
| Editorial | Texto | No | Alta y edición |
| Portada | Imagen (ver RF-03) | No | Alta y edición |
| Año de publicación | Año | No | Edición |
| Género | Texto libre | No | Edición |
| Idioma | Texto | No | Edición |
| Número de páginas | Entero positivo | No | Edición |
| Estado de lectura | Pendiente / Leyendo / Leído / Prestado | No | Edición |
| Valoración | 1 a 5 | No | Edición |
| Notas | Texto largo | No | Edición |
| Prestado a | Texto (nombre de una persona) | Sí, si el estado es Prestado | Edición |
| Fecha de préstamo | Fecha | Sí, si el estado es Prestado | Edición |
| Ubicación: hueco | Un hueco de una estantería del propietario (spec 004) | No | Edición |
| Posición | Entero ≥ 1: orden de izquierda a derecha dentro del hueco | Sí, si tiene hueco | Automática (ver RF-05c) |
| Condición | Nuevo / Muy bueno / Bueno / Aceptable / Deteriorado | No | Edición |

La ubicación es "todo o nada": o el libro está en un hueco concreto, del que se deducen la balda, la estantería y la sala, o no tiene ubicación. Las salas, estanterías, baldas y huecos se definen en la [spec 004](004-estanteria-virtual.md).

## Requisitos

### RF-01 Propiedad
- Todo libro pertenece a un único usuario (su propietario).
- Un usuario solo puede ver, crear, editar y eliminar sus propios libros.
- Los administradores y el super administrador pueden ver, crear, editar y eliminar los libros de cualquier usuario.

### RF-02 Alta de libro
- El formulario de alta contiene: título, autor, ISBN, editorial y portada.
- El único campo obligatorio es el título.
- Un libro creado por un usuario pertenece a ese usuario.
- Un administrador puede crear un libro en la biblioteca de otro usuario, indicando su propietario.

### RF-03 Portada
- Al elegir la portada, la persona decide entre hacer una foto con la cámara (en dispositivos que la tengan) o subir un archivo.
- Formatos aceptados al subir: JPG, PNG y WebP, con un máximo de 10 MB.
- La imagen se convierte a WebP y se redimensiona manteniendo su proporción, con un máximo de 1200 px en el lado mayor.
- El archivo almacenado no puede superar los 2 MB. Si tras convertirla y redimensionarla supera ese tamaño, se reduce la calidad hasta que quepa, se muestra a la persona cómo quedaría y solo se guarda si lo confirma.
- En la edición se puede sustituir la portada o quitarla sin poner otra.
- Al quitar la portada o eliminar un libro se elimina también el archivo de la portada.

### RF-04 ISBN
- Es opcional. Si se indica, debe ser un ISBN-10 o ISBN-13 válido.
- No es único: puede repetirse en varios libros.
- Si el ISBN ya existe en la biblioteca del propietario del libro, se avisa y se pide confirmación antes de guardar. Si se confirma, se guarda; si no, no se guarda.
- La comprobación de duplicados se hace solo contra la biblioteca del propietario del libro, no contra todo el sistema. Cuando un administrador crea o edita un libro de otro usuario, se comprueba contra la biblioteca de ese usuario.

### RF-05 Edición de libro
- El formulario de edición permite modificar todos los campos de la tabla de datos.
- Se aplican las mismas reglas de validación que en el alta (RF-02, RF-03 y RF-04).

### RF-05b Préstamos
- Cuando el estado de lectura es Prestado, son obligatorios "prestado a" y la fecha de préstamo.
- Cada préstamo queda registrado en un historial de préstamos del libro. Los datos de un préstamo no se borran al cambiar a otro estado.
- Cuando el estado cambia de Prestado a otro, se registra automáticamente la fecha de devolución en ese préstamo.
- Un usuario no puede editar ni borrar los préstamos del historial. Los administradores y el super administrador sí.
- Cuando un préstamo lleva más de 2 meses activo (sin devolución) desde su fecha de préstamo, se marca en la base de datos con un indicador booleano de tiempo superado. Ese indicador se usará en la spec de diseño de interfaz; esta spec no define cómo se muestra.
- La comprobación de préstamos se ejecuta automáticamente todos los días a las 2:00.
- Al devolverse el libro (el estado deja de ser Prestado), la marca de tiempo superado del préstamo se quita.
- El historial muestra, para cada préstamo, los días que el libro ha estado prestado. Este dato no se guarda en la base de datos:
  - En un préstamo devuelto, se calcula entre la fecha de préstamo y la de devolución, y deja de contar al devolverse.
  - En un préstamo activo, se calcula hasta el día actual, así que aumenta cada día.
- El historial no aparece en la navegación. Solo se accede a él mediante un enlace, que se definirá en la spec de diseño de interfaz de usuario.
- Pueden ver el historial de un libro su propietario y los administradores (RF-01).

### RF-05c Ubicación y posición
- En la edición, la ubicación se elige con selectores encadenados (sala → estantería → balda → hueco) entre la estructura del propietario del libro, o se deja sin ubicación.
- Al asignar un hueco desde la edición, el libro se coloca en la última posición de ese hueco.
- Al quitar un libro de un hueco, las posiciones de los libros que quedan en ese hueco se renumeran sin saltos.
- La colocación y reordenación visual se hace en la estantería virtual (spec 004).

### RF-06 Listado de libros (usuario)
- Muestra los libros del propio usuario.
- Búsqueda por título y autor.
- El filtro de género usa coincidencia parcial: basta con que el campo contenga el texto, sin distinguir mayúsculas y minúsculas.
- Cuando hay texto de búsqueda, los resultados que coinciden por título o autor aparecen antes que el resto.
- Filtros:
  - Género (texto).
  - Estado de lectura y condición, cada uno con la opción adicional "Sin especificar".
  - Ubicación física: un selector por cada nivel (sala, estantería, balda y hueco), con los valores de la estructura del propietario y combinables entre sí. El de estantería tiene prioridad y es el primero que se muestra. La opción "Sin especificar" muestra los libros sin ubicación.
- Ordenación por título, autor y fecha de alta. Por defecto, por fecha de alta descendente (los más recientes primero).
- Paginación de 15 libros por página.

### RF-07 Listado global (administradores)
- Solo lo ven los administradores y el super administrador.
- Muestra los libros de todos los usuarios e indica el propietario de cada uno.
- Tiene todas las funciones de RF-06 más un filtro por propietario.

### RF-08 Biblioteca de un usuario (administradores)
- Desde el panel de gestión de usuarios (spec 001), un administrador puede entrar en la biblioteca de cualquier usuario y gestionar sus libros.

### RF-09 Borrado de libro
- El propietario y los administradores pueden eliminar un libro.
- Antes de eliminarlo se pide confirmación.

### RF-10 Eliminación de cuenta
- Al eliminar una cuenta de usuario (spec 001), se eliminan también todos sus libros y sus portadas.

## Criterios de aceptación

### Propiedad (RF-01)
- **CA-01** Dado un usuario, cuando accede a un libro suyo, entonces lo ve.
- **CA-02** Dado un usuario, cuando intenta ver, editar o eliminar un libro de otro usuario, entonces recibe un 403.
- **CA-03** Dado un administrador, cuando accede a un libro de cualquier usuario, entonces lo ve y puede editarlo y eliminarlo.
- **CA-04** Dado un visitante sin sesión, cuando accede a cualquier página de libros, entonces se le redirige al inicio de sesión.

### Alta (RF-02)
- **CA-05** Dado un usuario, cuando crea un libro indicando solo el título, entonces el libro se guarda en su biblioteca.
- **CA-06** Dado un usuario, cuando intenta crear un libro sin título, entonces se rechaza y se muestra un error asociado al campo título.
- **CA-07** Dado un administrador, cuando crea un libro indicando otro usuario como propietario, entonces el libro se guarda en la biblioteca de ese usuario.
- **CA-08** Dado un usuario, cuando envía el formulario de alta, entonces no puede asignar el libro a otro propietario.

### Portada (RF-03)
- **CA-09** Dado un usuario, cuando sube una imagen JPG, PNG o WebP de hasta 10 MB, entonces se almacena como WebP, con un lado mayor de 1200 px como máximo y un tamaño de 2 MB como máximo.
- **CA-10** Dada una imagen de menos de 1200 px en su lado mayor, cuando se sube, entonces se convierte a WebP sin agrandarla.
- **CA-11** Dado un usuario, cuando sube un archivo de otro formato o de más de 10 MB, entonces se rechaza con un error asociado al campo portada.
- **CA-12** Dado un dispositivo con cámara, cuando se abre el selector de portada, entonces se ofrece hacer una foto o elegir un archivo.
- **CA-12b** Dada una imagen que tras la conversión supera los 2 MB, cuando se sube, entonces se reduce la calidad, se muestra una vista previa y no se guarda hasta que la persona confirma. Si confirma, se guarda un archivo de 2 MB como máximo; si cancela, la portada no cambia.
- **CA-12c** Dado un libro con portada, cuando en la edición se quita la portada, entonces el libro queda sin portada y se elimina el archivo.

### ISBN (RF-04)
- **CA-13** Dado un ISBN-10 o ISBN-13 válido, cuando se guarda el libro, entonces se acepta.
- **CA-14** Dado un ISBN con formato inválido, cuando se guarda el libro, entonces se rechaza con un error asociado al campo ISBN.
- **CA-15** Dado un ISBN que ya existe en la biblioteca del propietario, cuando se guarda el libro, entonces se avisa y se pide confirmación; si se confirma, se guarda, y si no, no se guarda.
- **CA-16** Dado un ISBN que solo existe en la biblioteca de otro usuario, cuando se guarda el libro, entonces se guarda sin aviso.
- **CA-17** Dado un administrador que crea un libro para el usuario A, cuando el ISBN ya existe en la biblioteca de A, entonces se avisa; y cuando solo existe en la biblioteca del propio administrador, entonces no se avisa.

### Edición (RF-05)
- **CA-18** Dado un libro, cuando su propietario modifica cualquiera de los campos con datos válidos, entonces se guardan los cambios.
- **CA-19** Dado un libro, cuando en la edición se le asigna un hueco de una estantería de su propietario, entonces se guarda con posición al final de ese hueco.
- **CA-19b** Dado un libro, cuando se intenta asignarle un hueco de una estantería de otro usuario, entonces se rechaza.
- **CA-19c** Dado un libro con hueco, cuando en la edición se le quita la ubicación, entonces queda sin hueco ni posición.
- **CA-20** Dada una valoración fuera del rango 1 a 5, o una condición o un estado que no están en la lista, cuando se guarda, entonces se rechaza con un error asociado al campo.

### Préstamos (RF-05b)
- **CA-20b** Dado un libro, cuando se pone el estado Prestado sin indicar a quién o la fecha, entonces se rechaza con un error asociado al campo que falta.
- **CA-20c** Dado un libro prestado, cuando se cambia a otro estado, entonces el préstamo sigue guardado en su historial.
- **CA-20d** Dado un libro que se ha prestado varias veces, cuando su propietario abre el historial de préstamos, entonces ve todos los préstamos.
- **CA-20e** Dado un usuario, cuando intenta abrir el historial de préstamos de un libro de otro usuario, entonces recibe un 403.
- **CA-20f** Dado un libro prestado, cuando se cambia a otro estado, entonces el préstamo del historial queda con la fecha de devolución de ese día.
- **CA-20g** Dado un usuario, cuando intenta editar o borrar un préstamo del historial, incluso de un libro suyo, entonces recibe un 403.
- **CA-20h** Dado un administrador, cuando edita o borra un préstamo del historial, entonces se aplica el cambio.
- **CA-20i** Dado un préstamo activo con fecha de préstamo de hace más de 2 meses, cuando se ejecuta la comprobación de préstamos, entonces queda marcado con tiempo superado.
- **CA-20j** Dado un préstamo activo con fecha de préstamo de hace 2 meses o menos, cuando se ejecuta la comprobación, entonces no se marca.
- **CA-20k** Dado un préstamo devuelto antes de cumplir 2 meses, cuando se ejecuta la comprobación, entonces no se marca.
- **CA-20l** Dada la programación de tareas de la aplicación, entonces la comprobación de préstamos está programada todos los días a las 2:00.
- **CA-20m** Dado un préstamo con fecha de préstamo 1 de marzo y fecha de devolución 11 de marzo, cuando se abre el historial, entonces muestra 10 días prestado.
- **CA-20n** Dado un préstamo activo con fecha de préstamo de hace 5 días, cuando se abre el historial, entonces muestra 5 días prestado.
- **CA-20o** Dado un préstamo marcado con tiempo superado, cuando se devuelve el libro, entonces la marca se quita y el historial sigue mostrando los días que estuvo prestado.

### Listado del usuario (RF-06)
- **CA-21** Dado un usuario con libros, cuando abre su listado sin elegir ordenación, entonces ve solo sus libros, 15 por página, con los más recientes primero.
- **CA-21b** Dado un filtro con la opción "Sin especificar" (por ejemplo, en condición), cuando se aplica, entonces solo se muestran los libros que no tienen ese campo relleno.
- **CA-21c** Dados filtros de estantería y balda, cuando se aplican a la vez, entonces solo se muestran los libros que cumplen los dos.
- **CA-21d** Dado el filtro de género "nov", cuando se aplica, entonces se muestran también los libros con género "Novela".
- **CA-21e** Dado el filtro de sala, cuando se aplica, entonces se muestran los libros ubicados en cualquier hueco de cualquier estantería de esa sala.
- **CA-22** Dado un texto de búsqueda, cuando se busca, entonces se muestran los libros cuyo título o autor lo contienen.
- **CA-23** Dado un filtro de género, estado, ubicación o condición, cuando se aplica, entonces solo se muestran los libros que lo cumplen.
- **CA-24** Dada una ordenación por título, autor o fecha de alta, cuando se aplica, entonces el listado se ordena por ese campo.

### Listado global (RF-07)
- **CA-25** Dado un administrador, cuando abre el listado global, entonces ve los libros de todos los usuarios junto con su propietario.
- **CA-26** Dado un administrador, cuando filtra por propietario, entonces solo ve los libros de ese usuario.
- **CA-27** Dado un usuario, cuando intenta abrir el listado global, entonces recibe un 403.

### Biblioteca de un usuario (RF-08)
- **CA-28** Dado un administrador en el panel de usuarios, cuando entra en la biblioteca de un usuario, entonces ve y gestiona los libros de ese usuario.

### Borrado (RF-09)
- **CA-29** Dado un libro, cuando su propietario lo elimina y confirma, entonces se eliminan el libro y su portada.
- **CA-30** Dado un libro, cuando se cancela la confirmación de borrado, entonces el libro no se elimina.

### Eliminación de cuenta (RF-10)
- **CA-31** Dado un usuario con libros, cuando se elimina su cuenta, entonces se eliminan también sus libros y sus portadas.

## Decisiones tomadas

- La marca de tiempo superado se quita al devolver el libro.
- Los días prestado se calculan sin guardarse: hasta hoy si el préstamo sigue activo, y hasta la devolución si ya se devolvió.
- La comprobación de préstamos se ejecuta todos los días a las 2:00.
- Los filtros de texto usan coincidencia parcial y la búsqueda da prioridad a título y autor.
- La fecha de devolución se registra automáticamente al dejar el estado Prestado.
- Los préstamos del historial solo los pueden editar o borrar los administradores y el super administrador.
- Ubicación estructurada (sala → estantería → balda → hueco, más la posición), "todo o nada" (spec 004). Hay un filtro por nivel, combinables, y el de estantería tiene prioridad.
- El listado se ordena por defecto por fecha de alta descendente.
- Los datos del préstamo son obligatorios cuando el estado es Prestado y se conservan en un historial. El acceso al historial se definirá en la spec de interfaz de usuario.
- La portada se puede quitar sin sustituirla.
- Una portada de más de 2 MB tras la conversión se comprime, se muestra una vista previa y se guarda solo si la persona confirma.
- Los filtros de estado, condición y ubicación incluyen la opción "Sin especificar".
