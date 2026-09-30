# Spec 006: Protección antibots en formularios públicos

- **Estado:** borrador
- **Fecha:** 2026-09-30
- **Depende de:** [001 Usuarios y roles](001-usuarios-y-roles.md) (registro, login y recuperación de contraseña)

## Objetivo

Reducir el abuso automatizado (bots) de los formularios públicos, sin añadir fricción a las personas ni dependencias externas.

## Situación actual

- **Login:** ya limita los intentos fallidos: 5 por combinación de correo e IP (`AuthenticationService`).
- **Recuperar contraseña:** solo envía correo a cuentas existentes, no revela si la cuenta existe y limita a 1 correo por cuenta y minuto (`config/auth.php`, `throttle => 60`). No tiene límite por IP ni honeypot.
- **Registro:** no tiene ninguna protección: un bot puede crear cuentas sin límite.

## Alcance

- Honeypot en los formularios de **registro** y **recuperación de contraseña**.
- Límite de envíos **por IP** en esos dos formularios.

## Fuera de alcance

- CAPTCHA o servicios externos (Cloudflare Turnstile, reCAPTCHA…).
- Cambios en el límite actual del login.
- Bloqueos permanentes de IP o listas negras.

## Requisitos

### RF-01 Honeypot

- Los formularios de registro y de recuperación de contraseña incluyen un **campo trampa** invisible para las personas: oculto visualmente, fuera del orden de tabulación y marcado para que los lectores de pantalla no lo anuncien.
- Si el campo trampa llega relleno, el envío se descarta.
- Además, se descarta un envío que llegue **demasiado rápido** desde que se mostró el formulario, porque una persona tarda al menos unos segundos en rellenarlo.
- Al descartar un envío, la persona (o el bot) **no recibe una pista** de que ha sido detectada: la respuesta es la misma que en un envío normal, o un error genérico.
- La lógica de detección va en un service (constitución, sección 8).

### RF-02 Límite por IP

- El formulario de **registro** admite un número máximo de envíos por IP en un periodo de tiempo.
- El formulario de **recuperación de contraseña** admite un número máximo de envíos por IP en un periodo de tiempo. Este límite es independiente del de 1 correo por cuenta y minuto, que se mantiene.
- Al superar el límite, se muestra un mensaje en español asociado al formulario que indica cuánto esperar, como ya hace el login.
- Los límites se configuran en un único sitio (configuración), no repartidos por el código.

### RF-03 Accesibilidad

- El honeypot no debe afectar a quien use teclado o lector de pantalla (WCAG 2.2 AA, constitución §5).
- El mensaje de límite superado es anunciable y está asociado al formulario.

## Criterios de aceptación

- **CA-01** Dado el formulario de registro, cuando se envía con el campo trampa relleno, entonces no se crea la cuenta.
- **CA-02** Dado el formulario de recuperación, cuando se envía con el campo trampa relleno, entonces no se envía ningún correo y la respuesta es igual que la de un envío normal.
- **CA-03** Dado cualquiera de los dos formularios, cuando se envía antes del tiempo mínimo, entonces se descarta igual que en CA-01 y CA-02.
- **CA-04** Dado un envío normal (campo trampa vacío y tiempo suficiente), cuando se envía, entonces funciona igual que ahora.
- **CA-05** Dada una IP que supera el límite de registros, cuando intenta registrarse otra vez, entonces se rechaza con un mensaje en español que indica cuánto esperar.
- **CA-06** Dada una IP que supera el límite de solicitudes de recuperación, cuando lo intenta otra vez, entonces se rechaza con el mismo tipo de mensaje y no se envía correo.
- **CA-07** Dada otra IP distinta, cuando usa los formularios, entonces no le afecta el límite de la primera.
- **CA-08** Dado el campo trampa, cuando se navega con el teclado, entonces no recibe el foco, y los lectores de pantalla no lo anuncian.

## Preguntas abiertas

- **Límite de registro:** ¿cuántos registros por IP y en qué periodo? Propuesta: 3 por hora.
- **Límite de recuperación:** ¿cuántas solicitudes por IP y en qué periodo? Propuesta: 5 por hora.
- **Tiempo mínimo del honeypot:** ¿cuántos segundos? Propuesta: 3 segundos.
- **Respuesta al detectar un bot en el registro:** ¿mostrar un error genérico ("No se ha podido completar el registro, inténtalo de nuevo") o simular éxito sin crear la cuenta? En recuperación ya se responde igual en todos los casos.
- **Registro de intentos:** ¿se anotan en el log los envíos descartados por el honeypot o por el límite, para poder vigilar ataques?
