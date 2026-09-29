# Constitución del proyecto: Biblioteca Personal

Principios no negociables. Toda spec, plan e implementación debe cumplirlos.
Si una spec los contradice, se detiene el trabajo y se consulta.

## 1. Propósito

CMS para gestionar bibliotecas físicas personales.

- Los requisitos funcionales del producto se definen exclusivamente en las specs.
- La documentación del proyecto (`README.md`, `log.md`, etc.) queda fuera de este principio.

## 2. Stack tecnológico

- Laravel 12 con el starter kit oficial de Livewire (Blade + Livewire + Tailwind CSS).
- Tests con PHPUnit.
- Base de datos: MySQL.
- No se añaden dependencias nuevas sin consultarlo antes.

## 3. Usuarios y autorización

- Existen tres roles: usuario, administrador y super administrador.
- Un usuario solo puede ver y modificar sus propios recursos.
- Un administrador puede acceder a los recursos de cualquier usuario.
- Existe un único super administrador, por encima de administradores y usuarios. Nadie más puede modificarlo ni eliminarlo.
- Toda ruta que gestione recursos requiere autenticación.
- La autorización se aplica en el servidor (Policies/Gates de Laravel), nunca solo ocultando elementos en la vista.
- Cada regla de acceso tiene tests que verifican tanto el acceso permitido como el denegado.

## 4. Testing (TDD estricto con PHPUnit)

- Ciclo obligatorio: test en rojo → implementación mínima → verde → refactor.
- No se escribe código de producción sin un test que falle antes.
- Cada criterio de aceptación de una spec tiene al menos un test.
- Los tests deben verificar comportamiento observable, no detalles internos de implementación.
- Tests de feature para comportamiento HTTP; tests unitarios para lógica aislada.
- Una tarea no está terminada hasta que `php artisan test` pasa completo.

## 5. Accesibilidad (WCAG 2.2 AA)

- HTML semántico y jerarquía de encabezados correcta.
- Todo campo de formulario tiene `<label>` asociado; errores de validación asociados al campo y anunciables.
- Contraste mínimo 4.5:1 en texto normal y 3:1 en texto grande y componentes.
- Toda la aplicación es usable solo con teclado, con foco visible.
- Imágenes con texto alternativo (vacío si son decorativas).
- El elemento con foco no queda oculto por contenido superpuesto (cabeceras fijas, modales).
- Objetivos táctiles/clicables de al menos 24×24 px.

## 6. Idioma y convenciones de código

- Código, comentarios, nombres de clases, métodos, variables, tablas y columnas en inglés, siguiendo convenciones de Laravel y PHP.
- Nombres descriptivos, sin abreviaturas innecesarias.
- Textos visibles para el usuario en español.
- Formato con `./vendor/bin/pint` antes de dar una tarea por terminada.

## 7. Diseño visual

- Estilo rústico, claro y fácil de usar.
- La estética nunca prevalece sobre la accesibilidad (sección 5).

## 8. Arquitectura por capas

- **Services** (`app/Services`): contienen toda la lógica de negocio (reglas de roles, altas, bajas, cambios de estado, cálculos…).
- **Modelos**: solo relaciones y configuración de Eloquent (`$fillable`, `$hidden`, casts y valores por defecto). No contienen métodos con lógica ni eventos de modelo.
- **Controladores y componentes Volt**: controladores finos. Solo validan la entrada, autorizan, llaman a un service y devuelven la vista.
- **Policies**: la autorización se declara en Policies de Laravel, que delegan las reglas en los services.
- Las pantallas que trae el starter kit siguen esta misma arquitectura.

## 9. Datos y persistencia

- Todo cambio de esquema se hace mediante migraciones; nunca se modifica una migración ya ejecutada en otro entorno.
- La validación de entrada se hace en Form Requests en los controladores, y con `validate()` en los componentes Volt.
- Cambios relevantes de persistencia se consultan antes de implementarse.

## 10. Flujo de trabajo

- Antes de modificar código: leer esta constitución y la spec activa.
- Decisiones relevantes de arquitectura, diseño, accesibilidad, testing o persistencia se consultan antes.
- No se modifican los requisitos de una spec para adaptarlos a la implementación.
- Al terminar: tests en verde, entrada en `log.md`, `README.md` actualizado si procede, informe de cambios y verificaciones.
- No se crea ningún commit sin autorización del usuario.
