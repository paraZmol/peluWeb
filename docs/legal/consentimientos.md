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
| 16 | Reseñas (paso 6.2), formulario en la reserva, salón en modo Públicas | Aviso sobre el botón «Publicar reseña», sin casilla | "Tu reseña se publicará con tu nombre de pila en la página de {Salón}, y el salón puede responderla ahí. No incluyas datos personales de otras personas. Puedes retirarla cuando quieras desde esta reserva." Se firma solo con el primer nombre ("Cliente" si el nombre de la cuenta no sirve). | — | Sí (2026-10-11) |
| 16a | Reseñas, salón en modo Privadas | Aviso sobre el botón «Enviar al salón», sin casilla | "La leerá el equipo de {Salón}, con tu nombre de pila. No se publica. Si el salón la reporta, la revisa peluWeb." Lo escrito en este modo nunca se publica, aunque el salón pase después a Públicas. | — | Sí (2026-10-11) |
| 16b | Reseñas: retiro y borrado de cuenta | Diálogo de confirmación | "Deja de mostrarse y se borra tu comentario. No podrás volver a opinar de esta cita." Retirar es definitivo: se borran el comentario y el nombre; la fila queda (estrellas, cita) sin mostrarse. Al borrar la cuenta, las reseñas quedan firmadas como "Cliente" y el diálogo de «Eliminar cuenta» lo avisa. | — | Sí (2026-10-11) |
| 17 | Ficha del cliente: **alergias y reacciones** (dato de salud), texto v0.5 (`ConsentKind::Care`) | Dos vías: (a) hoja impresa firmada que el salón conserva, y el personal lo declara; (b) la persona, desde su cuenta (Mis datos), con casilla desmarcada y constancia por correo. Retira la persona desde su cuenta o el salón a su pedido. Si retiró la persona, solo ella puede volver a autorizar | "Autorizo a {Salón} a guardar mis alergias, sensibilidades y reacciones a productos (datos de salud) para atenderme con seguridad. Es voluntario: si no autorizo, me atienden igual y no se anota. Solo lo verán quien dirige el salón y quien me atiende. Se guarda en peluWeb, la plataforma de reservas que usa el salón. No se usa para publicidad ni se entrega a nadie más. Se conserva mientras no retire esta autorización. Puedo ver lo anotado y retirar esta autorización cuando quiera desde mi cuenta de peluWeb, y pedir en el salón que lo corrijan o que la retiren por mí; al retirarla, se borra." La hoja lleva además: línea para el documento, línea para madre, padre o tutor, cómo ejercer derechos y reclamar ante la ANPD, versión y número de ficha. Casilla del personal, desmarcada: "Declaro que {Nombre} firmó la autorización impresa y que el salón la conserva." | Sí, para guardar ese campo | Sí (2026-10-11). Falta: país del hosting y plazo máximo en el texto (cuando exista el borrado automático); razón social y domicilio del salón en la hoja; correo a la persona cuando el salón retira por ella |
| 17a | Mis datos → "Autorizar" (alergias y fotos) | Casilla, desmarcada, validada en el servidor; exige correo verificado | Alergias: "Declaro que soy mayor de 18 años. Leí el texto de arriba y autorizo a {Salón} a guardar estos datos de salud." Fotos: "Declaro que soy mayor de 18 años. Leí el texto de arriba y autorizo a {Salón} a tomar y guardar estas fotos." Debajo, sin casilla: "¿Tienes menos de 18? La autorización la firma tu madre, padre o tutor, en el salón." Prueba: cuenta, IP, navegador, versión y huella SHA-256 del texto **más la casilla** (`ConsentKind::accountText`), fecha; constancia por correo con el texto. | Sí | Sí (2026-10-11) |
| 20 | Fotos antes/después de uso interno, texto v0.3 (`ConsentKind::Photos`) | Dos vías: (a) casilla del personal en cada subida, desmarcada; (b) la persona desde su cuenta, para volver a autorizar tras haber retirado. Quien retira desde su cuenta bloquea nuevas subidas hasta que vuelva a autorizar | "Autorizo a {Salón} a tomar y guardar fotos del antes y el después de mi servicio, solo para su registro interno y para mis próximas citas. Es voluntario: si no autorizo, me atienden igual. Solo las verán quien dirige el salón y quien me atiende. No se publicarán ni se entregarán a nadie más. Se guardan en peluWeb, la plataforma de reservas que usa el salón. Puedo verlas y retirar esta autorización cuando quiera desde mi cuenta de peluWeb o pidiéndolo en el salón; al retirarla, se borran todas." Casilla del personal: "{Nombre} autorizó estas fotos para uso interno. Le avisé que no se publican y que puede verlas y borrarlas desde su cuenta." Ayudas: "Encuadra el trabajo, no la cara." "No subas fotos de menores de edad." | Sí, por subida | Sí (2026-10-11). La vía (a) es declaración del personal, no firma de la persona; Mis datos lo dice ("{Salón} registró que las autorizaste en persona.") |
| 20a | Mis datos → retirar | Diálogo de confirmación, sin casilla | Alergias: "¿Retirar la autorización?" / "{Salón} dejará de guardar tus alergias y se borrará lo anotado. Avísales en persona antes de tu próximo servicio." Fotos: "¿Retirar la autorización para fotos?" / "Se borrará(n) de peluWeb la(s) foto(s) que {Salón} guarda de tus servicios. No se puede(n) recuperar. Si el salón tiene copias en otro lugar, pídele que las borre." o "{Salón} ya no podrá guardar fotos de tus servicios." Borrar la cuenta lo retira todo en todos los salones. | — | Sí (2026-10-11). El registro del retiro se conserva (sin el contenido) |
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
- Autorización 17 desde la cuenta: si la casilla marcada (sesión con contraseña, correo verificado, IP, huella del texto y constancia por correo) vale como consentimiento "por escrito" para datos de salud, o si hace falta firma manuscrita o digital.
- Edad: si la declaración "soy mayor de 18 años" basta, cuál es el umbral vigente y cómo debe constar la autorización de madre, padre o tutor.
- Reseñas: si el aviso sin casilla basta para publicar el nombre de pila; si conservar la reseña anónima tras borrar la cuenta (y la fila tras el retiro) respeta el derecho de cancelación; si el pedido de reseña por correo es comunicación comercial (art. 58.1.e del Código); si llamar la atención sobre "citas atendidas" y permitir el cambio de modo puede ser publicidad engañosa.
- Cuánto tiempo y con qué base se conserva la prueba del consentimiento (IP incluida).
