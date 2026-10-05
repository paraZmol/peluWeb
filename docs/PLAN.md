# peluWeb — Plan maestro

> **Cómo usar este plan**
> - Es la única fuente de verdad. Si algo no está aquí, no se construye todavía: se anota en
>   "Ideas para después" (al final).
> - Al terminar un paso: `[ ]` → `[x]` y la fecha. `[~]` = a medias (se explica qué falta). Un paso solo está terminado si pasó sus
>   criterios de aceptación y los agentes de calidad que le tocan (ver §8).
> - **Si decidimos cambiar algo**, primero se escribe en el "Registro de cambios" (§11) qué cambia y
>   por qué, se ajustan los pasos afectados, y después se toca el código.
>
> Documentos de apoyo: [stack.md](stack.md) · [mercado.md](mercado.md) ·
> [catalogo-servicios.md](catalogo-servicios.md) · skills `booking-domain` y `ui-system` en `.claude/skills/`.

---

## 1. Visión
Un solo sistema web donde **cualquier peluquería, barbería o salón** del Perú (y luego de LATAM)
gestiona sus reservas, su equipo, su caja y su imagen, y donde sus clientes reservan en menos de
un minuto desde el celular. Los salones **pagan una suscripción mensual** para usarlo. El dueño
de la plataforma (super admin) administra los salones, los planes y los cobros.

**Promesa al salón:** "Se acabaron las dobles reservas, los clientes que no llegan y las cuentas en
el cuaderno."
**Promesa al cliente:** "Elige estilo, profesional y hora en 4 toques, y confirma por WhatsApp."

## 2. Público objetivo
| Quién | Perfil | Qué gana |
|---|---|---|
| **Salones (pagan)** | Barberías, salones de belleza, unisex, uñas y spa con **1 a 6 profesionales** (punto ideal), en Lima primero y luego otras ciudades | Agenda sin choques, señas que reducen ausencias, caja y reportes, página propia con su marca |
| **Personal** | Peluqueros/as y barberos/as que usan el celular entre cita y cita | Ver "qué sigue", fijar su horario y la duración de sus servicios, ver sus comisiones |
| **Clientes (gratis)** | 18–45 años, reservan desde el celular, usan WhatsApp y Yape/Plin | Reservar a cualquier hora sin escribir ni llamar, ver precios, elegir estilo y profesional |
| **Super admin** | Tú | Panel para dar de alta salones, cobrar suscripciones, suspender, ver métricas y dar soporte |

Tipos de salón (definen la plantilla inicial de servicios y textos): **barbería**, **salón de belleza**,
**unisex**, **uñas**, **estética/spa**. El público (hombres / mujeres / todos) es un **atributo
aparte** que se muestra como filtro y en el perfil.

## 3. Roles y permisos
Una misma cuenta puede tener roles distintos en salones distintos (ej. clienta de un salón y
dueña de otro). La tabla `salon_user` guarda el rol **por salón**.

| Acción | Super admin | Dueño | Personal | Cliente | Visitante |
|---|:-:|:-:|:-:|:-:|:-:|
| Crear / suspender salones, planes, cobros de suscripción | ✅ | – | – | – | – |
| Entrar como un salón para dar soporte (queda registrado) | ✅ | – | – | – | – |
| Editar perfil del salón, logo, color, galería, ubicación | – | ✅ | ⚙️ | – | – |
| Gestionar servicios y precios del salón | – | ✅ | ⚙️ | – | – |
| Invitar y gestionar personal | – | ✅ | – | – | – |
| Fijar **su propio** horario y la duración de **sus** servicios | – | ✅ | ✅ | – | – |
| Ver agenda de todo el salón | – | ✅ | ⚙️ | – | – |
| Crear / mover / cancelar reservas de clientes | – | ✅ | ✅ | solo las suyas | – |
| Marcar seña recibida, cobrar, registrar gastos | – | ✅ | ⚙️ | – | – |
| Ver reportes de ganancias y pérdidas | – | ✅ | ⚙️ | – | – |
| Ver **sus propias** comisiones y citas atendidas | – | ✅ | ✅ | – | – |
| Marcar clientes VIP | – | ✅ | ⚙️ | – | – |
| Mostrar / ocultar reseñas, responderlas | – | ✅ | – | – | – |
| Reservar, ver historial, dejar reseña (tras cita completada) | – | – | – | ✅ | – |
| Ver perfil público, servicios, precios, ubicación | ✅ | ✅ | ✅ | ✅ | ✅ |

⚙️ = permiso que el dueño activa por persona (ej. "puede ver caja", "puede editar servicios"),
para cubrir recepcionistas o encargados sin crear más roles.

## 4. Módulos y funcionalidades del MVP
### 4.1 Cuentas y perfiles
- Registro e inicio de sesión con email y contraseña, y con **Google** (gratis). Verificación de email.
- Recuperar contraseña; 2FA opcional (obligatorio para el super admin).
- Perfil: nombre, foto, teléfono (WhatsApp), fecha de cumpleaños opcional, preferencias.
- **Tema claro / oscuro / del sistema**, guardado por usuario.
- El personal se une por **invitación** (enlace o email); el dueño no inventa contraseñas ajenas.
- El personal puede crear una reserva para alguien **sin cuenta** (solo nombre y teléfono).

### 4.2 Salón (perfil público y marca)
- Página pública `peluweb.pe/s/<nombre-del-salon>` con portada, logo, color de marca, descripción,
  tipo de salón y público, horario, servicios con precios, equipo, galería, reseñas (si están
  visibles), mapa y botones "Reservar", "WhatsApp" y "Cómo llegar".
- **Logo y color cambiables** en cualquier momento, con vista previa en claro y oscuro y ajuste
  automático de contraste.
- **Galería** de fotos (local, trabajos realizados, antes/después) con orden arrastrable.
- **Ubicación**: el dueño fija un pin en el mapa; se muestra con Google Maps Embed y un botón "Cómo llegar".
- Código QR del enlace de reservas para imprimir en el local.
- Datos de pago para señas (Yape/Plin: número y nombre; cuenta bancaria opcional).

### 4.3 Servicios y catálogo de estilos
- Al crear el salón se elige el tipo, se precarga la plantilla del catálogo
  ([catalogo-servicios.md](catalogo-servicios.md)) y el dueño desmarca lo que no ofrece.
- Cada servicio tiene categoría, precio, duración, tramos (activo / espera / final), variantes por
  largo de cabello, foto de referencia, si es adicional y si requiere evaluación o prueba de alergia.
- **Cada profesional ajusta su duración y su precio** por servicio ("yo hago el fade en 35 min").
- Buscador para el cliente por nombre en español o inglés ("fade", "balayage") y por filtros
  (público, largo, tipo de cabello).
- Combos (corte + barba, etc.) como servicio compuesto.

### 4.4 Horarios del personal
- Horario semanal por profesional con varias franjas por día, editado con el **selector tipo
  alarma** (ruedas de hora y minuto). Copiar un día a otros días con un toque.
- Cada franja es **pública**, **VIP** o **solo personal**.
- Excepciones: vacaciones, permisos, almuerzo distinto, bloqueo rápido ("bloquear 1 h ahora").
- Feriados del salón (cierra para todo el equipo).
- Si un cambio de horario deja reservas afuera, se muestran esas reservas para reprogramarlas.

### 4.5 Reservas (núcleo)
- Flujo del cliente en 4 pasos: **servicio(s) → profesional o "cualquiera" → día y hora → confirmar**.
- Fechas futuras hasta el horizonte del salón (60 días por defecto) y a una hora específica.
- **Nunca se solapan dos reservas del mismo profesional** (garantizado en servidor; ver `booking-domain`).
- Aprovecha los tiempos de espera: durante el reposo de un tinte se puede reservar otro corte.
- **Reservas VIP**: franjas que solo ven los clientes VIP del salón, con seña distinta y prioridad en
  la lista de espera.
- **Seña opcional por salón**: genera un mensaje de WhatsApp listo con el monto y el Yape/Plin; la
  reserva queda "pendiente" 30 min y el personal la confirma con un toque.
- El cliente reprograma o cancela según la política del salón (24 h por defecto).
- **Repetir última cita** con un toque. "Mi estilo": el cliente adjunta una foto de referencia.
- **Lista de espera**: si no hay hora, el cliente se anota y se le avisa si se libera.
- **Sin cita** (barberías): cola de llegada con tiempo estimado, visible en una pantalla del local.

### 4.6 Herramientas del profesional
- Vista **Hoy**: la cita actual, la siguiente con cuenta regresiva, huecos libres, y botones
  "Llegó", "Terminé y cobrar", "No vino".
- Agenda por día y semana (en escritorio, columnas por profesional); arrastrar para mover
  (valida choques antes de soltar).
- Ficha del cliente: historial, notas (alergias, fórmula de color, preferencias), fotos antes/después,
  cantidad de ausencias, VIP sí o no.
- Botones de WhatsApp con mensajes armados: confirmar, recordar, "ya puedes venir", "gracias por venir".
- Cobro rápido: método (efectivo, Yape, Plin, tarjeta, transferencia), propina y descuento.

### 4.7 Caja y contabilidad
- **Ingresos**: cada cobro de servicio, seña o producto queda como movimiento con método de pago.
- **Gastos**: alquiler, productos, servicios, sueldos, otros (categorías editables), con foto del comprobante.
- **Caja diaria**: apertura, cierre y cuadre por método de pago (efectivo esperado frente a contado).
- **Comisiones** por profesional: porcentaje por servicio o fijo; cálculo del período y marcar como pagadas.
- **Reportes**: ingresos, gastos y **ganancia o pérdida** por día, semana, mes y rango; por
  profesional, por servicio y por método de pago; ausencias y cancelaciones (dinero perdido);
  horas ocupadas frente a disponibles; clientes nuevos frente a recurrentes.
- Exportar a Excel/CSV y PDF.
- *Fuera del MVP:* boletas y facturas electrónicas SUNAT (ver Ideas para después).

### 4.8 Reseñas (con interruptor)
- Solo pueden reseñar clientes con una cita **completada** (reseña verificada), una por cita.
- El dueño elige el modo: **Ocultas** (no se piden ni se muestran), **Privadas** (se piden y solo
  las ve el salón como retroalimentación) o **Públicas** (visibles en el perfil, con promedio).
- En modo público se muestran **todas**. El dueño puede responder y **reportar** una reseña abusiva
  al super admin, pero no puede borrar selectivamente las negativas: si no quiere exponerse, la
  opción es Privadas u Ocultas. Esto protege la credibilidad de la plataforma y evita problemas con
  la protección al consumidor.
- Cambiar de modo es inmediato y reversible.

### 4.9 Notificaciones (costo cero en el MVP)
- Email: confirmación, recordatorio 24 h antes, cancelación, invitación al equipo.
- **Web Push** en la PWA instalada: recordatorio al cliente; nueva reserva o cancelación al profesional.
- WhatsApp por enlace `wa.me` (un toque del personal o del cliente; no es automático).

### 4.10 Plataforma SaaS (super admin)
- Panel Filament: salones (alta, edición, suspensión), planes, suscripciones, pagos recibidos,
  métricas (salones activos, reservas por mes, ingresos de la plataforma, vencimientos próximos).
- Planes con límites: número de profesionales, funciones (VIP, comisiones, reportes avanzados).
  Prueba gratis de 30 días.
- Cobro de suscripción v1: **manual** (Yape o transferencia, registras el pago y el plan se extiende).
  Avisos de vencimiento 7, 3 y 1 día antes; al vencer, 7 días de gracia y luego **solo lectura**
  (los clientes del salón ven "reservas en pausa"; ningún dato se borra).
- Entrar como un salón para dar soporte, con registro de auditoría.
- Moderación de reseñas reportadas.

### 4.11 Móvil
- **PWA** desde el MVP: instalable en Android (aviso de instalación) y en iOS (guía visual
  "Compartir → Añadir a pantalla de inicio"), ícono con la marca, pantalla de carga y modo sin
  conexión básico (ver mis próximas reservas).
- **Flutter** después del lanzamiento, contra la API `/api/v1` (que se diseña desde el inicio).

### 4.12 Legal (Perú)
- Términos y condiciones y política de privacidad (Ley 29733 de Protección de Datos Personales):
  consentimiento explícito al registrarse y opción de borrar la cuenta.
- Libro de Reclamaciones virtual de la plataforma (exigido por Indecopi).
- El salón es responsable de los datos de sus clientes; la plataforma es encargada del tratamiento.

---

## 5. Arquitectura (resumen; detalle en [stack.md](stack.md))
- **Laravel 13** (PHP 8.3+) + **Inertia 3 + React 19 + TypeScript** + **Tailwind 4 + shadcn/ui + Motion**.
- **MySQL 8.4**. Una sola base; las tablas de negocio llevan `salon_id`.
- Filament 5 para el super admin. Sanctum para la API móvil. Colas y scheduler con driver `database`
  (funciona en hosting compartido con un solo cron).
- Imágenes: Spatie Media Library, convertidas a WebP en 3 tamaños, sin EXIF.

### Modelo de datos (tablas principales)
`users` · `salons` · `salon_user` (rol y permisos por salón) · `plans` · `subscriptions` ·
`subscription_payments` · `staff` · `staff_schedules` · `staff_time_off` · `holidays` ·
`service_templates` (catálogo global) · `service_categories` · `services` · `service_variants` ·
`staff_service` (duración y precio por profesional) · `salon_clients` (VIP, notas, ausencias) ·
`bookings` · `booking_services` · `booking_segments` (tramos que ocupan agenda) · `booking_events`
(auditoría de estados) · `waitlist_entries` · `walk_in_queue` · `payments` · `expenses` ·
`expense_categories` · `cash_sessions` · `commission_rules` · `commission_payouts` · `reviews` ·
`media` · `push_subscriptions` · `audit_logs`.

---

## 6. Diseño
Ver skill `ui-system`. Resumen: estilo "salón editorial" (cálido, limpio, premium), fondo hueso en
claro y carbón cálido en oscuro, **un color de acento que elige cada salón**, Fraunces para títulos
e Inter para interfaz, móvil primero con acciones al alcance del pulgar, animaciones cortas en
login, reserva y agenda que respetan "reducir movimiento".

---

## 7. Fases, pasos y tiempos
Estimación para **una persona con Claude, ~25 h por semana**. A tiempo completo, multiplicar por ~0.6.
Inicio previsto: **lunes 2026-10-05**.

| Fase | Contenido | Semanas | Fin estimado |
|---|---|:-:|---|
| 0 | Entorno y esqueleto | 1 | 2026-10-11 |
| 1 | Cuentas, roles, multi-salón, tema | 2 | 2026-10-25 |
| 2 | Salón: perfil, marca, galería, mapa, servicios | 2 | 2026-11-08 |
| 3 | Personal y horarios | 1.5 | 2026-11-18 |
| 4 | Motor de reservas y agenda | 3 | 2026-12-09 |
| 5 | Caja, contabilidad y reportes | 2 | 2026-12-23 |
| 6 | Clientes, reseñas, notificaciones, lista de espera | 1.5 | 2027-01-06 |
| 7 | SaaS: planes y suscripciones | 1.5 | 2027-01-17 |
| 8 | PWA, pulido, auditoría y piloto con un salón real | 2 | 2027-01-31 |
| 9 | Lanzamiento | 1 | **2027-02-07** |
| 10 | Post-MVP (Flutter, pasarela, etc.) | — | según demanda |

**MVP vendible: ~17.5 semanas (≈ 4 meses).** Margen recomendado: +20 % para imprevistos (≈ fin de febrero de 2027).

### Fase 0 — Entorno y esqueleto
- [x] 0.1 PHP 8.4 en `C:\php84` (ZIP oficial, sin tocar XAMPP) + MySQL 9.6 existente con base y usuario `peluweb`. *(2026-10-04)*
- [~] 0.2 `git init` hecho (rama `main`). **Falta:** primer commit y repositorio privado en GitHub (decisión D6).
- [x] 0.3 Proyecto con el starter kit React de Laravel 13.34 conectado a MySQL; login y registro responden. *(2026-10-04)*
- [~] 0.4 Pest 4.7, Larastan nivel 7, Pint y lint/tipos listos (40/40 tests en MySQL). **Falta:** pruebas de navegador (Pest browser + Playwright) → se instalan en el paso 1.3, con las primeras pantallas propias.
- [x] 0.5 Workflow de CI con MySQL 8.4, PHP 8.4, `composer ci:check`, `composer audit` y `npm audit` (corre cuando exista el repositorio en GitHub). *(2026-10-04)*
- [x] 0.6 Tokens de color claro/oscuro, fuentes Inter + Fraunces, transición de tema, "reducir movimiento" y Motion instalado. El cambio de tema claro/oscuro/sistema ya viene en el kit (Ajustes → Apariencia). *(2026-10-04)*
- [x] 0.7 Calidad de código: Boost + skill `clean-code` + modelos estrictos + `dev.cmd`. *(2026-10-04)*

### Fase 1 — Cuentas, roles y multi-salón
- [x] 1.1 Tablas `salons`, `salon_user`, `salon_invitations` + `CurrentSalon`, trait `BelongsToSalon` y salón activo en la sesión, resuelto **antes** del route model binding. *(2026-10-04)*
- [x] 1.2 Roles por salón con **tabla propia** (`salon_user.role` + `permissions`, ver §11), `SalonPolicy`, `SalonMemberPolicy` y middleware `salon`. *(2026-10-04)*
- [x] 1.3 Registro, login con email y con **Google** (se activa con `GOOGLE_CLIENT_ID`), verificación de email, recuperación, 2FA y passkeys. Animaciones de entrada y sacudida en error. Interfaz en español. *(2026-10-04)*
- [~] 1.4 Perfil: datos, **foto** (WebP 512 px, sin EXIF), WhatsApp, cambiar contraseña y borrar cuenta. **Falta:** el tema claro/oscuro se recuerda por navegador (cookie del kit), no por cuenta → se mueve a la Fase 8 (pulido).
- [x] 1.5 Invitaciones por correo: token hasheado, 7 días, mismo correo verificado, la nueva reemplaza a la anterior. *(2026-10-04)*
- [x] 1.6 Panel `/plataforma` (Filament 5): salones (ver, editar, suspender, prueba, eliminar/restaurar) y usuarios (solo lectura); solo super admin con 2FA; rol por consola `platform:grant-admin`. Los salones los crean sus dueños (autoservicio). *(2026-10-04)*
- [x] 1.7 Selector de salón en la barra lateral. *(2026-10-04)*
- [x] *Cierre (2026-10-04):* 181 tests Pest + 15 E2E (móvil y escritorio, claro y oscuro) en verde. **security-auditor**: 1 ALTO (toma de cuenta previa vía Google) + 5 MEDIOS + 6 BAJOS → todos corregidos con test de regresión (`tests/Feature/Security/Phase1HardeningTest.php`). **qa-tester**: 3 hallazgos → 2 bugs corregidos (único dueño que borra su cuenta; invitación que degradaba a un dueño), 1 era decisión de plan (§11). **ux-reviewer**: 15 puntos → corregidos los 3 BLOQUEA y 5 MOLESTA; el resto pasó a la Fase 8 (paso 8.7).

### Fase 2 — Salón, marca, servicios
- [x] 2.1 Alta del salón: pantalla única (nombre, tipo, público, WhatsApp) que precarga los servicios de su tipo + pantalla "Mi salón" por secciones + lista "Completa tu salón" con progreso en el panel. **Cambio:** el "horario general" pasa a la Fase 3 (sale de los horarios del personal). *(2026-10-04)*
- [x] 2.2 Logo, portada y color de marca con vista previa; `BrandColor` garantiza contraste AA en claro y oscuro (ajusta y avisa). El panel del dueño también usa su color. *(2026-10-04)*
- [x] 2.3 Imágenes: WebP, logo 512², portada 1600×900, galería ajustada a 1600 px sin recortar, sin EXIF ni SVG, máximo 30, ordenable. *(2026-10-04)*
- [x] 2.4 Ubicación: mapa OpenStreetMap (Leaflet) con "usar mi ubicación", pegar enlace de Google Maps o tocar el mapa; botón "Cómo llegar" con Google Maps (gratis, sin clave). **Cambio:** no se usa el iframe de Maps Embed (pediría clave); si el tráfico crece se cambia el proveedor de mosaicos del mapa. *(2026-10-04)*
- [x] 2.5 Catálogo de plataforma: **116** servicios (no 106) con sinónimos en inglés, tramos, rangos de precio y etiquetas; migración de datos + `php artisan catalog:sync`. *(2026-10-04)*
- [x] 2.6 Servicios: del catálogo (buscador sin tildes) o propios; precio, tramos trabajo/espera/final, variantes, adicionales, evaluación y prueba de alergia; pausar y quitar. **Cambio:** los combos pasan a la Fase 4 (el cliente podrá elegir varios servicios en una reserva). *(2026-10-04)*
- [x] 2.7 Página pública `/s/{slug}` con servicios, galería, mapa, WhatsApp, compartir; Open Graph renderizado en servidor; QR descargable en "Mi salón". *(2026-10-04)*
- [~] *Cierre:* 253 tests + 19 E2E en verde. **security-auditor**: 0 críticos/altos, 7 bajos → 5 corregidos (variantes como lista y sin duración ≤ 0, texto plano en campos libres, límite en la página pública, foto procesada fuera del bloqueo); imágenes de salón suspendido siguen accesibles por URL directa (aceptado: nombres aleatorios) y CSP completa en 8.8. **ux-reviewer**: 15 puntos → corregidos los 3 BLOQUEA (mapa estático que no atrapa el dedo, portada compacta sin foto, acción cuando no hay servicios o WhatsApp) y 7 MOLESTA, incluido un **bug real**: el color de marca no pintaba los botones (Tailwind resuelve `--color-primary` en `:root`). **qa-tester** en curso.

### Fase 3 — Personal y horarios
- [ ] 3.1 Componente `TimeWheel` (selector tipo alarma) con teclado y lectores de pantalla.
- [ ] 3.2 Horario semanal por profesional, varias franjas, nivel público / VIP / solo personal, copiar día. Incluye el **horario general del salón** (movido desde 2.1).
- [ ] 3.3 Excepciones, bloqueo rápido y feriados del salón.
- [ ] 3.4 Duración y precio por profesional y servicio.
- [ ] 3.5 Aviso de reservas afectadas al cambiar un horario.
- [ ] 3.6 Mostrar en la página pública el horario de atención y "Abierto ahora / Abre a las…" (pedido por ux-reviewer en la Fase 2).
- *Cierre:* **qa-tester** + **ux-reviewer** (editar horario en 360 px).

### Fase 4 — Motor de reservas y agenda (núcleo; no recortar tests)
- [ ] 4.1 `AvailabilityCalculator` puro con tests primero (todas las invariantes de `booking-domain`).
- [ ] 4.2 Transacción de reserva con `lockForUpdate` + test concurrente real.
- [ ] 4.3 Máquina de estados con `booking_events`; expiración de pendientes sin depender del cron.
- [ ] 4.4 Flujo del cliente en 4 pasos (permite elegir **varios servicios** en una reserva, que reemplaza a los combos de 2.6) con animaciones (paso a paso, chip → resumen, check al confirmar).
- [ ] 4.5 Reservas VIP: filtro en servidor, seña distinta, marca VIP en la ficha.
- [ ] 4.6 Señas: mensaje `wa.me` armado en servidor y botón "Seña recibida".
- [ ] 4.7 Vista **Hoy** del profesional y agenda día/semana con arrastrar para mover.
- [ ] 4.8 Reserva creada por el personal para un cliente sin cuenta; reprogramar y cancelar según política.
- [ ] 4.9 Mis reservas del cliente, repetir última cita, foto de referencia de estilo.
- [ ] 4.10 API `/api/v1` de disponibilidad y reservas (Sanctum), para la futura app.
- *Cierre:* **booking-breaker** (obligatorio) + **security-auditor** + **qa-tester** + **ux-reviewer**.

### Fase 5 — Caja, contabilidad y reportes
- [ ] 5.1 `payments` desde el cobro de la cita (método, propina, descuento) y desde las señas.
- [ ] 5.2 Gastos con categorías y foto del comprobante.
- [ ] 5.3 Apertura y cierre de caja con cuadre por método.
- [ ] 5.4 Reglas de comisión y liquidación por período.
- [ ] 5.5 Reportes de ganancias y pérdidas con gráficos (cargar skill `dataviz`), filtros y comparativa con el período anterior.
- [ ] 5.6 Exportar a Excel/CSV/PDF.
- *Cierre:* test de que los totales cuadran al céntimo + **security-auditor** (permisos de caja) + **qa-tester**.

### Fase 6 — Clientes, reseñas, notificaciones
- [ ] 6.1 Ficha del cliente (historial, notas, fotos antes/después, ausencias, VIP).
- [ ] 6.2 Reseñas verificadas con los modos Ocultas / Privadas / Públicas, respuesta y reporte.
- [ ] 6.3 Emails transaccionales y recordatorio de 24 h (scheduler).
- [ ] 6.4 Web Push (cliente y profesional).
- [ ] 6.5 Lista de espera con prioridad VIP; cola "sin cita" para barberías.
- *Cierre:* **qa-tester** + **security-auditor** (reseñas: XSS, spam, rate limit).

### Fase 7 — SaaS
- [ ] 7.1 Planes con límites y funciones; aplicar límites en servidor.
- [ ] 7.2 Suscripciones, prueba de 30 días, registro manual de pagos, avisos de vencimiento.
- [ ] 7.3 Gracia y modo solo lectura para salones vencidos o suspendidos.
- [ ] 7.4 Métricas del super admin y "entrar como salón" con auditoría.
- [ ] 7.5 Landing de ventas de la plataforma (qué es, precios, "Prueba gratis").
- *Cierre:* **security-auditor** (escalada a super admin, eludir límites del plan).

### Fase 8 — PWA, pulido y piloto
- [ ] 8.1 Manifest, service worker, íconos, aviso de instalación en Android y guía para iOS.
- [ ] 8.2 Revisión completa de animaciones, estados vacíos y de error, y accesibilidad.
- [ ] 8.3 Rendimiento: Lighthouse ≥ 90 en móvil en la página pública y en el flujo de reserva.
- [ ] 8.4 Auditoría completa: los 4 agentes en paralelo + OWASP ZAP baseline; corregir todo lo CRÍTICO y ALTO.
- [ ] 8.5 **Piloto** con 1–3 salones reales durante 1 semana; recoger fallos y ajustar.
- [ ] 8.6 Prueba manual en un iPhone y un Android reales (instalación, push, reserva).
- [ ] 8.7 Pendientes de UX de la Fase 1: navegación inferior por rol en móvil; diálogos como hoja inferior (Drawer) en móvil con spinner en confirmaciones; página 404/500 con marca y modo oscuro; aviso "sin conexión" con reintento; error de login como `Alert` sobre el botón; registro sin "confirmar contraseña" y con la regla visible; panel con acciones útiles para el personal y chevron en tarjetas enlace; textos de ayuda a 14 px; tema claro/oscuro guardado por cuenta (no solo por navegador).
- [ ] 8.9 Pendientes de UX de la Fase 2: "Mi salón" con cambios sin guardar visibles (o un solo "Guardar" fijo abajo) y barra de secciones fija; formularios de servicio como hoja inferior en móvil con el botón fijo; contraste del botón deshabilitado en oscuro.
- [ ] 8.8 Seguridad pendiente de la Fase 1: CSP completa (hoy solo `frame-ancestors`) y `Inertia::encryptHistory()` + limpiar historial al cerrar sesión (tablets compartidas del salón).

### Fase 9 — Lanzamiento
- [ ] 9.1 Dominio y hosting (cPanel con PHP 8.3 o VPS), HTTPS, cron, colas.
- [ ] 9.2 Copias de seguridad diarias de la base y de las imágenes, con prueba de restauración.
- [ ] 9.3 Monitoreo de errores (Sentry, plan gratis) y uptime.
- [ ] 9.4 Términos, privacidad, Libro de Reclamaciones.
- [ ] 9.5 Restringir la clave de Google Maps por dominio; `APP_DEBUG=false`; cabeceras de seguridad.

### Fase 10 — Después del MVP (priorizar con datos del piloto)
- App Flutter (tiendas, push nativo).
- Pasarela de pago para señas y suscripciones (Mercado Pago, Izipay o Culqi) detrás de `PaymentProvider`.
- Recordatorios automáticos por la API de WhatsApp como extra de pago.
- Boletas y facturas SUNAT (vía un PSE como Nubefact).
- Inventario y venta de productos; fidelización (puntos, paquetes, gift cards); multisede.
- Directorio o marketplace de salones (cuando haya masa crítica).

---

## 8. Proceso de calidad (agentes en `.claude/agents/`)
| Agente | Cuándo | Busca |
|---|---|---|
| `qa-tester` | Al terminar cada paso | Que cada criterio funcione por rol; tests Pest y Playwright, móvil y escritorio, claro y oscuro |
| `booking-breaker` | Cada vez que se toca la agenda | Dobles reservas, concurrencia, zonas horarias, fugas de horarios VIP |
| `security-auditor` | Auth, roles, subidas, dinero, consultas por salón; cierre de fase | Fugas entre salones, IDOR, escalada, XSS, SQLi, dependencias |
| `ux-reviewer` | Cada pantalla | Intuitivo, 360 px, contraste, animaciones, lenguaje neutro |

**Definición de terminado:** tests verdes, phpstan y lint limpios, agentes aplicables sin hallazgos
CRÍTICOS ni ALTOS abiertos, y la casilla marcada en este plan.

## 9. Riesgos
| Riesgo | Mitigación |
|---|---|
| Fuga de datos entre salones | Global scope + Policies + test de aislamiento por recurso + auditor |
| Doble reserva | Función pura + bloqueo de fila + test concurrente |
| Competidor grande (AgendaPro) | Precio menor, WhatsApp y Yape nativos, reseñas con interruptor, foco en 1–6 profesionales y fuera de Lima |
| Alcance que crece sin fin | Este plan + "Ideas para después" + registro de cambios |
| Costo de WhatsApp API | Fuera del MVP; solo como extra de pago |
| iOS limita la PWA | Guía de instalación; Flutter en la fase 10 |
| Hosting compartido lento | Diseñar colas y caché desde el inicio; migrar a VPS cuando haga falta |

## 10. Decisiones abiertas (responder antes de la fase indicada)
| # | Pregunta | Antes de | Supuesto actual |
|---|---|---|---|
| D1 | Nombre comercial y dominio de la plataforma | Fase 9 | **Respondido 2026-10-04:** aún no hay; se usa "peluWeb" provisional (se cambia con `APP_NAME`). Decidir antes del lanzamiento |
| D2 | País y moneda iniciales | — | **Cerrado 2026-10-04:** Perú, PEN, America/Lima por defecto (cada salón guarda su zona y moneda) |
| D3 | Precios finales de los planes | Fase 7 | Ver [mercado.md](mercado.md): S/ 39 / 89 / 169 |
| D4 | ¿Hay un salón amigo para el piloto? | Fase 8 | Sí (el del proyecto anterior) |
| D5 | ¿Se reutiliza algo del proyecto Flutter anterior (`StudioProjects/peluqueria`)? | Fase 10 | Solo las reglas de dominio, ya pasadas a `booking-domain` |
| D6 | ¿Subir el código a un repositorio privado de GitHub? (activa el CI) | Fase 9 | Pendiente; el CI ya está listo para cuando exista |

## 11. Registro de cambios del plan
| Fecha | Cambio | Motivo |
|---|---|---|
| 2026-10-04 | Versión inicial | Investigación de mercado, stack y catálogo |
| 2026-10-04 | Entorno: **sin Laragon**. PHP 8.4 aparte en `C:\php84` + el MySQL 9.6 que ya estaba instalado (CI y producción en MySQL 8.4). Tests contra MySQL en vez de SQLite. Color de marca por defecto `#8F5E45` (el `#B4846C` no pasaba contraste AA) | Pedido del usuario: usar XAMPP/MySQL o instalar aparte, nada desconocido |
| 2026-10-04 | Herramientas de orden y limpieza: **Laravel Boost** (guías y skills oficiales de Laravel 13, Inertia, React, Pest, Tailwind, Wayfinder, solo para Claude Code y sin Laravel Cloud) + skill propia `clean-code` (estructura de carpetas, tamaños máximos, nombres, checklist) + `Model::shouldBeStrict()` en desarrollo. `.claude/` y `CLAUDE.md` ahora sí van a git. Lanzador `dev.cmd` | Pedido del usuario: código ordenado y limpio; `composer dev` fallaba con el PHP de XAMPP |
| 2026-10-04 | Roles por salón con **tabla propia** en vez de spatie/laravel-permission. Pruebas de navegador con **Playwright Test** en vez de pest-plugin-browser. Los salones se crean por autoservicio (el panel no los da de alta) | spatie con "teams" depende de un id global por petición, fácil de olvidar en colas (fuga entre salones); el plugin de Pest se cuelga en Windows; el modelo SaaS es autoservicio con prueba gratis |

## Ideas para después (no entran sin pasar por §11)
- Pantalla de turnos para TV en el local · Programa de referidos entre salones · Recomendación de
  estilo con IA a partir de una foto · Cumpleaños con descuento automático · Integración con
  Reserve with Google.
