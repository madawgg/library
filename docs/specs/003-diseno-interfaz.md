# Spec 003: Diseño de interfaz

- **Estado:** aprobada
- **Fecha:** 2026-09-29
- **Depende de:** [001 Usuarios y roles](001-usuarios-y-roles.md), [002 Libros](002-libros.md), [004 Estantería virtual](004-estanteria-virtual.md)

## Objetivo

Definir la identidad visual y la estructura de la interfaz. El estilo es clásico, elegante y atemporal, inspirado en bibliotecas tradicionales y publicaciones editoriales antiguas. Debe transmitir una sensación cálida, cuidada y ligeramente nostálgica, sin perder una experiencia de uso moderna, clara y accesible.

## Principio rector

La estética nunca perjudica la legibilidad, la accesibilidad (WCAG 2.2 AA, constitución §5) ni la facilidad de uso. Ante un conflicto, gana siempre la usabilidad.

## Alcance

- Identidad visual: tipografía, paleta y temas claro y oscuro.
- Estructura: cabecera y navegación.
- Página de inicio (resumen).
- Selector de vista del listado de libros.
- Presentación de la alerta de préstamo vencido.
- Enlace al historial de préstamos.
- Textos en español y nombre de la aplicación.

## Fuera de alcance

- Detalle de la vista estantería (spec 004).
- Logotipo gráfico: la marca es solo texto.

## Requisitos

### RF-01 Tipografía
- Los títulos usan una tipografía serif de estilo editorial clásico.
- El texto de la interfaz (cuerpo, formularios, tablas, botones) usa una sans-serif muy legible.
- El tamaño mínimo del texto de cuerpo es 16 px.
- Las fuentes se eligen solo del catálogo de Google Fonts.
- Si la licencia de la fuente permite descargarla y redistribuirla gratis, se descarga de Google Fonts, se incluye en el proyecto y se sirve desde el propio servidor.
- Si la licencia no lo permite, la fuente se carga directamente desde Google Fonts.

### RF-02 Paleta "Papel, tinta y cuero"
- **Tema claro:** fondo color papel crema, texto color tinta casi negra, acentos en cuero o burdeos y en verde inglés.
- **Tema oscuro:** fondos de madera oscura, texto marfil y los mismos acentos adaptados.
- Todas las combinaciones de texto y fondo cumplen un contraste mínimo de 4.5:1, o 3:1 en texto grande y componentes de interfaz, en los dos temas.
- El color nunca es el único medio para transmitir información.

### RF-03 Temas
- Hay dos temas: claro y oscuro.
- El tema por defecto es el claro, aunque el sistema operativo prefiera el oscuro.
- El usuario elige el tema en Ajustes → Apariencia. La elección se guarda en su cuenta y se aplica en cualquier dispositivo en el que inicie sesión.
- La opción "Sistema" del kit se elimina.

### RF-04 Cabecera y navegación
- La navegación principal es una cabecera superior. En pantallas estrechas se convierte en un menú desplegable, accesible con teclado.
- La marca es el texto "Biblioteca Personal", con la tipografía de títulos y sin icono. Enlaza a la página de inicio.
- El título de cada pestaña del navegador es "<página> · Biblioteca Personal".
- Enlaces de la cabecera:
  - Todos: Inicio, Mis libros, Salas y estanterías.
  - Administradores y super administrador, además: Usuarios y Todos los libros.
  - Menú de cuenta: Perfil, Contraseña, Apariencia y Cerrar sesión.
- La cabecera no incluye enlaces al historial de préstamos.

### RF-05 Página de inicio (resumen)
- Tras iniciar sesión se muestra un resumen de la biblioteca del usuario con:
  - Totales: libros, leídos, prestados y préstamos vencidos.
  - Los últimos libros añadidos.
  - Accesos rápidos: añadir libro, ver mis libros y ver la estantería.
- Si hay préstamos vencidos, se muestra un aviso con la lista de esos libros, a quién están prestados y cuántos días llevan prestados.

### RF-06 Selector de vista del listado de libros
- El listado de libros ofrece tres vistas: Cuadrícula (portadas), Tabla y Estantería (esta última solo en pantallas de 1280 px o más, spec 004).
- La última vista elegida se guarda en la cuenta del usuario. Si esa vista no está disponible en el dispositivo (por ejemplo, Estantería en móvil), se usa la Tabla.
- La vista por defecto es Cuadrícula.
- Los libros sin portada muestran un marcador con el título y el autor.

### RF-07 Alerta de préstamo vencido
Los préstamos marcados con tiempo superado (spec 002) se muestran de tres formas:
- **Distintivo** con el texto "Préstamo vencido" en el libro, en todas las vistas (tarjeta, fila de tabla y lomo). No se indica solo con color.
- **Aviso en la página de inicio** (RF-05).
- **Filtro "Préstamo vencido"** en el listado de libros, que se añade a los filtros de la spec 002.

### RF-08 Historial de préstamos
- La ficha de un libro tiene un enlace discreto "Ver historial de préstamos".
- Ese enlace no aparece en la navegación ni en los listados.

### RF-09 Textos e idioma
- Todos los textos visibles están en español, incluidos los del starter kit, los mensajes de validación, los correos (recuperar contraseña, verificar email) y las páginas de error.
- El atributo `lang` del documento es `es`.

### RF-11 Correos
- Los correos de la aplicación (recuperar contraseña, verificar email) usan el estilo de la app de forma contenida: la marca "Biblioteca Personal" en texto, y los colores y la tipografía de la paleta, sin elementos decorativos que sobrecarguen.
- La prioridad es la lectura: texto con buen contraste y tamaño legible, y enlaces y botones de acción claramente visibles.
- Si se usa un botón de acción, el correo incluye también el enlace en texto plano.

### RF-10 Accesibilidad transversal
- Foco visible en todos los elementos interactivos, con buen contraste en los dos temas.
- El elemento con foco no queda oculto por la cabecera ni por otros elementos superpuestos.
- Objetivos clicables de al menos 24×24 px.
- Enlace "Saltar al contenido" al principio de cada página.
- Jerarquía de encabezados correcta en cada página.

## Criterios de aceptación

### Temas (RF-02 y RF-03)
- **CA-01** Dado un usuario nuevo con el sistema en modo oscuro, cuando entra por primera vez, entonces ve el tema claro.
- **CA-02** Dado un usuario, cuando elige el tema oscuro, entonces se guarda en su cuenta, y al iniciar sesión en otro dispositivo ve el tema oscuro.
- **CA-03** Dado cualquiera de los dos temas, cuando se miden los contrastes de texto, controles y foco, entonces cumplen WCAG 2.2 AA.
- **CA-04** Dados los ajustes de apariencia, cuando se abren, entonces solo se ofrecen Claro y Oscuro.

### Navegación (RF-04)
- **CA-05** Dado un usuario, cuando ve la cabecera, entonces ve Inicio, Mis libros y Salas y estanterías, pero no Usuarios ni Todos los libros.
- **CA-06** Dado un administrador, cuando ve la cabecera, entonces además ve Usuarios y Todos los libros.
- **CA-07** Dada una pantalla estrecha, cuando se abre el menú con el teclado, entonces se puede recorrer y cerrar con Escape.
- **CA-08** Dada cualquier página, cuando se carga, entonces su título tiene el formato "<página> · Biblioteca Personal".

### Inicio (RF-05)
- **CA-09** Dado un usuario con 10 libros, de los cuales 4 leídos y 2 prestados (1 vencido), cuando abre el inicio, entonces ve esos totales y el aviso con el préstamo vencido.
- **CA-10** Dado un usuario sin préstamos vencidos, cuando abre el inicio, entonces no ve el aviso.
- **CA-11** Dado un usuario, cuando abre el inicio, entonces ve solo datos de su propia biblioteca.

### Selector de vista (RF-06)
- **CA-12** Dado un usuario que elige la vista Tabla, cuando vuelve al listado desde otro dispositivo, entonces ve la Tabla.
- **CA-13** Dado un usuario cuya última vista es Estantería, cuando abre el listado en un móvil, entonces ve la Tabla.

### Alerta y historial (RF-07 y RF-08)
- **CA-14** Dado un libro con préstamo vencido, cuando aparece en cualquier vista, entonces muestra el distintivo "Préstamo vencido" con texto.
- **CA-15** Dado el filtro "Préstamo vencido", cuando se aplica, entonces solo se muestran los libros con un préstamo activo marcado.
- **CA-16** Dada la ficha de un libro, cuando se abre, entonces contiene el enlace "Ver historial de préstamos", y ese enlace no aparece en la cabecera.

### Textos y accesibilidad (RF-09 y RF-10)
- **CA-17** Dado un formulario con errores, cuando se valida, entonces los mensajes aparecen en español.
- **CA-18** Dada cualquier página, cuando se navega con el teclado desde el principio, entonces el primer elemento es "Saltar al contenido" y el foco es siempre visible.
- **CA-19** Dada cualquier página, cuando se carga, entonces el documento tiene `lang="es"`.
- **CA-20** Dada cualquier página, cuando se carga, entonces las fuentes con licencia de descarga gratuita se sirven desde el propio servidor, y solo se piden a Google Fonts las que no la tienen.

### Correos (RF-11)
- **CA-21** Dada una solicitud de recuperación de contraseña, cuando se envía el correo, entonces está en español, lleva la marca "Biblioteca Personal" y los colores de la app, y contiene el botón de acción y el enlace en texto plano.

## Decisiones tomadas

- No hay portada pública: la ruta `/` lleva al inicio si hay sesión y al login si no.
- Fuentes: Cormorant Garamond (títulos) y Source Sans 3 (interfaz).
- Las preferencias del usuario (tema y, más adelante, la vista del listado) se guardan en columnas de la tabla `users`.
- Traducciones: archivos propios en `lang/es`, sin paquetes externos.
- Las fuentes se eligen de Google Fonts. Si su licencia permite descargarlas gratis, se descargan y se sirven desde el propio servidor; si no, se cargan desde Google Fonts.
- Los correos tienen el estilo de la app, sin sobrecargarlos y dando prioridad a la lectura y a los enlaces.
