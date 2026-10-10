# Inventario de datos personales, terceros y cookies

> **Borrador — no es asesoría legal.** Debe revisarlo un abogado colegiado en Perú.
> Versión 0.1 — 2026-10-10. Leído del código de la rama `fase-5-caja`; rehacer al cerrar cada fase.

Roles: **R** = la plataforma es responsable; **E** = la plataforma es encargada y el salón es el
responsable.

## 1. Datos personales que se recogen hoy

| # | Dato | De quién | Dónde entra | Dónde se guarda | Para qué | Rol | Quién lo ve | Cuánto dura hoy |
|---|---|---|---|---|---|---|---|---|
| 1 | Nombre, correo, contraseña (cifrada con hash) | Toda persona con cuenta | Registro con correo | `users` | Crear y proteger la cuenta | R | La persona; super admin (panel Filament) | Hasta que borra la cuenta |
| 2 | Nombre, correo, identificador de Google | Quien entra con Google | "Continuar con Google" | `users.google_id` | Iniciar sesión sin contraseña | R | Igual | Igual. No se toma la foto de Google |
| 3 | Teléfono (WhatsApp) | Toda cuenta (opcional) | Perfil | `users.phone` | Que el salón pueda escribirle | R | La persona; super admin; pasa a la ficha del salón al reservar | Hasta que lo quita o borra la cuenta |
| 4 | Foto de perfil | Toda cuenta (opcional) | Perfil | Disco público, WebP sin EXIF | Identificarse | R | Quien vea su perfil | **El archivo no se borra al borrar la cuenta** (no hay rutina que lo haga) |
| 5 | Verificación en dos pasos, llaves de acceso (passkeys) | Quien las activa | Seguridad | `users`, `passkeys` | Seguridad | R | Nadie (secretos) | Hasta desactivar |
| 6 | IP, navegador, última actividad | Toda visita con sesión | Automático | `sessions` | Mantener la sesión, seguridad | R | Solo sistema | 120 min sin actividad; las filas viejas se limpian al azar |
| 7 | Correo en recuperación de contraseña | Quien la pide | "Olvidé mi contraseña" | `password_reset_tokens` | Recuperar acceso | R | Solo sistema | El enlace vale 60 min |
| 8 | Tokens de la API (nombre del dispositivo, último uso) | Quien use la app móvil | `POST /api/v1/login` | `personal_access_tokens` (hash) | Sesión de la app | R | Solo sistema | 90 días; máximo 10 por cuenta; se borran al cambiar contraseña |
| 9 | Ficha de cliente: nombre, teléfono, VIP, ausencias | Clientes del salón, con o sin cuenta | Al reservar en línea, o la crea el personal | `salon_clients` | Agenda y trato del salón | **E** | Dueño y personal con permiso | **Indefinido. Sobrevive a que la persona borre su cuenta** (queda sin vínculo a la cuenta) |
| 10 | Reserva: servicios, profesional, fecha, precio, seña, estado, historial de cambios | Clientes | Asistente de reserva, agenda | `bookings`, `booking_services`, `booking_events` | Prestar el servicio; caja | **E** | Cliente (las suyas), dueño, personal | Indefinido |
| 11 | Nota del cliente (texto libre, 255) | Clientes | Paso "confirmar" | `bookings.client_note` | Indicaciones para la cita | **E** | Dueño y personal | Indefinido. **Puede contener datos de salud** (alergias) |
| 12 | Foto de referencia "Mi estilo" | Clientes (puede mostrar su cara o la de otra persona) | Mis reservas | Disco **público**, ruta no adivinable, WebP sin EXIF | Mostrar lo que quiere | **E** | Dueño y personal; cualquiera que tenga la URL | Hasta que el cliente la cambia o la quita. No se borra sola tras la cita |
| 13 | Miembro del equipo: rol y permisos | Dueños y personal | Invitación aceptada | `salon_user` | Control de acceso | R (cuenta) / E (rol) | Dueño | Hasta que se le quita |
| 14 | Invitación: correo de la persona invitada, rol | Futuro personal (aún sin cuenta) | El dueño la escribe | `salon_invitations` (token con hash) | Invitar | **E** | Dueño | Vale 7 días; la fila no se borra |
| 15 | Perfil profesional: nombre, cargo, foto, horario, ausencias con motivo | Personal | Dueño o la propia persona | `staff`, `staff_schedules`, `staff_time_off` | Agenda y página pública | **E** | Nombre, cargo y foto son **públicos**; el resto, el salón | Hasta que se elimina (borrado lógico) |
| 16 | Comisiones: porcentaje, montos, liquidaciones | Personal | Dueño | `staff`, `service_staff`, `commission_settlements`, `bookings.commission_cents` | Pagar comisiones | **E** | Dueño y quien tenga permiso de caja | Indefinido |
| 17 | Cobros y señas: monto, método, quién registró | Clientes y personal | Caja | `payments` | Contabilidad del salón | **E** | Dueño, permiso de caja | Indefinido. No se guardan tarjetas ni cuentas del cliente |
| 18 | Gastos y foto del comprobante (puede traer RUC, nombres, cuentas de terceros) | Proveedores del salón | Caja | `expenses`; foto en disco **privado** | Contabilidad | **E** | Dueño, permiso de caja | Indefinido (borrado lógico) |
| 19 | Apertura y cierre de caja: quién, cuándo, montos | Personal | Caja | `cash_sessions` | Cuadre | **E** | Dueño, permiso de caja | Indefinido |
| 20 | Datos del salón: nombre, teléfono, Instagram, dirección, coordenadas, datos de Yape/Plin para la seña | Dueño (si es persona natural, son datos personales) | Mi salón | `salons` | Página pública y cobro de señas | R | **Públicos** | Hasta que se cambian |
| 21 | Galería del salón (puede mostrar personas) | Clientes o modelos fotografiados | Dueño | `salon_photos`, disco público | Mostrar trabajos | **E** | **Público** | Hasta que el dueño la quita |
| 22 | Registros de errores del servidor | Cualquiera | Automático | `storage/logs` | Depurar | R | Quien administra el servidor | Sin rotación con la configuración de ejemplo (`LOG_STACK=single`) |

No se recoge hoy: documento de identidad, fecha de nacimiento, dirección del cliente, datos de
tarjeta, geolocalización del visitante, analítica ni perfiles publicitarios. No hay reseñas,
mensajes automáticos, Web Push ni lista de espera todavía (fases 6 y 7): cuando existan, se amplía
esta tabla **antes** de publicarlos.

## 2. Terceros que reciben datos

| Tercero | Cuándo | Qué recibe | ¿Hace falta para el servicio? | Observación |
|---|---|---|---|---|
| Google (inicio de sesión) | Solo si la persona pulsa "Continuar con Google" | Que esa persona entra a la plataforma; devuelve nombre, correo e identificador | Sí, lo elige la persona | Google actúa con sus propias condiciones |
| Google Maps (iframe `maps.google.com`) | **Al abrir la página pública de un salón con ubicación y la tarjeta de mapa de una reserva, sin acción de la persona** | IP, navegador, página de origen; Google puede usar cookies propias dentro del marco | **No** | Único punto con posibles cookies de terceros. Ver `politica-cookies.md` y requisito P5 |
| Google Maps (enlace "Cómo llegar") | Al pulsar | Las coordenadas del salón | Lo elige la persona | Sale del sitio |
| OpenStreetMap (teselas de `tile.openstreetmap.org`) | Solo en "Mi salón → Ubicación" (dueño fijando el pin) | IP y navegador del dueño | Sí, para esa pantalla | No se usa en páginas públicas |
| WhatsApp (`wa.me`) | Al pulsar un botón de WhatsApp | Nada desde el servidor: se abre WhatsApp con un mensaje ya escrito, que la persona decide enviar | Lo elige la persona | El mensaje de seña incluye código de reserva y monto |
| Proveedor de correo | Verificación, recuperación de contraseña, invitaciones | Correo de destino y contenido | Sí | `[PROVEEDOR DE CORREO Y PAÍS]` **sin decidir** (hoy `MAIL_MAILER=log`) |
| Hosting y copias de seguridad | Siempre | Toda la base y las imágenes | Sí | `[PROVEEDOR DE HOSTING Y PAÍS]` **sin decidir**. Si está fuera de Perú, es flujo transfronterizo |
| Sentry (paso 9.3) | **No existe todavía** | Errores, que pueden arrastrar datos personales | No | Antes de activarlo: quitar datos personales de los reportes y añadirlo aquí |

Las fuentes tipográficas **están alojadas en el propio sitio** (se descargan al compilar; en el
resultado no hay llamadas a servidores de fuentes). No hay analítica, píxeles ni publicidad.

## 3. Cookies

| Nombre | Quién la pone | Qué guarda | Duración | Tipo | ¿Necesaria? |
|---|---|---|---|---|---|
| `peluweb-session` (el nombre sale de `APP_NAME`) | Servidor | Identificador de sesión (cifrado, `HttpOnly`, `SameSite=Lax`) | 120 min sin actividad | Propia, técnica | Sí |
| `XSRF-TOKEN` | Servidor | Token contra envíos falsificados | Igual que la sesión | Propia, seguridad | Sí |
| `remember_web_…` | Servidor, **solo si se marca "Recuérdame"** | Token para no volver a escribir la contraseña | Larga (valor por defecto del framework, unos 400 días — sin verificar en este proyecto) | Propia, pedida por la persona | Sí (función solicitada) |
| `appearance` | Navegador, en la primera visita | `system`, `light` o `dark` | 365 días | Propia, preferencia | Sí (evita el destello al cargar). No identifica a nadie |
| `sidebar_state` | Navegador, al plegar el menú del panel | `true` o `false` | 7 días | Propia, preferencia | Sí (función solicitada). Solo en los paneles |
| Cookies de Google | Google, dentro del iframe del mapa y al entrar con Google | Las que Google decida | Las que Google decida | **De terceros** | **No** (las del mapa) |

En producción falta fijar `SESSION_SECURE_COOKIE=true` y dar el atributo `Secure` a `appearance`
y `sidebar_state` (requisito L16).

## 4. Almacenamiento local del navegador

| Clave | Dónde | Qué guarda | Duración | ¿Necesaria? |
|---|---|---|---|---|
| `appearance` | `localStorage` | Tema elegido | Hasta que se borra | Sí, preferencia |
| `peluweb:reserva:{salón}` | `sessionStorage` | Lo elegido en el asistente de reserva (servicios, profesional, hora, nota) para recuperarlo tras iniciar sesión | Hasta cerrar la pestaña; el asistente lo descarta a los 30 min | Sí |
| Marca de celebración por reserva | `sessionStorage` | Que ya se mostró la animación de "reserva creada" | Un solo uso | Sí |
| Panel del super admin (Filament) | `localStorage` | Preferencias del panel (**no revisado en detalle**) | — | Solo lo usa el super admin |

## 5. Conclusión

Todo lo propio es técnico, de seguridad o de preferencia pedida por la persona: **no hace falta
banner de consentimiento, basta el aviso informativo y la política de cookies**. La única
excepción es el iframe de Google Maps; con el cambio del requisito P5 (el mapa se carga cuando la
persona pulsa "Ver mapa") tampoco hace falta banner. Si algún día se añade analítica, píxeles,
mapas o videos incrustados que carguen solos, o repetición de sesiones, entonces sí.

## 6. Brechas y riesgo

| Brecha | Quién podría reclamar y dónde | Riesgo para un negocio pequeño |
|---|---|---|
| Sin política de privacidad ni aviso al recoger datos | Cualquier persona, ante la ANPD | Alto: es lo primero que se mira en una fiscalización o denuncia |
| Sin términos aceptados ni prueba | Salón o cliente en un conflicto; Indecopi | Alto: sin contrato no hay límites ni reglas que oponer |
| Sin Libro de Reclamaciones virtual | Cualquier consumidor o fiscalización de oficio, ante Indecopi | Alto: infracción fácil de comprobar con una captura |
| Ficha del salón sobrevive al borrado de la cuenta sin que se diga | La persona, ante la ANPD (derecho de cancelación) | Medio: se resuelve diciéndolo y dando el canal hacia el salón |
| Foto de referencia en disco público y sin caducidad | La persona fotografiada | Medio: imagen de personas, quizá menores |
| Nota libre con posibles datos de salud | La persona, ante la ANPD | Medio; sube a alto cuando exista la ficha con alergias (6.1) |
| Mapa de Google sin acción previa | La persona, ante la ANPD | Bajo-medio; arreglo barato |
| Hosting y correo sin definir (posible flujo transfronterizo sin declarar) | ANPD | Medio |
| Sin procedimiento de incidentes (aviso en 48 horas) | ANPD, tras una fuga | Alto si ocurre; preparar cuesta poco |
| Comisiones y ausencias del personal sin aviso al personal | El trabajador, ante la ANPD o Sunafil | Bajo-medio; lo cubre el aviso al equipo |
