# Requisitos de producto para cumplir

> **Borrador — no es asesoría legal.** Debe revisarlo un abogado colegiado en Perú.
> Versión 0.1 — 2026-10-10. Son requisitos, no diseño ni código. Antes de construir, pasan por
> `docs/PLAN.md` (§11) como detalle del paso 9.4.

Prioridad: **P** = bloquea el piloto con salones reales (8.5) · **L** = bloquea el lanzamiento
(Fase 9) · **D** = después.

## P — Bloquea el piloto

| # | Requisito | Por qué |
|---|---|---|
| P1 | **Páginas legales publicadas** y enlazadas en el pie de todas las páginas (también registro, inicio de sesión, página del salón, reserva y paneles): Términos para clientes, Términos para salones, Privacidad, Cookies, Libro de Reclamaciones. Cada una muestra versión y fecha. El pie muestra `[RAZÓN SOCIAL]`, `[RUC]` y `[CORREO DE CONTACTO]`, cargados por configuración (no en el repositorio) | Deber de informar; identificación del proveedor |
| P2 | **Casilla de aceptación en el registro** con correo (casilla 1 de `consentimientos.md`), validada en el servidor, y **registro de aceptación**: cuenta, documento, versión, huella del texto, fecha UTC, IP, navegador y vía. No editable | Prueba de aceptación e información |
| P3 | **Pantalla de aceptación pendiente**: toda cuenta sin aceptación registrada de la versión vigente (alta con Google, invitación, cuentas anteriores, nueva versión) la ve al entrar y no puede seguir sin aceptar; sí puede cerrar sesión y borrar su cuenta. Las versiones anteriores de cada documento siguen consultables | El alta con Google hoy crea la cuenta sin mostrar nada |
| P4 | **Alta del salón** con la casilla 5 (Términos para salones + encargo de datos) y su registro, ligado al salón | Contrato con el salón y contrato de encargo |
| P5 | **Mapa de Google al pulsar**: en la página pública del salón y en la tarjeta de mapa de la reserva, mostrar dirección y botón "Ver mapa" con su aviso; el iframe se carga solo al pulsar. "Cómo llegar" sigue siendo un enlace | Evita cookies de terceros sin acción de la persona y, con eso, el banner |
| P6 | **Libro de Reclamaciones virtual de la plataforma** según `libro-de-reclamaciones.md` §2: formulario sin sesión, correlativo, copia por correo, bandeja con plazo de 15 días hábiles, conservación | Ley 32495; art. 24 del Código |
| P7 | **Libro del salón visible**: campo de enlace en "Mi salón", línea fija en el pie de su página pública y punto en la lista de puesta en marcha. Campos nuevos (opcionales, `nullable`): nombre o razón social, RUC y correo del salón | Cada salón responde por su libro; identifica quién presta el servicio |
| P8 | **Paso "Confirmar" de la reserva**: mostrar quién presta el servicio, el aviso 6, y la política de seña y cancelación del salón (textos de `textos-para-salones.md`) con el monto y si la seña se devuelve. Guardar con la reserva la regla mostrada (horas de cancelación, si se devuelve, plazo de devolución) | Información previa; la regla aplicable es la que vio la persona |
| P9 | **Textos de ayuda** 8, 9, 10 y 12 de `consentimientos.md` (nota, foto de referencia, teléfono, cliente sin cuenta) | Minimizar datos; imagen de terceros |
| P10 | **Borrar la cuenta, completo y coherente**: borrar también el archivo del avatar, las filas de sesiones y los accesos de la app; decidir qué pasa si quien borra es el único dueño de un salón (bloquear hasta cerrar o transferir el salón); la pantalla dice, antes de confirmar, que las fichas de los salones no se borran y cómo pedirlo | Hoy el archivo del avatar queda huérfano y un salón puede quedar sin dueño |
| P11 | **Canal y procedimientos** (no es código): buzón `[CORREO DE PRIVACIDAD]`; una hoja interna de cómo se atiende un pedido de acceso, corrección o borrado y en qué plazo; una hoja de qué hacer ante un incidente (a quién avisar y cómo en 48 horas: ANPD, afectados y salones) | Reglamento vigente de la Ley 29733 |
| P12 | **Elegir hosting y correo** y llenar los huecos de todos los textos, incluido el país donde quedan los datos | Sin eso no se puede declarar el flujo transfronterizo |
| P13 | **Acuerdo con cada salón del piloto** (no es código): aceptación registrada de los Términos para salones en versión piloto, y su aviso impreso para clientes sin cuenta | Los datos del piloto son reales |
| P14 | **Solo datos reales de quien aceptó**: no cargar al piloto listas de clientes anteriores del salón sin revisar con el abogado | Base legal de datos importados |

## L — Bloquea el lanzamiento

| # | Requisito | Por qué |
|---|---|---|
| L1 | **Descargar mis datos**: desde el perfil, un archivo con la cuenta y las reservas propias | Acceso y portabilidad |
| L2 | **Exportar y cerrar el salón**: exportar clientes y reservas (hoy solo movimientos en CSV); cierre del salón con plazo de exportación y borrado o anonimización posterior, incluidas sus imágenes | Fin del encargo |
| L3 | **Conservación automática**, con plazos escritos: foto de referencia tras la cita; invitaciones vencidas; sesiones caducadas; fichas y reservas antiguas (anonimizar tras el plazo que decida el salón dentro de un máximo); cuentas sin verificar | Principio de conservación limitada |
| L4 | **Foto de referencia fuera del disco público**: servirla solo a quien puede ver esa reserva | Imagen de personas, quizá menores |
| L5 | **Consentimiento de publicidad aparte**, por salón y por canal, con fecha de alta y de baja, visible en la ficha del cliente; baja en cada mensaje; "PUBLICIDAD" en el asunto de los correos. Sin esto no se construye ningún envío promocional ni botón de "enviar promoción por WhatsApp" a clientes | Art. 58.1.e del Código (Ley 32323); Ley 28493 |
| L6 | **Promociones** (paso 6.7): las seis reglas de `textos-para-salones.md` §5, y la letra chica en toda pieza compartible | Art. 14 del Código; publicidad engañosa |
| L7 | **Ficha del cliente** (paso 6.1). *Hecho (2026-10-10)*: tres campos (preferencias, fórmula y técnica, alergias y cuidado); el de alergias solo se guarda si el personal declara que la persona firmó la autorización 17 impresa; registro de autorizaciones que solo crece (dada y retirada, quién, cuándo, versión del texto); retirar borra el texto; lo ven solo el dueño y quien atiende a esa persona; fuera de listados y exportaciones. *Falta*: que la persona vea lo anotado y dé o retire la autorización desde su cuenta; casilla "es menor de edad" (firma de madre, padre o tutor); borrado automático a los 24 meses de la última cita; que borrar la cuenta retire la autorización; registro de accesos | Datos sensibles |
| L8 | **Reseñas** (paso 6.2): aviso 16; nombre de pila solamente; reportar; el salón responde pero no borra a su gusto (ya previsto); retirar la reseña al borrar la cuenta o volverla anónima | Imagen, honor, consumidor |
| L9 | **Galería y fotos del equipo**: declaración 11 la primera vez; botón "Reportar esta foto" en la página pública y retiro rápido | Derecho a la propia imagen |
| L10 | **Edad**: declaración en el registro según lo que confirme el abogado; no pedir fecha de nacimiento si no hace falta | Menores |
| L11 | **Libro de Reclamaciones por salón** dentro de la plataforma, si el abogado lo confirma | Reglamento del Libro |
| L12 | **Aviso al equipo** (aviso 4) al aceptar la invitación; el personal ve sus propias comisiones y ausencias | Información al trabajador |
| L13 | **Aviso imprimible para el local** (texto 2 de `textos-para-salones.md`), descargable junto al QR | Clientes sin cuenta |
| L14 | **Registros y monitoreo**: rotación de registros (14 días); antes de activar Sentry, quitar datos personales de los reportes, sin grabación de sesiones, y añadirlo a la política | Minimización; nuevo proveedor |
| L15 | **Avisos push** (paso 6.5): pedir el permiso solo tras una acción de la persona; solo avisos de citas | Consentimiento |
| L16 | **Cookies en producción**: sesión solo por HTTPS; atributo `Secure` en `appearance` y `sidebar_state`; revisar la duración de "Recuérdame" y escribirla en la política | Seguridad; exactitud de la política |
| L17 | **Trámites ante la ANPD** (no es código): inscripción de bancos de datos y lo que corresponda por flujo transfronterizo; documento de seguridad con fecha; evaluar oficial de datos | Reglamento vigente |
| L18 | **"Entrar como salón"** (paso 7.4): registro de cada ingreso visible para el dueño; decirlo en los términos | Acceso del encargado |
| L19 | **Suscripción** (Fase 7): precios con IGV visibles antes de pagar; avisos de vencimiento; aviso de cambio de precio con 30 días; constancia de cada pago; solo lectura sin borrar datos | Información y cláusulas abusivas |
| L20 | **Revisión de un abogado** de todos los textos y de la lista del `README.md` | El agente no lo sustituye |

## D — Después

| # | Requisito |
|---|---|
| D1 | Pasarela de pago: nuevo proveedor, nuevas cookies, reglas de devolución; rehacer inventario y política |
| D2 | API de WhatsApp para recordatorios: consentimiento y plantillas |
| D3 | App Flutter: fichas de privacidad de las tiendas, borrado de cuenta desde la app |
| D4 | Comprobantes electrónicos (SUNAT) |
| D5 | Oficial de Datos Personales: revisar la obligación antes de la fecha que corresponda al tamaño de la empresa |
| D6 | Reactivación de clientes y cumpleaños: exigen L5 y pedir la fecha de nacimiento con finalidad clara |

## Estado de la verificación de normas (2026-10-10)

| Tema | Qué se confirmó | Fuente | Estado |
|---|---|---|---|
| Reglamento de la Ley 29733 | D.S. 016-2024-JUS, publicado el 30-11-2024, vigente desde el 30-03-2025; deroga el D.S. 003-2013-JUS | Garrigues, Cuatrecasas, CMS | Confirmado en fuentes secundarias coincidentes; texto oficial no abierto. Una fuente dice 29-03-2025 |
| Incidentes | Aviso a la ANPD en 48 horas desde que se conoce, y a los afectados; registro interno | Garrigues, CMS | Confirmado en secundarias |
| Oficial de datos | Obligatorio para quien trata grandes volúmenes o datos sensibles como actividad principal; cronograma por ventas: más de 2300 UIT 30-11-2025, 1700–2300 UIT 30-11-2026, 150–1700 UIT 30-11-2027, hasta 150 UIT 30-11-2028; directiva R.D. 100-2025-JUS-DGTAIPD | El Peruano (27-01-2026), Cuatrecasas | Confirmado; falta decidir si aplica a la plataforma |
| Portabilidad | Nuevo derecho, exigible desde el 30-09-2025 | Cuatrecasas | Confirmado en una secundaria |
| Publicidad y datos | Oposición o revocación para publicidad: atender en 10 días | Garrigues, CMS | Confirmado en secundarias |
| Aplicación a empresas pequeñas | No se halló excepción por tamaño salvo el cronograma del oficial | — | **Sin verificar** |
| Menores | 14 años como umbral para consentir | Carta gremial sobre un proyecto | **Sin verificar** en el reglamento vigente |
| Registro de bancos y flujo transfronterizo | Solo hay citas del reglamento de 2013 | — | **Sin verificar** |
| Contrato de encargo | Contenido mínimo | — | **Sin verificar** |
| Plazos de derechos (acceso, rectificación, cancelación, oposición) | — | — | **Sin verificar** (de memoria, del reglamento anterior: 20 días hábiles el acceso y 10 los demás) |
| Cookies | No se halló norma ni guía oficial peruana específica | — | **Sin verificar** que no exista |
| Libro de Reclamaciones: plazo | 15 días hábiles improrrogables (Ley 31435, D.S. 101-2022-PCM) | El Peruano | Confirmado |
| Libro: plataformas | Ley 32495 (11-11-2025), enlace visible y permanente | Resúmenes de la ley | Confirmado; reglamento en proyecto (R.M. 244-2026-PCM, 18-07-2026), **sin verificar** si se aprobó |
| Libro: conservación 2 años, campos de la hoja, aviso | — | Cita del reglamento; memoria | **Sin verificar** en el texto vigente |
| Promociones | Art. 14 del Código: duración y cantidad mínima de unidades; restricciones en el anuncio o en fuente gratuita | Copia del Código alojada por una entidad estatal | Texto leído; **sin verificar** que no haya cambiado |
| Precio total | Art. 4 del Código | Igual | Igual |
| Cláusulas abusivas | Arts. 47, 50 y 51 del Código | Igual | Igual |
| Comunicaciones comerciales | Ley 32323 (09-05-2025) modifica el art. 58.1.e: consentimiento previo y expreso, revocable; infracción muy grave | El Peruano | Confirmado. Alcance sobre WhatsApp entre salón y cliente: **sin verificar** |
| Correo comercial | Ley 28493: "PUBLICIDAD" en el asunto, baja | Resolución de 2011 citada | **Sin verificar** el texto vigente |
| Identificación del proveedor en línea | La obligación de poner RUC y razón social en la publicidad (D.L. 1524) fue derogada por la Ley 32080 (02-07-2024). El D.L. 1729 (12-02-2026) exige mecanismos de reclamo accesibles en canales digitales | Garrigues; resumen de un estudio | Confirmado en secundarias. No se halló una lista legal vigente de datos obligatorios del proveedor en su web: **sin verificar**. Se recomienda mostrarlos igual (el Libro los exige) |
