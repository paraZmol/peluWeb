---
name: booking-domain
description: Reglas del dominio de reservas de peluWeb — cálculo de disponibilidad, duración por profesional, tiempos de reposo, prevención de solapes en MySQL, reservas VIP, estados de la reserva, señas por WhatsApp y cancelaciones. Cargar ANTES de tocar disponibilidad, agenda, horarios del personal, servicios, señas o estados de reserva.
---

# Dominio de reservas

## Conceptos
- **Salón** (`salons`): tiene zona horaria (`America/Lima` por defecto), antelación mínima
  (`min_lead_minutes`, 60 por defecto), horizonte (`max_days_ahead`, 60) y rejilla
  (`slot_step_minutes`, 15).
- **Profesional** (`staff`): persona que atiende. La agenda es **por profesional**: dos
  profesionales distintos sí pueden atender a la misma hora.
- **Servicio** (`services`): tiene duración base y precio base. Cada profesional puede
  sobrescribirlos en `service_staff` (`active_minutes`, `price_cents`) — "el peluquero decide
  cuánto le toma".
- **Tramos**: un servicio puede tener `active_minutes` → `idle_minutes` → `finish_minutes`
  (ej. tinte 20 → 35 → 25). Durante `idle` el profesional está libre para otro cliente.
  Si `idle_minutes = 0`, es un solo tramo.
- **Horario laboral** (`staff_schedules`): por día de semana, una o varias franjas
  (`start_time`, `end_time`, `access_level`). Se edita en la UI tipo "alarma" (rueda de hora).
- **Excepciones** (`staff_time_off`): vacaciones, almuerzo puntual, bloqueos manuales.

## Regla de oro
La disponibilidad se calcula en **una sola clase pura** `App\Domain\Booking\AvailabilityCalculator`
(sin Eloquent, sin `now()` interno: recibe todo por parámetro). La UI y la API solo la llaman.
Cualquier bug de solape se arregla ahí y en la transacción de guardado, **nunca** en el frontend.

## Cálculo de horarios libres
Entrada: franjas laborales del día, excepciones, tramos ocupados existentes, tramos del servicio
pedido, rejilla, `nowUtc`, antelación mínima, nivel del cliente.
1. Un inicio candidato es válido si **cada tramo activo** del servicio cabe entero dentro de una
   franja laboral y no se solapa con ningún tramo ocupado ni excepción.
2. Solape: `aStart < bEnd && bStart < aEnd`. Extremos que se tocan **no** solapan.
3. Se descartan inicios `< nowUtc + min_lead_minutes` y `> hoy + max_days_ahead`.
4. La rejilla es independiente de la duración (un servicio de 50 min puede empezar 9:15).
5. Ocupan agenda las reservas en estado `pending` (no vencida), `confirmed` e `in_service`.

## Prevención de doble reserva en MySQL (no hay exclusion constraints)
Al guardar, dentro de `DB::transaction()`:
1. `SELECT ... FROM staff WHERE id = ? FOR UPDATE` → serializa todas las reservas del mismo
   profesional (los demás profesionales no se bloquean).
2. Re-consultar tramos ocupados de ese profesional en el rango y volver a validar con el
   **mismo** `AvailabilityCalculator`.
3. Insertar `bookings` + `booking_segments` (un registro por tramo activo, con `staff_id`,
   `starts_at`, `ends_at` en UTC).
4. Si otro proceso ganó, responder 409 con un mensaje amable y horarios alternativos.
**Nada lee antes del bloqueo dentro de la misma transacción.** MySQL usa REPEATABLE READ: la
primera lectura fija la vista de toda la transacción, así que un `SELECT` hecho antes del
`FOR UPDATE` deja la agenda "congelada" y dos personas reservan la misma hora (bug real,
2026-10-08: una transacción exterior en el controlador). Por eso: `CreateBooking` nunca se
envuelve en otra transacción; su primera sentencia es el bloqueo del profesional; y las lecturas
bajo bloqueo son lecturas bloqueantes (`sharedLock()`), sin subconsultas (MySQL no aplica el
bloqueo a las tablas de una subconsulta anidada).
Orden de bloqueos, siempre: **profesional → cliente → reserva → salón**. Quien edita horarios,
ausencias, servicios o al propio profesional toma primero el bloqueo del profesional.
Test obligatorio también por HTTP (`tests/Concurrency`), no solo llamando a la acción.
Índice obligatorio: `booking_segments (staff_id, starts_at, ends_at)`.
Test obligatorio: dos procesos concurrentes, el mismo horario → exactamente una reserva.

**La ficha del cliente nace con su primera reserva.** `ResolveSalonClient` devuelve la ficha
existente o una nueva **sin guardar**; `CreateBooking` la guarda dentro de la transacción, justo
antes de la reserva. Nunca crear la ficha antes ni borrarla después: borrar una ficha "vacía" se
llevó en cascada la cita que otra petición acababa de colgarle (bug real, 2026-10-08).
Quien cierra días o cambia horarios (feriado, ausencia, horario semanal) toma el bloqueo de los
profesionales afectados **antes** de comprobar que no deja citas fuera.

## Mover una cita (`RescheduleBooking`)
Se valida igual que una reserva nueva: bloquea a los profesionales implicados (los dos a la vez y
en orden de id si cambia de profesional), luego la reserva, y el nuevo inicio debe estar entre los
que ofrecería el selector, ignorando el lugar que la propia cita ocupa (`ignoreBookingId`).
- Conserva servicios, duraciones, precio y seña congelados al reservar; solo cambian inicio, fin,
  tramos y (si el equipo lo decide) el profesional. Deja un evento con `from_status = to_status`.
- Solo citas `pending` (retención viva) o `confirmed`.
- **Cliente**: mismo profesional, su propio nivel (estándar/VIP), con el aviso de
  `cancel_window_hours` y hasta `MAX_BY_CLIENT` (3) veces. **Equipo**: nivel `Team`; pasarla a
  otro profesional exige ver toda la agenda y que ese profesional haga todos sus servicios.
- Como `CreateBooking`, nunca dentro de otra transacción.

## Niveles de acceso (VIP)
Cada franja laboral tiene `access_level`: `public` | `vip` | `staff_only`.
- `vip`: solo clientes marcados VIP **en ese salón** (`salon_clients.is_vip`). El dueño lo asigna.
- `staff_only`: solo el personal la usa (reservas que llegan por teléfono o en persona).
- El filtro se aplica en el servidor: un cliente estándar **nunca** recibe una franja VIP en el JSON.
- VIP puede tener seña distinta (`vip_deposit_percent`) y prioridad en lista de espera.

## Estados (máquina de estados estricta, en `BookingStatus` enum)
```
pending ──(seña confirmada / salón sin seña)──> confirmed ──> in_service ──> completed
   │                                               │
   │ expira (hold_minutes, 30 por defecto)         ├─(cliente o salón cancela)──> cancelled
   ▼                                               └─(no vino, tras la hora)────> no_show
expired
```
- Cada transición registra `changed_by` y `changed_at` en `booking_events` (auditoría).
- El cliente solo puede cancelar con ≥ `cancel_window_hours` (24 por defecto); menos de eso
  la seña no se devuelve (`deposit_refundable = false`).
- Una reserva `pending` vencida deja de ocupar agenda **por sí sola** (se filtra por
  `hold_expires_at`), sin depender de que corra un cron. El cron solo la marca `expired`.

## Señas y dinero (v1 = WhatsApp, sin pasarela)
- `deposit_cents = round(price_cents * deposit_percent / 100)`; si es 0, la reserva nace `confirmed`.
- Se genera en el servidor un enlace `https://wa.me/<telefono_salon>?text=<mensaje>` con nombre,
  servicio, fecha/hora local, monto y datos de Yape/Plin del salón.
- La web **no verifica** pagos: el personal pulsa "Seña recibida" → `confirmed`, registrando quién.
- Todo cobro se guarda como movimiento en `payments` (método: efectivo, yape, plin, tarjeta,
  transferencia) para que la contabilidad cuadre. Montos SIEMPRE en céntimos (`int`), nunca float.
- El precio cobrado vive en la reserva (`charged_cents`); los reportes suman eso, no el catálogo.
- Todo el cálculo está detrás de la interfaz `PaymentProvider` para poder enchufar una pasarela
  (Mercado Pago / Izipay / Culqi) en una versión futura sin tocar la UI.

## Fechas
UTC en la base (`DATETIME` en UTC), zona del salón en la vista. Nombres con sufijo `_utc` o
variables `$startsAtUtc` cuando haya ambigüedad. Nunca usar la zona del servidor.

## Invariantes con test obligatorio
- Dos reservas activas del mismo profesional jamás se solapan (test concurrente real).
- Un servicio que excede el cierre no aparece.
- Durante el reposo de un tinte se ofrece el hueco; el tramo final no se pisa.
- Cliente estándar recibe cero franjas VIP.
- Un `pending` vencido no bloquea, aunque el cron no haya corrido.
- Cambiar la zona horaria del navegador no mueve reservas existentes.
