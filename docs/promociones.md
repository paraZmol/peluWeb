# Promociones — spec del módulo 4.13 (paso 6.7)

Decidido por el agente `marketing-advisor` el 2026-10-10, a pedido del usuario ("opciones
predeterminadas", "descuento o rasurado gratis"). Si algo cambia, primero aquí y en `PLAN.md` §11.

## Qué se construye
El dueño entra a **Promociones**, toca **Nueva promoción** y elige de una lista de tarjetas.
Rellena dos o tres campos, ve cómo quedará y publica. No hay formulario genérico de "beneficio".
El sistema escribe solo el título, la línea y la letra chica. La promoción se aplica sola al
reservar: sin códigos y sin que nadie la "aplique" a mano. El precio queda congelado en la reserva.

### Primera entrega: cuatro tarjetas sobre dos mecánicas
1. **Descuento** — "20 % menos en corte", "S/ 10 menos en manicure".
2. **Servicio de regalo** — "rasurado gratis con tu corte", "diseño de cejas gratis con tu tinte".
3. **Horas flojas** — el Descuento con días y horas obligatorios.
4. **Campaña de temporada** — se elige la ocasión (San Valentín, Día de la Madre, Día del Padre,
   Fiestas Patrias, Regreso a clases, Halloween, Navidad y Año Nuevo, u "Otra") y luego Descuento
   o Servicio de regalo; nombre y fechas ya sugeridos (10 días antes y hasta el día).

### Segunda entrega, en orden
Primera visita (Descuento con condición "cliente nuevo"; *construida el 2026-10-14*: quinta
tarjeta, solo descuento; "nuevo" = sin ninguna reserva en el salón salvo canceladas o con la
retención vencida, así que cuentan las pasadas, las no asistidas y las próximas; se comprueba en
el servidor con la fila del cliente bloqueada; a un visitante sin sesión se le muestran los
horarios con promo y, si al entrar resulta que ya vino, la reserva no se crea y se le pide elegir
de nuevo para ver su precio) → Combo a precio cerrado → 2x1 (una
reserva, un profesional, dos turnos seguidos del mismo servicio, se paga el más caro; exige que
`QuoteBooking` admita el servicio repetido y anotar a quien acompaña) → filtro por profesional →
imagen descargable para Instagram.

## Reglas comunes
- **Vigencia** por la fecha de la cita, en la hora del salón. Fin obligatorio, máximo 90 días.
  Días y horas opcionales: cuenta la hora de inicio.
- **Precio normal**: lo pone el sistema (el del profesional o la variante elegidos). El dueño
  nunca escribe un "antes". Con variantes o precio por profesional se muestra "desde".
- **Redondeo**: el precio con promoción de cada servicio, hacia abajo a 10 céntimos.
- **Una promoción por reserva**. Si califican dos, gana la que más ahorra; si empatan, la que
  vence antes. El cliente no elige.
- **Cupos** opcionales (sin límite por defecto). Ocupa cupo toda reserva no cancelada, también la
  pendiente de seña; al vencer su retención lo libera. Se cuentan dentro del bloqueo de la reserva.
- **Una por cliente** opcional, apagada por defecto; por la ficha del cliente en el salón. Cancelar
  la devuelve; completar o no presentarse la consume.
- **Seña**: porcentaje del salón sobre el total ya con promoción (VIP con su porcentaje).
- **Mover**: el cliente solo a horarios donde la promoción sigue valiendo; el salón a cualquiera,
  conservando el precio. **Cancelar** libera el cupo.
- **Después de publicar**: las reservas hechas conservan su precio. Con reservas hechas solo se
  puede pausar, alargar la fecha, subir cupos o cambiar el título; para lo demás, "Duplicar". Si
  un servicio incluido se desactiva, la promoción pasa a "Revisar" y deja de ofrecerse.
- **Comisión**: sobre lo cobrado, como hoy; la línea de regalo da S/ 0. La vista previa lo avisa.
- **VIP**: vale igual, también en franjas VIP si caen en el horario.
- **Descuento manual de caja**: se puede sumar encima, con permiso de caja y el tope actual. En
  caja y reportes van separados: "Promoción −S/ X" y "Descuento manual −S/ Y". Cobrar una cita
  con promoción no exige permiso de caja: no es un descuento discrecional.
- **Máximo 3 promociones activas** por salón. En todos los planes y en la prueba gratis.
- **Quién**: crear, pausar y editar es solo del dueño. En reservas creadas por el personal la
  promoción aparece premarcada y se puede quitar.
- **Letra chica**, armada sola y no editable (versión del 2026-10-13, tras la revisión de
  `legal-compliance`; la escribe `PromotionCopy::finePrint` y dice todo lo que el motor aplica):
  "Promoción de {salón}. Válido para citas del {día} al {día} de {mes} de {año}. Aplica a:
  {servicios}. [Solo {días}, en citas que empiecen entre las {hora} y las {hora}.] [Cupos: {n}
  reservas. | Sin límite de cupos: vale para todas las citas que se reserven dentro de estas
  fechas, mientras haya horarios libres.] [Solo para tu primera reserva en {salón}: no vale si ya
  tuviste una cita allí, aunque no hayas asistido, ni si tienes otra reservada. Las citas
  canceladas no cuentan. Lo comprobamos con tu cuenta y tu teléfono. | Una por cliente.] [frase
  del combo] [El descuento de S/ {monto} se aplica
  una sola vez por reserva.] [El regalo ({servicio}, precio normal S/ {precio}) se hace en la
  misma cita y alarga la cita {n} min. No se cambia por dinero ni por otro servicio.] Si cambias
  tu cita, la promoción se mantiene solo si la nueva fecha y hora cumplen estas condiciones. [El
  precio con promoción se reserva hasta el plazo para enviar la seña.] Con reserva previa. No se
  suma a otras promociones: si tu cita cumple más de una, se aplica la de mayor ahorro. Precios
  finales en soles, con impuestos incluidos." Borrador: **lo confirma un abogado antes del paso
  9.4**; si pide un número de cupos, el campo pasa a obligatorio con 30 por defecto.
- **Lo que se anuncia** (2026-10-13): una promoción con los cupos agotados deja de anunciarse (su
  enlace dice que ya no está disponible); la duración y los cupos se leen en la tarjeta y en los
  textos para compartir sin abrir las condiciones; un título escrito por el salón va siempre
  acompañado del título automático; y una reserva nunca se guarda a un precio distinto del que
  la persona vio al confirmar (si la promoción se fue, no se crea y se le pide elegir de nuevo).

### Precisiones del motor (2026-10-12, tras el ataque de `booking-breaker`)
- Los cupos y "una por cliente" se cuentan con la fila de la promoción bloqueada y con lecturas
  bloqueantes: una lectura normal contaría con la foto anterior a la espera del bloqueo.
- Una reserva cuya retención venció devolvió su cupo. Si otra reserva lo tomó, "Seña recibida" se
  rechaza con un mensaje que pide avisar a la persona: si acepta el precio normal, cancelar y
  crear una nueva; si no, devolverle la seña completa.
- El monto fijo solo se aplica a un servicio que cueste **más** que el monto con ese profesional o
  esa variante (nunca deja un servicio gratis) y el resultado se redondea hacia abajo a 10 céntimos.
- El porcentaje nunca redondea un servicio a S/ 0.
- El regalo es el servicio sin opción elegida: una variante suya se cobra a su precio.
- Un servicio incluido borrado (no solo desactivado) también detiene la promoción.
- Días y horas pueden cruzar la medianoche ("22:00 a 02:00").
- Si la promoción se borró del todo, el cliente ya no puede mover esa reserva: solo el salón.

## Fichas

### Descuento
- Campos: servicios (ninguno marcado; atajo "Todos" sin marcar), cuánto (20 %; porcentaje de 5 a
  70 o monto en soles), fechas (hoy a 14 días), "Más opciones" plegado (días y horas, cupos, una
  por cliente).
- Cliente: "{X} % menos en {servicio}" / "{Servicio}: S/ {normal} → S/ {promo}" con el normal tachado.
- Reglas: el porcentaje va a cada servicio incluido; el monto fijo, una vez por reserva sobre los
  incluidos y menor que el incluido más barato; nunca por debajo de S/ 0. La duración no cambia.

### Servicio de regalo
- Campos: "Al reservar…" (uno o varios servicios; basta cualquiera), "…regalamos" (un solo
  servicio con precio fijo: sin variantes ni "requiere evaluación"; primero los adicionales y los
  más cortos), fechas, "Más opciones".
- Cliente: "{B} gratis con tu {A}" / "Ahorras S/ {precio de B}". En la reserva, al elegir A:
  "Te regalamos {B} (+15 min)" con botón "Agregar gratis" (un toque, no automático: alarga la
  cita). Desde el enlace de la promoción, A y B llegan marcados.
- Reglas: B entra como una línea más, S/ 0 cobrado y precio normal guardado; la duración incluye
  a B; solo profesionales que hacen A y B; si se quita A, B vuelve a precio normal; un regalo por
  reserva. Letra chica extra: la frase del regalo de la sección "Letra chica".

### Combo a precio cerrado (segunda entrega; reglas elegidas por el usuario el 2026-10-14)
- Propuesta de `marketing-advisor`; el usuario eligió las tres opciones recomendadas.
- Campos: **2 o 3 servicios**, todos obligatorios (sin "requiere evaluación"; no solo adicionales),
  **precio del combo** en soles, fechas y "Más opciones". El precio del combo se compara con la
  suma de los **precios base** (la opción más barata de cada servicio, a precio de catálogo): la
  rebaja va del 5 % al 50 %. Se guarda como **ahorro fijo en soles** (`amount_cents`).
- **Mismo ahorro siempre**: con una opción más cara o un profesional con precio propio, la persona
  paga la diferencia y ahorra lo mismo. Con opciones o precios por profesional el título dice
  "desde". Ningún servicio puede quedar en S/ 0: si el ahorro no cabe, el combo no aplica.
- **Reparto proporcional**: el ahorro se reparte entre los servicios del combo en proporción a su
  precio en esa reserva (el último absorbe el resto, para que el ahorro dé exacto), igual que el
  descuento manual. Comisiones y reportes no cambian de lógica.
- Cliente: "{A} + {B} por S/ {precio}" (o "desde"), línea "Por separado S/ {normal}: ahorras
  S/ {ahorro}". La tarjeta abre el asistente con los servicios marcados; se pueden sumar otros a
  precio normal; si se quita uno del combo, vuelve el precio normal con el aviso "Sin {servicio}
  el combo no aplica". Solo profesionales que hacen todos los servicios del combo.
- Letra chica extra (revisada por `legal-compliance` el 2026-10-14): "El precio del combo es por
  {A} y {B} juntos, en la misma cita y atendidos por una sola persona del equipo. Si eliges una
  opción o un profesional con un precio más alto, pagas esa diferencia y sigues ahorrando
  S/ {ahorro}. Si quitas uno de los servicios, el combo no aplica y los demás se cobran a su
  precio normal." Con "desde", la línea dice "Por separado desde S/ {normal}".
- **Nombre sin precio**: se guarda (y se congela en la reserva) como "Combo {A} + {B}"; el
  "{A} + {B} por S/ {precio}" se escribe en vivo, porque los precios del catálogo cambian.
- **Sin redondeo a 10 céntimos**: es la excepción a la regla general; el ahorro debe dar exacto.
- **El precio anunciado se puede reservar**: "por separado" y el precio del combo salen del
  profesional activo **más barato entre los que hacen todos sus servicios** (la opción más barata
  de cada uno). Un combo que nadie del equipo hace completo no se puede crear ni se anuncia.
- Gana la promoción de mayor ahorro, como siempre. Un solo profesional por reserva.

### Ven con alguien (2x1) (segunda entrega; reglas elegidas por el usuario el 2026-10-14)
- Propuesta de `marketing-advisor`; el usuario eligió las tres opciones recomendadas.
- Dos personas, **el mismo servicio**, el mismo profesional, una después de otra, en **una sola
  reserva** de exactamente dos líneas. Cada persona elige su opción (corto y largo); se cobra
  entero el más caro y se rebaja el más barato.
- Beneficio a elegir: **"el segundo a mitad de precio"** (marcado; cualquier horario) o **"2x1,
  el segundo gratis"** (días y horas obligatorios, como Horas flojas). Mecánica `pair` con
  `percent` 50 o 100 sobre la línea más barata (la mitad se redondea hacia abajo a 10 céntimos).
- Servicios: de precio fijo (sin "requiere evaluación"), no adicionales. Uno o varios incluidos:
  vale cualquiera de ellos repetido.
- **Quien acompaña: solo su nombre**, obligatorio, guardado en su línea (`booking_services.
  companion_name`); sin teléfono ni ficha. La reserva, la seña, "una por cliente" y la reseña son
  de quien reserva; quien acompaña no cuenta como visita propia.
- **Si llega una sola persona** (corregido tras `legal-compliance`): el salón dice **quién vino**
  (quien reservó o quien acompaña); se quita la otra línea y se cobra a precio normal solo el
  servicio de quien vino. La seña pagada se descuenta de ese monto; no es cancelación ni
  inasistencia. El historial guarda quién lo hizo, sin el nombre de quien acompañaba. El tiempo
  reservado no cambia.
- Mover y cancelar: las dos líneas van juntas. Seña sobre el total con promoción. Un cupo es una
  reserva.
- Títulos: "2x1 en {servicio}" / "Segundo {servicio} a mitad de precio"; la tarjeta dice sin
  abrir condiciones "Se cobra entero el de mayor precio" y, en la modalidad gratis, sus días y
  horas. Letra chica extra (revisada por `legal-compliance` el 2026-10-14): "Vale para dos
  personas que reservan juntas, en una sola reserva, el mismo servicio, atendidas una después de
  otra por la misma persona del equipo. Si eligen opciones de distinto precio, se cobra entero el
  de mayor precio y el otro es gratis [va a mitad de precio]. Al reservar se pide solo el nombre
  de quien acompaña. Si llega una sola persona, la promoción no aplica: se cobra a precio normal
  solo el servicio de quien vino y la seña pagada se descuenta de ese monto. El beneficio no se
  cambia por dinero ni se pasa a otra cita."
- Junto al campo del nombre: "Nombre de quien te acompaña: solo el nombre o como prefiera que lo
  llamen. Lo verán el salón y tú, únicamente para esta cita. Avísale que lo compartes."

### Filtro por profesional (segunda entrega, 2026-10-14)
- En «Más opciones» de cualquier tarjeta: "Solo con…" y la lista del equipo; ninguna marcada =
  todo el equipo. Se guarda en `promotion_staff`.
- Aplica solo cuando quien reserva **elige** a una de esas personas (o el personal reserva con
  ella). Con «Cualquiera» no aplica: el precio mostrado no puede depender de a quién se asigna
  después. Los horarios se marcan "con promo" solo con esa persona elegida.
- Tarjeta pública: "Con {nombres}". Letra chica: "Solo con {nombres}: al reservar, elige a una
  de estas personas." Solo el salón puede pasar la cita a otra persona del equipo, y entonces el
  precio se mantiene, como al mover cualquier cita con promoción.

### Horas flojas
- Los campos de Descuento con días y horas a la vista y obligatorios (por defecto lunes a
  miércoles, 10:00–16:00, 20 %, 30 días).
- En el paso de día y hora, los horarios donde vale llevan la marca "con promo".

### Campaña de temporada
- Ocasión + qué se ofrece (Descuento o Servicio de regalo). El nombre de la campaña va de
  antetítulo: "Halloween · Decoración gratis con tu Semipermanente".

## Pantalla "Promociones" del dueño
1. **Lista**: tarjeta por promoción con título, fechas, estado (Borrador, Programada, Activa,
   Pausada, Terminada, Revisar) y resultados (reservas, completadas, S/ cobrado, S/ regalado,
   clientes nuevos, cupos usados). Acciones: Compartir, Pausar, Duplicar. Vacía, muestra las
   tarjetas de tipo como invitación.
2. **Nueva promoción**: las tarjetas de tipo, cada una con una frase y un ejemplo hecho con los
   servicios del propio salón.
3. **Rellenar**: una pantalla, dos o tres campos y "Más opciones" plegado.
4. **Vista previa en vivo**: la tarjeta con la plantilla del salón, la letra chica y un resumen
   para el dueño ("El cliente paga S/ 20 en vez de S/ 25. Quien atiende comisiona sobre S/ 20.
   Seña: S/ 4.").
5. **Publicar** o guardar borrador; si empieza en el futuro queda Programada.
6. **Compartir**: enlace propio (abre la página del salón con la promoción destacada y los
   servicios marcados; necesita etiquetas Open Graph), "Copiar enlace", "Enviar por WhatsApp" y
   "Copiar texto para Instagram". Un enlace vencido dice "Esta promoción terminó" y ofrece
   reservar a precio normal: nunca un 404.

## Dónde la ve el cliente
Franja "Promociones" sobre los servicios en la página pública; etiqueta y precio tachado en la
lista de servicios; paso 1 de la reserva y horarios marcados en el paso 3; línea "Promoción
{nombre}: −S/ X" al confirmar, en el correo y en Mis reservas.

## Qué guarda el sistema (para no rehacer nada al añadir tipos)
- **Promoción**: salón, mecánica (descuento, regalo y las futuras), tarjeta de origen, nombre y
  antetítulo, estado, inicio y fin, días y horas opcionales, servicios incluidos, servicio de
  regalo o parámetro del beneficio (porcentaje o céntimos), cupos, una por cliente, condición de
  cliente (hoy "todos"; reservada para "nuevo"), enlace corto.
- **Reserva**: qué promoción se aplicó, con su nombre congelado, y el monto de promoción en
  céntimos, separado del descuento manual.
- **Cada servicio de la reserva**: precio normal y precio cobrado (cubre regalo, combo y 2x1).

## Descartado
Bonos o paquetes prepagados (sin pasarela el salón quedaría debiendo servicios cobrados por
fuera), cupones con código, promociones acumulables, texto libre en la letra chica, "solo
destacar" sin beneficio, referidos con recompensa, tarjeta de sellos y puntos, precios dinámicos
automáticos, envíos masivos de pago, vitrina de ofertas entre salones.

## Más adelante
Reactivación de quien no viene hace meses y cumpleaños (necesitan correo o push), sugerir horas
flojas desde el reporte de ocupación, aviso si el precio de un servicio subió en los 30 días
previos a ponerlo en promoción.
