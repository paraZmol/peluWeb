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
Primera visita (Descuento con condición "cliente nuevo") → Combo a precio cerrado → 2x1 (una
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
- **Letra chica**, armada sola y no editable: "Válido para citas del {inicio} al {fin}. Aplica a:
  {servicios}. [Solo {días}, de {hora} a {hora}.] [Cupos: {n}. | Sin límite de cupos, sujeto a
  horarios disponibles.] [Una por cliente.] Con reserva previa. No acumulable con otras
  promociones. Precios en soles con IGV incluido." (Art. 14 de la Ley 29571, citado de memoria:
  **confirmar con el texto legal antes del paso 9.4**; si piden un número de cupos, el campo pasa
  a obligatorio con 30 por defecto.)

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
  reserva. Letra chica extra: "El servicio de regalo se realiza en la misma cita y no es
  canjeable por dinero ni por otro servicio."

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
