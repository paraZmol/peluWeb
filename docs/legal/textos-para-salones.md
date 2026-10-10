# Textos por defecto que cada salón muestra a sus clientes

> **Borrador — no es asesoría legal.** Debe revisarlo un abogado colegiado en Perú antes de
> publicarse. Versión 0.1 — 2026-10-10.

Estos textos son **del salón**: el salón responde por ellos. La plataforma los ofrece ya escritos
y los rellena con los datos de "Mi salón" (lo que va entre llaves). Lo que el salón configura
(horas, porcentaje, si devuelve) sale de sus ajustes: el texto nunca debe decir algo distinto de
lo que el sistema aplica. Si el salón quiere otra regla, cambia el ajuste, no el texto.

Datos nuevos que el salón debe poder cargar: nombre o razón social, RUC, correo de contacto y
enlace a su Libro de Reclamaciones (requisito P7).

---

## 1. Privacidad del salón

Se muestra enlazado en el paso "Confirmar" de la reserva y en el pie de la página del salón.

> **Cómo usa {Salón} tus datos**
>
> **{Nombre o razón social del salón}** {— RUC {RUC}}, {dirección}, es responsable de los datos
> que nos das al reservar.
>
> **Qué guardamos:** tu nombre y teléfono, tus citas (servicio, profesional, fecha, precio y
> seña), la nota y la foto de referencia si las envías, y si faltaste a una cita.
>
> **Para qué:** agendar y atender tu cita, escribirte sobre ella (confirmarla, recordártela o
> avisarte un cambio) y llevar nuestras cuentas.
>
> **Quién los ve:** el equipo de {Salón} que los necesita. Los guardamos en {Plataforma}, que nos
> presta el sistema de reservas y no los usa para otra cosa.
>
> **No te enviamos publicidad** salvo que lo hayas aceptado, y puedes darte de baja cuando quieras.
>
> **Tus derechos:** puedes pedirnos ver, corregir o borrar tus datos escribiendo a {WhatsApp o
> correo del salón}. Si no quedas conforme, puedes acudir a la Autoridad Nacional de Protección
> de Datos Personales.

## 2. Aviso para el local (clientes sin cuenta)

Para imprimir junto a la caja; cubre a quien reserva por teléfono o en persona.

> En {Salón} anotamos tu nombre y tu teléfono para agendar tu cita y avisarte de ella. Puedes
> pedirnos que los corrijamos o los borremos cuando quieras. Más información: {enlace o QR a la
> privacidad del salón}.

## 3. Política de seña

El sistema elige la variante según los ajustes del salón.

**Sin seña:**
> No pedimos seña para reservar.

**Con seña que se devuelve:**
> Para confirmar tu cita pedimos una seña del **{N} %** del precio (**S/ {monto}** en esta
> reserva), que se descuenta de lo que pagas el día de la cita.
> Envíala a **{datos de pago del salón}** dentro de los **{minutos} minutos** siguientes a
> reservar y avísanos por WhatsApp; si no llega a tiempo, el horario se libera.
> **Te la devolvemos completa** si cancelas hasta **{H} horas antes** de la cita, por el mismo
> medio en que la enviaste y en un máximo de **{D} días**. Si cancelas después o no asistes, la
> seña no se devuelve.
> Si somos nosotros quienes cancelamos o cambiamos tu cita y el nuevo horario no te sirve, te la
> devolvemos completa siempre.

**Con seña que no se devuelve:**
> Para confirmar tu cita pedimos una seña del **{N} %** del precio (**S/ {monto}** en esta
> reserva), que se descuenta de lo que pagas el día de la cita.
> **La seña no se devuelve si cancelas o no asistes**, porque reservamos ese horario solo para ti.
> Si mueves tu cita hasta **{H} horas antes**, la seña se mantiene para la nueva fecha.
> Si somos nosotros quienes cancelamos o cambiamos tu cita y el nuevo horario no te sirve, te la
> devolvemos completa siempre.

Notas para quien construye:
- {D}, el plazo de devolución, es un ajuste nuevo (propuesta por defecto: 7 días).
- La frase "si mueves tu cita… la seña se mantiene" solo se muestra si el sistema lo hace así.
- La variante "no se devuelve" es la que más reclamos puede traer: `[CONFIRMAR CON ABOGADO]` que
  una seña no reembolsable informada antes de pagar es válida, y con qué límites.

## 4. Política de cancelación y cambios

> Puedes mover o cancelar tu cita desde "Mis reservas" hasta **{H} horas antes**. Después de ese
> plazo, escríbenos por WhatsApp al {teléfono}.
> Si llegas más de **{M} minutos** tarde, quizá no podamos atenderte completo o tengamos que
> reprogramar.
> Si no puedes venir, avísanos: así otra persona puede usar ese horario.

Nota: {M} es un ajuste nuevo y opcional; si el salón no lo define, esa frase no se muestra.

## 5. Condiciones de promoción **[PENDIENTE: paso 6.7]**

Se arma sola con los datos de la promoción; el dueño no la edita. Va **en cada pieza** donde se
anuncie la promoción (tarjeta en la página, enlace compartido, texto para Instagram o WhatsApp),
completa o con la frase corta y el enlace.

**Completa:**
> Válido para citas del **{inicio}** al **{fin}**. Aplica a: **{servicios}**.
> {Solo {días}, de {hora} a {hora}.}
> {**Cupos: {n}.** | Sin límite de cupos, sujeto a horarios disponibles.}
> {Una por persona.}
> Con reserva previa por {Plataforma}. No acumulable con otras promociones. Precios en soles,
> con impuestos incluidos.
> {El servicio de regalo se realiza en la misma cita y no se cambia por dinero ni por otro servicio.}
> Promoción de {Nombre o razón social del salón}.

**Corta (para una imagen o un mensaje):**
> Del {inicio} al {fin}. {Cupos: {n}.} Condiciones en {enlace}.

Reglas que el sistema debe hacer cumplir (art. 14 del Código: la publicidad de una promoción
indica su **duración** y la **cantidad mínima de unidades disponibles**; las restricciones van en
cada anuncio o en una fuente gratuita y fácil a la que el anuncio remite):

1. Toda promoción tiene fecha de fin (ya previsto: máximo 90 días).
2. Los cupos se dicen siempre: el número o, si no hay, la frase "sin límite de cupos".
   `[CONFIRMAR CON ABOGADO que esa frase basta]`. Si no se indican, el salón queda obligado a
   atender a todo el que la pida.
3. El precio "antes" es el precio de catálogo que el salón cobraba de verdad; lo pone el sistema.
   Mantener el aviso previsto si el precio subió en los 30 días anteriores.
4. "Gratis" significa que no se cobra nada por ese servicio, sin condiciones escondidas: la
   condición ("con tu corte") va en la misma frase.
5. Una promoción publicada no se recorta: solo se pausa, se alarga o se le suben cupos, y las
   reservas hechas conservan su precio (ya previsto).
6. El enlace de una promoción vencida dice que terminó (ya previsto).

## 6. Mensajes de WhatsApp ya escritos (sobre la cita)

No son publicidad y no necesitan consentimiento aparte. No deben incluir ofertas.

> Hola {nombre}, te escribimos de {Salón} para confirmar tu cita del {día} a las {hora}.

> Hola {nombre}, te recordamos tu cita en {Salón} mañana a las {hora}. Si no puedes venir,
> avísanos por aquí.

> Hola {nombre}, tuvimos que mover tu cita del {día}. ¿Te sirve el {nuevo día} a las {hora}?
> Si no, te devolvemos la seña.

## 7. Publicidad del salón **[PENDIENTE: no existe]**

Solo a clientes que marcaron la casilla 13 de `consentimientos.md`. Todo mensaje dice quién lo
envía y cómo darse de baja:

> {Salón}: {promoción}. Condiciones en {enlace}. Si no quieres recibir más promociones, responde BAJA.
