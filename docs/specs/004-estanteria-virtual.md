# Spec 004: Estantería virtual

- **Estado:** aprobada
- **Fecha:** 2026-09-29
- **Depende de:** [001 Usuarios y roles](001-usuarios-y-roles.md), [002 Libros](002-libros.md)

## Objetivo

Que cada usuario defina la estructura física de su biblioteca (salas, estanterías, baldas y huecos) y coloque sus libros en ella de forma visual, con una estantería dibujada en pantalla, arrastrar y soltar, y alternativas accesibles.

## Alcance

- Gestión de salas.
- Gestión de estanterías, con sus baldas y el número de huecos de cada balda.
- Vista "Estantería" del listado de libros, solo en pantallas de 1280 px o más.
- Mesa con los libros sin ubicación.
- Colocación y reordenación de libros arrastrando y soltando, o mediante un menú alternativo.

## Fuera de alcance

- Vista estantería en móvil o en pantallas de menos de 1280 px: allí solo se usan la lista o la cuadrícula (spec 003).
- Capacidad máxima de los huecos: no hay límite.

## Modelo

| Elemento | Datos | Pertenece a |
|---|---|---|
| Sala | Nombre | Un usuario |
| Estantería | Nombre | Una sala |
| Balda | Número (1..N, de arriba abajo) y nombre opcional | Una estantería |
| Hueco | Número (1..M, de izquierda a derecha) y nombre opcional | Una balda |
| Libro ubicado | Hueco y posición (spec 002) | Un hueco |

- Cada balda tiene su propio número de huecos, que puede ser distinto al de las demás baldas de la misma estantería.
- La posición es el orden del libro de izquierda a derecha dentro de su hueco, empezando en 1 y sin saltos.
- La ubicación de un libro es "todo o nada": o está en un hueco, o no tiene ubicación y aparece en la mesa.

## Requisitos

### RF-01 Propiedad
- Cada usuario gestiona sus propias salas y estanterías.
- Los administradores y el super administrador pueden gestionar las de cualquier usuario.
- Un libro solo puede colocarse en huecos de estanterías de su mismo propietario.

### RF-02 Salas
- El usuario puede crear, renombrar y eliminar salas.
- El nombre es obligatorio.
- Al eliminar una sala, previa confirmación, se eliminan también sus estanterías y todos sus libros pasan a la mesa.

### RF-03 Estanterías
- El usuario puede crear, editar y eliminar estanterías.
- Al crear o editar una estantería se indican su nombre (obligatorio), su sala (obligatoria), su número de baldas (al menos 1) y el número de huecos de cada balda (al menos 1).
- Cada balda y cada hueco pueden tener un nombre opcional (por ejemplo, "Poesía"). Cuando lo tienen, se muestra junto a su número.

### RF-04 Reducción y borrado de estructura
- Si se reduce el número de baldas o de huecos, o se elimina una estantería, los libros que estaban en las baldas o huecos eliminados quedan sin ubicación y pasan a la mesa.
- Los libros que se quedan en su hueco mantienen sus posiciones, renumeradas sin saltos si hace falta.

### RF-05 Vista estantería
- Es un modo más del selector de vistas del listado de libros, junto a Cuadrícula y Tabla (spec 003).
- Solo está disponible en pantallas de 1280 px o más. En pantallas más estrechas no se ofrece y se muestra la lista.
- Se muestra una estantería cada vez, elegida con un selector de sala y estantería.
- La estantería se dibuja con sus baldas y huecos, y en cada hueco sus libros en orden de posición.
- Cada libro se dibuja como un lomo con el título en vertical. Al pasar el ratón o enfocarlo con el teclado, se muestran su portada y sus datos principales.
- Junto a la estantería se muestra una mesa con los libros del propietario que no tienen ubicación:
  - Se representa de forma sencilla, con un tablero y algo de cuerpo inferior.
  - Los libros aparecen en orden alfabético por título, 8 cada vez.
  - Justo debajo de la mesa hay flechas de navegación para pasar a los 8 anteriores o siguientes, usables con ratón y con teclado.

### RF-06 Arrastrar y soltar
- Se puede arrastrar un libro:
  - De la mesa a un hueco.
  - De un hueco a otro hueco.
  - Dentro de un mismo hueco, para cambiar su posición.
  - De un hueco a la mesa, para quitarle la ubicación.
- Al soltar, el libro queda en la posición donde se suelta, y las posiciones del hueco de origen y del de destino se renumeran sin saltos.
- El cambio se guarda al soltar.

### RF-07 Alternativas a arrastrar (WCAG 2.2, criterio 2.5.7)
- Cada libro de la estantería y de la mesa tiene un menú con dos opciones, que se puede usar con ratón y con teclado:
  - **Mover a…:** abre un formulario para elegir estantería, balda, hueco y posición (o "Mesa"), y el libro se coloca al confirmar.
  - **Seleccionar y colocar:** selecciona el libro. Después, al activar un destino (un hueco, una posición entre libros o la mesa), el libro se coloca allí. Se puede cancelar con Escape.
- Los cambios de ubicación se anuncian a los lectores de pantalla.

## Criterios de aceptación

### Propiedad (RF-01)
- **CA-01** Dado un usuario, cuando intenta ver o modificar una sala o estantería de otro usuario, entonces recibe un 403.
- **CA-02** Dado un administrador, cuando gestiona las salas o estanterías de otro usuario, entonces se aplica el cambio.
- **CA-03** Dado un libro del usuario A, cuando se intenta colocar en un hueco de una estantería del usuario B, entonces se rechaza.

### Salas y estanterías (RF-02 y RF-03)
- **CA-04** Dado un usuario, cuando crea una sala con nombre, entonces se guarda. Sin nombre, se rechaza con un error asociado al campo.
- **CA-05** Dado un usuario, cuando crea una estantería con 3 baldas de 2, 4 y 3 huecos, entonces se guarda con esa estructura.
- **CA-06** Dada una estantería, cuando se intenta guardar con 0 baldas o con una balda de 0 huecos, entonces se rechaza.

### Reducción y borrado (RF-04)
- **CA-07** Dada una estantería con libros en la balda 3, cuando se reduce a 2 baldas, entonces esos libros quedan sin ubicación y aparecen en la mesa.
- **CA-08** Dada una balda con libros en el hueco 4, cuando se reduce a 3 huecos, entonces los libros del hueco 4 pasan a la mesa y el resto no cambia.
- **CA-09** Dada una estantería con libros, cuando se elimina, entonces todos sus libros pasan a la mesa.
- **CA-09b** Dada una sala con estanterías y libros, cuando se elimina y se confirma, entonces se eliminan sus estanterías y todos sus libros pasan a la mesa.

### Vista estantería (RF-05)
- **CA-10** Dada una pantalla de 1280 px o más, cuando se abre el selector de vistas, entonces aparece la opción Estantería.
- **CA-11** Dada una pantalla de menos de 1280 px, cuando se abre el listado, entonces la opción Estantería no aparece.
- **CA-12** Dada una estantería elegida, cuando se abre la vista, entonces se dibujan sus baldas y huecos con los libros como lomos en orden de posición, y la mesa muestra los libros sin ubicación del propietario.
- **CA-12b** Dados 20 libros sin ubicación, cuando se abre la vista, entonces la mesa muestra los 8 primeros por orden alfabético, y las flechas permiten pasar a los siguientes (9–16, luego 17–20) y volver atrás.
- **CA-12c** Dado un lomo enfocado con el teclado, cuando recibe el foco, entonces se muestran la portada y los datos principales del libro.
- **CA-12d** Dada una balda con nombre, cuando se dibuja la estantería, entonces se muestra su número junto a su nombre.
### Colocación (RF-06 y RF-07)
- **CA-13** Dado un libro en la mesa, cuando se arrastra a un hueco, entonces queda ubicado en ese hueco y en la posición donde se soltó.
- **CA-14** Dado un libro en la posición 1 de un hueco con 3 libros, cuando se mueve a la posición 3, entonces las posiciones quedan 1, 2 y 3 sin saltos y con el nuevo orden.
- **CA-15** Dado un libro ubicado, cuando se lleva a la mesa, entonces queda sin ubicación y las posiciones de su hueco anterior se renumeran.
- **CA-16** Dado un usuario de teclado, cuando usa "Mover a…" o "Seleccionar y colocar", entonces puede hacer todo lo anterior sin arrastrar.
- **CA-17** Dado "Seleccionar y colocar" en curso, cuando se pulsa Escape, entonces se cancela sin cambios.
- **CA-18** Dado cualquier cambio de ubicación, cuando se completa, entonces queda guardado al recargar la página.

## Decisiones tomadas

- Al eliminar una sala se eliminan sus estanterías y los libros pasan a la mesa, tras confirmación.
- Baldas y huecos tienen número y un nombre opcional.
- La mesa muestra los libros por orden alfabético, 8 cada vez, con flechas de navegación debajo. Se representa de forma sencilla (tablero y cuerpo inferior).
- Cada libro se dibuja como un lomo con el título; la portada y los datos se muestran al pasar el ratón o al enfocarlo.
