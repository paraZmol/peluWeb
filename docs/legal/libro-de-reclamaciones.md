# Libro de Reclamaciones

> **Borrador — no es asesoría legal.** Debe revisarlo un abogado colegiado en Perú antes de
> publicarse. Versión 0.1 — 2026-10-10.

## 1. Quién está obligado

| Quién | ¿Obligado? | Fundamento | Estado |
|---|---|---|---|
| **La plataforma** | Sí, virtual | Ley 32495 (11-11-2025): toda plataforma digital de comercio electrónico debe tener Libro de Reclamaciones y exhibir de forma permanente, visible y accesible el enlace. Además presta un servicio en línea a los salones | Verificado en resúmenes de la ley. Falta que un abogado confirme que la plataforma entra en la definición |
| **Cada salón** | Sí, el suyo | Art. 150 del Código: todo establecimiento abierto al público. El reglamento (D.S. 011-2011-PCM y modificatorias) añade que quien además usa un medio virtual para ofrecer sus servicios debe tener un libro virtual accesible **en ese mismo medio** | El primer punto, verificado (nota de El Peruano del 02-12-2025). El segundo, leído en una cita del reglamento: confirmar que la página del salón en la plataforma cuenta como su "medio virtual" |

El reglamento se está modificando para adecuarlo a la Ley 32495: el proyecto se publicó el
18-07-2026 (R.M. 244-2026-PCM). Según un resumen de un estudio de abogados, pide que el enlace
lleve **directo al formulario** y que no exija iniciar sesión, registrarse ni acreditar una
compra. **No se pudo confirmar si ya fue aprobado**: se diseña cumpliendo eso, que es lo más
exigente.

Lo que no cambia: el plazo de respuesta es de **15 días hábiles improrrogables** (art. 24 del
Código, Ley 31435). Las hojas se conservan **2 años** (dato del reglamento, **sin verificar** en
el texto vigente).

## 2. Qué construir para la plataforma (requisito P6)

1. **Página pública `/libro-de-reclamaciones`**, sin iniciar sesión, enlazada en el pie de
   **todas** las páginas (portada, registro, inicio de sesión, página del salón, reserva, Mis
   reservas, paneles) con el texto "Libro de Reclamaciones" y su ícono.
2. **Formulario** con los campos de la Hoja de Reclamación (lista de memoria, **sin verificar**
   contra el anexo vigente del reglamento; se puede contrastar con el formato que genera la
   herramienta gratuita de Indecopi "Tu Libro de Reclamaciones"):
   - Fecha y **número correlativo** por año (lo pone el sistema).
   - Proveedor: `[RAZÓN SOCIAL]`, `[RUC]`, `[DOMICILIO]` (fijos, visibles).
   - Quien reclama: nombre, domicilio, documento (DNI o CE) y número, teléfono, correo; si es
     menor de edad, nombre de su madre, padre o tutor.
   - Sobre qué: producto o servicio, descripción, monto reclamado (opcional).
   - Tipo: **Reclamo** (disconformidad con el servicio) o **Queja** (malestar con la atención, no
     con el servicio).
   - Detalle y pedido concreto.
   - Casilla 19 de `consentimientos.md`.
3. **Constancia inmediata**: pantalla con el número y copia completa al correo de quien reclama.
4. **Bandeja en el panel del super admin**: lista, fecha límite calculada en días hábiles (15),
   alerta cuando faltan 5 y 2, campo de respuesta y envío de la respuesta por correo, con fecha.
   La hoja no se puede editar ni borrar.
5. **Conservación**: al menos 2 años; exportable para entregarla a Indecopi si la pide.
6. **Protección contra abuso**: límite de envíos por IP; sin captcha de terceros (añadiría
   cookies ajenas).
7. Este formulario pide documento y domicilio, que el resto del producto no pide: usarlos solo
   para el reclamo y decirlo.

## 3. Qué construir para los salones

**Para el piloto (requisito P7):** en "Mi salón", un campo "Enlace a tu Libro de Reclamaciones"
(opcional) y una línea fija en el pie de la página pública del salón:

- Con enlace: "Libro de Reclamaciones de {Salón}" → abre el enlace.
- Sin enlace: "Libro de Reclamaciones de {Salón}: disponible en su local, {dirección}."

En la lista de puesta en marcha del salón, un punto: "Ten tu Libro de Reclamaciones. Indecopi
ofrece una herramienta gratuita para generarlo."

**Para el lanzamiento (requisito L11), si el abogado lo confirma:** un Libro virtual por salón
dentro de la plataforma, con el mismo formulario pero con los datos del salón como proveedor
(razón social o nombre, RUC, dirección: campos nuevos en "Mi salón"), bandeja para el dueño con
el plazo de 15 días hábiles y aviso por correo. El responsable de responder es el salón; la
plataforma solo da la herramienta.

## 4. Textos

**Enlace del pie:**

> Libro de Reclamaciones

**Encabezado de la página de la plataforma:**

> **Libro de Reclamaciones**
> `[RAZÓN SOCIAL]` — RUC `[RUC]` — `[DOMICILIO]`
>
> Conforme al Código de Protección y Defensa del Consumidor, este sitio cuenta con un Libro de
> Reclamaciones a tu disposición.
>
> Aquí reclamas sobre **la plataforma** (tu cuenta, el funcionamiento de las reservas, tu
> suscripción). Si tu reclamo es sobre **el servicio de un salón** (la atención, el precio, una
> seña), preséntalo en el Libro de Reclamaciones de ese salón.

**Ayuda de los tipos:**

> **Reclamo:** no estás conforme con el servicio.
> **Queja:** no estás conforme con la atención, aunque el servicio haya funcionado.

**Al enviar:**

> Recibimos tu {reclamo | queja} **N.º {correlativo}**. Te enviamos una copia a {correo}.
> Te responderemos en un máximo de 15 días hábiles.
>
> Presentar un reclamo no impide acudir a otras vías ni es requisito para denunciar ante Indecopi.

**Aviso de privacidad del formulario:**

> Usamos estos datos solo para atender tu reclamo y los guardamos el tiempo que exige la ley.

## 5. Pendiente de abogado

- Texto y formato exactos del aviso y de la hoja con el reglamento que esté vigente al publicar.
- Si la plataforma debe además recibir y trasladar los reclamos dirigidos a los salones.
- Si el domicilio y el documento siguen siendo campos obligatorios.
