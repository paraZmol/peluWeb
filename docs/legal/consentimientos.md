# Consentimientos, casillas y avisos

> **Borrador — no es asesoría legal.** Debe revisarlo un abogado colegiado en Perú antes de
> publicarse. Versión 0.1 — 2026-10-10.

## Reglas para todas

1. Ninguna casilla viene marcada.
2. Aceptar los términos y aceptar publicidad son casillas **separadas**; la de publicidad nunca
   es obligatoria.
3. El texto enlaza al documento exacto que se acepta y se abre sin perder el formulario.
4. Un **aviso** informa y no lleva casilla. No se pide "aceptar" lo que es solo información.
5. El servidor rechaza el envío si falta una casilla obligatoria: ocultar el botón no basta.

## Qué se guarda como prueba

Un registro por cada aceptación, que nunca se edita ni se borra mientras dure el plazo de
conservación:

- quién (id de la cuenta; para un salón, además el id del salón);
- qué documento y **qué versión** (y la huella del texto mostrado);
- cuándo (fecha y hora en UTC);
- desde dónde (IP y navegador);
- cómo (registro con correo, Google, invitación, alta de salón, nueva versión).

Para los consentimientos opcionales (publicidad) se guarda además cuándo se **retiró** y por qué
vía. Retirar debe ser tan fácil como aceptar.

## Casillas y avisos

| # | Dónde | Tipo | Texto exacto | ¿Obligatoria? | Existe hoy |
|---|---|---|---|---|---|
| 1 | Registro con correo | Casilla, desmarcada | "Leí y acepto los **Términos y condiciones** y la **Política de privacidad**." | Sí | No |
| 2 | Registro e inicio con Google | Texto bajo el botón + pantalla de aceptación en el primer ingreso | Bajo el botón: "Al continuar verás los términos antes de crear tu cuenta." En la pantalla: la casilla 1 | Sí | No |
| 3 | Primer ingreso de una cuenta sin aceptación registrada, o cuando cambia la versión | Pantalla que no se puede saltar (sí permite cerrar sesión y borrar la cuenta) | "Actualizamos nuestros términos. Este es el resumen de lo que cambió: […]" + casilla 1 | Sí | No |
| 4 | Aceptar una invitación al equipo | Casilla 1 (si la cuenta aún no aceptó) + aviso | Aviso: "Al unirte, **{Salón}** verá tu nombre, tu agenda, tus ausencias y tus comisiones. {Salón} es responsable de esos datos." | Casilla sí; aviso sin casilla | No |
| 5 | Alta del salón | Casilla, desmarcada | "Acepto los **Términos para salones** y el **Encargo de tratamiento de datos**, y declaro que puedo actuar en nombre de este negocio." | Sí | No |
| 6 | Paso "Confirmar" de la reserva | Aviso sobre el botón | "Reservas con **{Salón}**, que recibirá tu nombre, tu teléfono y los datos de esta cita para atenderte. [Privacidad del salón] · [Política de seña y cancelación]" | Sin casilla | No |
| 7 | Mismo paso, cuando hay seña | Aviso destacado, junto al total | "Seña: **S/ {monto}**. La envías directamente a {Salón}. {Se devuelve si cancelas hasta {N} horas antes. \| No se devuelve si cancelas.}" | Sin casilla; el botón dice "Confirmar y enviar seña" | Parcial: revisar |
| 8 | Campo "Nota" de la reserva | Texto de ayuda | "Cuéntale al salón lo que necesite saber para tu cita. No incluyas datos de salud que no hagan falta." | — | No |
| 9 | Subir foto de referencia | Texto de ayuda | "La verá el equipo de {Salón}. Sube solo fotos tuyas o que puedas compartir. Puedes quitarla cuando quieras." | — | No |
| 10 | Perfil → teléfono | Texto de ayuda | "Los salones donde reserves lo verán para escribirte por tu cita." | — | No |
| 11 | Mi salón → galería y fotos del equipo | Texto de ayuda, y casilla la primera vez | "Sube solo fotos tuyas o con permiso. Si se ve a una persona, necesitas su autorización (y la de sus padres si es menor)." | Casilla la primera vez | No |
| 12 | Agenda → crear cliente sin cuenta | Texto de ayuda para el personal | "Anota solo nombre y teléfono. Avísale que el salón guarda estos datos para su cita." | — | No |
| 13 | Publicidad del salón **[PENDIENTE: no hay mensajes comerciales]** | Casilla, desmarcada, por salón | "Quiero recibir promociones de **{Salón}** por WhatsApp o correo. Puedo darme de baja cuando quiera." | **No** | No |
| 14 | Novedades de la plataforma para dueños **[PENDIENTE]** | Casilla, desmarcada | "Quiero recibir novedades y consejos de {Plataforma} por correo." | **No** | No |
| 15 | Avisos push **[PENDIENTE: paso 6.5]** | Permiso del navegador, pedido solo tras pulsar "Activar avisos" | "Te avisaremos de tus citas. No enviamos publicidad por aquí." | No | No |
| 16 | Reseñas **[PENDIENTE: paso 6.2]** | Aviso al escribir | "Tu reseña se publicará con tu nombre de pila en la página de {Salón}. No incluyas datos personales de otras personas." (solo si el salón tiene reseñas públicas) | — | No |
| 17 | Ficha del cliente: **alergias y reacciones** (dato de salud). La fórmula y la técnica van en otro campo y no la necesitan (v0.2, paso 6.1) | **Por escrito y firmada por la persona** (art. 8 del reglamento, sin verificar en el texto oficial). Primera entrega: hoja impresa que el salón conserva; el personal declara que existe. Pendiente: que la persona la acepte y la retire desde su cuenta | "Autorizo a {Salón} a guardar mis alergias, sensibilidades y reacciones a productos para atenderme con seguridad. Solo lo verá el equipo que me atiende. No se usará para publicidad ni se compartirá. Puedo ver lo anotado y retirar esta autorización cuando quiera, pidiéndolo en el salón; al retirarla, se borra." Casilla del personal, desmarcada: "Declaro que {Nombre} firmó la autorización impresa y que {Salón} la conserva." | Sí, para guardar ese campo | No |
| 20 | Fotos antes/después de uso interno **[PENDIENTE: 6.1 (b)]** | Autorización propia, distinta de la 17; casilla del personal por cada subida | "Autorizo a {Salón} a tomar y guardar fotos del antes y el después de mi servicio, solo para su registro interno y para mis próximas citas. Solo las verá el equipo de {Salón}. No se publicarán. Puedo pedir que las borren cuando quiera." | Sí, por subida | No |
| 21 | Publicar una foto en la galería o en redes **[reservada; no se construye]** | Autorización escrita de la persona, por foto, diciendo dónde se publica | Por redactar | Sí | No |
| 18 | Cookies | Aviso en el pie | Ver `politica-cookies.md` | Sin casilla | No |
| 19 | Libro de Reclamaciones | Casilla en el formulario | "Declaro que los datos son verdaderos y acepto que se usen para atender este reclamo." | Sí | No |

## Qué no hacer

- Una sola casilla para "términos + privacidad + publicidad".
- "Al navegar aceptas…" como forma de consentimiento para algo que no es necesario.
- Pedir consentimiento para lo que es necesario para la reserva: se informa, no se pide.
- Enviar una promoción por WhatsApp o correo a quien solo reservó. Desde la Ley 32323
  (09-05-2025) el art. 58.1.e del Código exige consentimiento libre, previo, informado, expreso e
  inequívoco, revocable en cualquier momento, y su incumplimiento es infracción muy grave.
  Los mensajes sobre **la cita** (confirmar, recordar, avisar un cambio) no son publicidad.
- Mensajes de publicidad sin decir quién los envía y sin forma de baja. En correo, el asunto
  empieza con "PUBLICIDAD" (Ley 28493 — **sin verificar** el texto vigente).

## Pendiente de abogado

- Si la casilla 1 puede unir términos y privacidad, o si la privacidad debe ir solo como aviso
  ("Leí la Política de privacidad") porque el tratamiento es necesario para el servicio.
- Texto y forma de la autorización 17 (datos sensibles): ¿hace falta por escrito o firmada?
- Edad: si hay que añadir "Soy mayor de 18 años, o tengo entre 14 y 17 y cuento con autorización".
