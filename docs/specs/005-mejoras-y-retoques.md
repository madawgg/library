# Spec 005: Mejoras y retoques

- **Estado:** cerrada (2026-09-30). Incluye las mejoras M-01 a M-08; las mejoras nuevas irán en otra spec.
- **Fecha:** 2026-09-29
- **Orden de implementación:** al final de todo, después del resto de specs.
- **Depende de:** [003 Diseño de interfaz](003-diseno-interfaz.md) y las pantallas de las demás specs.

## Objetivo

Reunir las mejoras y los retoques de interfaz y de uso que se vayan observando durante el desarrollo, para aplicarlos juntos al final.

## Mejoras

### M-01 Formularios a todo el ancho

- Los formularios se recolocan para aprovechar todo el ancho del contenedor.
- Si es necesario, sus campos se redimensionan.

### M-02 Redimensionar la ficha de cada libro

- Se redimensiona la ficha (vista de detalle) de cada libro.
- Queda abierta: las especificaciones concretas se añadirán más adelante.

### M-03 Mayús + clic en un libro de la estantería abre su ficha

- En la vista estantería de "Mis libros", al hacer Mayús + clic sobre un libro se va a la ficha (show) de ese libro.

### M-04 Libros más finos en la estantería

- Los libros (lomos) de la vista estantería se dibujan un poco más finos, para que quepan más en el mismo hueco.

### M-05 Filtros compactos en Tabla y Cuadrícula, con "Más filtros"

- En las vistas Tabla y Cuadrícula solo se ven a primera vista: "Buscar por título o autor", "Ordenar por", "Orden" y el botón "Quitar filtros".
- Estos controles van en la misma línea siempre que el ancho lo permita.
- El resto de filtros va en un menú oculto al que se accede con un botón "Más filtros".

### M-06 Vista de sala con tarjetas de estanterías

- En "Salas y estanterías", al pulsar en el nombre de una sala (por ejemplo, "Salón") se abre otra vista con una tarjeta por cada estantería de esa sala.
- Al pulsar una tarjeta se va a "Mis libros" en modo estantería, mostrando esa estantería.

### M-07 El nombre de la estantería lleva a su vista estantería

- En "Salas y estanterías", al pulsar en el nombre de una estantería se va directamente a "Mis libros" en modo estantería, mostrando esa estantería.

### M-08 Orden de los filtros de ubicación: sala antes que estantería

- En los filtros de "Mis libros", dentro de la ubicación, primero va la sala y después la estantería, porque es el orden natural de filtrado (sala → estantería → balda → hueco).
- Sustituye a lo dicho en la spec 002 (RF-06): el filtro de estantería deja de mostrarse primero.

## Criterios de aceptación

- **CA-01 (M-01)** Dado el formulario de edición de un libro, cuando se abre en una pantalla ancha, entonces ocupa todo el ancho del contenedor y sus campos se reparten en varias columnas. El formulario de alta no cambia.
- **CA-02 (M-02)** Dada la ficha de un libro, cuando se abre, entonces ocupa todo el ancho del contenedor con la misma distribución.
- **CA-03 (M-03)** Dado un libro en la vista estantería, cuando se hace Mayús + clic sobre él, entonces se abre su ficha. Un clic normal sigue abriendo su menú.
- **CA-04 (M-04)** Dada la vista estantería, cuando se dibujan los libros, entonces cada lomo mide 24 px de ancho.
- **CA-05 (M-05)** Dadas las vistas Tabla y Cuadrícula, cuando se abre el listado, entonces solo se ven la búsqueda, "Ordenar por", "Orden", "Quitar filtros" y el botón "Más filtros", en una línea si cabe. El resto de filtros (incluido el de propietario en el listado global) está en un panel desplegable.
- **CA-06 (M-05)** Dados 2 filtros activos del panel, cuando el panel está cerrado, entonces el botón muestra "Más filtros (2)".
- **CA-07 (M-06)** Dada una sala, cuando se pulsa su nombre en "Salas y estanterías", entonces se abre su vista con una tarjeta por estantería. Cada tarjeta muestra un pequeño dibujo de baldas y huecos, el nombre y el número de libros.
- **CA-08 (M-06, M-07)** Dada una tarjeta de estantería o el nombre de una estantería, cuando se pulsa, entonces se va a "Mis libros" en modo estantería mostrando esa estantería. En pantallas de menos de 1280 px se ve la tabla filtrada por esa estantería.
- **CA-09 (M-06, M-07)** Dado un admin que gestiona las salas de otro usuario, cuando pulsa una estantería, entonces va a la vista estantería de la biblioteca de ese usuario.
- **CA-10 (M-08)** Dados los filtros de ubicación del listado, cuando se abre "Más filtros", entonces aparecen en este orden: Sala, Estantería, Balda y Hueco.

## Decisiones tomadas

- M-01: solo el formulario de **edición** de libro, para aprovechar el espacio y que los campos no se amontonen en un lado.
- M-02: la ficha solo se hace más ancha (todo el ancho), sin cambiar su distribución.
- M-03: el clic normal sigue abriendo el menú del libro. Con teclado, la ficha se abre desde ese menú.
- M-04: los lomos pasan a 24 px de ancho.
- M-05: "Más filtros" es un panel desplegable bajo la línea, con contador de filtros activos. En el listado global, el filtro de propietario también va dentro.
- M-06: las tarjetas muestran un pequeño dibujo de la estantería (baldas y huecos) con su nombre y el número de libros. La vista de sala tiene su propia dirección.
- M-06 y M-07: en pantallas estrechas llevan a la tabla filtrada por esa estantería. Si es un admin gestionando las salas de otro usuario, llevan a la biblioteca de ese usuario.

## Preguntas abiertas

Ninguna. Las nuevas mejoras irán en otra spec.
