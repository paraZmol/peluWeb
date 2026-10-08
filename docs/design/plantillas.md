# Plantillas de la página del salón — qué construir

Spec del sistema: skill `ui-system` (tokens, skins, variantes, fuentes). Oficio: skill `design-craft`.
Maquetas: `docs/design/propuestas/a-nocturna/`, `b-luminosa/`, `c-vibrante/` y `actual/` (editorial).

Las cuatro plantillas sirven para **cualquier negocio**: barbería, peluquería de mujeres, unisex,
uñas y spa. Cambian solo el color de marca y las fotos. Nada de motivos de un solo público
(navajas, bigotes, postes de barbero, tijeras en el pin del mapa): íconos lucide neutros
(`MapPin`, `Clock`, `Sparkles`, `CalendarCheck`) o el logo/monograma del salón.

**Plantilla por defecto** (servidor, al crear el salón): barbería → nocturna · unisex → nocturna ·
salón de belleza, uñas y spa → luminosa. El dueño la cambia en Mi salón.

## 0. Reglas para construir
- Una base de componentes; **las plantillas no se bifurcan en páginas**. Cada pantalla envuelve en
  `<BrandTheme brand theme>`; los componentes leen `useSalonTheme().layout` para las 4 ranuras con
  variantes (`hero`, `serviceRow`, `staffCard`, `highlight`) y usan tokens + skins para todo lo demás.
- Una variante = un componente ≤ 150 líneas en su carpeta (`components/public/hero/full-bleed-hero.tsx`,
  `card-over-cover-hero.tsx`, `color-block-hero.tsx`, `stacked-hero.tsx`) + un selector
  (`salon-hero.tsx`) que elige por `layout.hero`. Igual para `service-row/*` y `staff-card/*`.
- Dialogs, sheets, popovers y toasts se re-envuelven con `<BrandTheme … className="contents">`.
- Cada pantalla se prueba en 4 plantillas × claro/oscuro × marcas grafito `#1F2937`, rojo `#E11D48`,
  ámbar `#F59E0B`, rosa `#F472B6` y lila `#A78BFA`, a 390 y 1440 px, y se compara con la maqueta.
- Las capturas de la app real en un carril propio de Playwright (`E2E_PORT`, `E2E_DB_DATABASE`,
  `E2E_OUTPUT`); la vista previa `?diseno=` permite capturar las cuatro sin cambiar datos.

## 1. Cómo luce cada plantilla en cada tipo de salón

| Plantilla | Barbería | Salón de mujeres / spa | Uñas | Qué ajusta sola para no verse pesada o masculina |
|---|---|---|---|---|
| **Nocturna** | ámbar o rojo sobre carbón azulado, fotos con contraste | rosa `#F472B6` o lila `#A78BFA` brillando sobre carbón: aire de *lounge* nocturno | lila o coral con fotos de manos y esmaltes claras | velo teñido 10 % con la marca (rosa → ciruela, no azul acero); brillo del primario y degradado del botón salen de la marca; en claro (humo) solo la portada y el cierre son oscuros. **Recomendado**: fotos con luz cálida; evitar 3+ fotos muy oscuras seguidas |
| **Luminosa** | grafito o azul petróleo: se ve barbería moderna y limpia | frambuesa, rosa, nude: su terreno natural | lila, coral, menta: halos de color suaves | `brand-2` gira el tono −40°: con grafito da pizarra (sobrio), con rosa da magenta; sin colores fríos forzados |
| **Vibrante** | azul, naranja o rojo en bloques; formas con filo tinta | fucsia, coral: bloques alegres | lila, menta, amarillo: el sticker y las formas de hoja encajan | el color manda: un rosa hace la página rosa. Las formas (arco, hoja) suavizan; la sombra dura es tinta, no negro pesado en oscuro (marca 40 % + negro) |
| **Editorial** | cobre, verde botella, azul marino: barbería clásica | rosa viejo, burdeos: salón elegante | nude, lila suave | serif Fraunces y hueso cálido: neutra por naturaleza |

Colores claros (rosa `#F472B6`, lila `#A78BFA`, ámbar): el servidor los oscurece en claro para que
el botón tenga 3:1 con el fondo y elige tinta oscura o blanca para el texto (`BrandColor`). Probado
en la hoja de muestras: rosa queda `#BE185D` en claro y `#F472B6` con texto oscuro en oscuro, y se
ve bien en las cuatro plantillas.

## 2. Página pública del salón (`pages/public/salon.tsx`)

Orden común: portada → chips rápidos (seña, dirección, Instagram) → servicios → equipo → galería →
horario → mapa → pie "Página hecha con peluWeb · ¿Tienes un salón?" → barra fija (móvil).

| Pieza | Nocturna (ref. `a-nocturna/capturas/salon-390.png`, `salon-1440.png`) | Luminosa (ref. `b-luminosa/capturas/salon-390.png`, `salon-1440.png`, `salon-390-primer-pantallazo.png`) | Vibrante (ref. `c-vibrante/capturas/salon-390.png`, `salon-1440.png`) | Editorial (ref. `actual/salon-390.png`) |
|---|---|---|---|---|
| Portada | `full-bleed`: foto ≈ 78svh, zoom 1.3 en móvil, `photo-veil` + `photo-veil-top`, botones `skin-glass` arriba (volver, Instagram, compartir), logo/monograma 56 px `skin-primary`, tipo · distrito, nombre `display-type display-fit`, descripción 2 líneas, estado en chip `skin-glass`. Escritorio: velo de izquierda a derecha y tarjeta de reserva `skin-raised` a la derecha | `card-over-cover`: foto 468 px con nombre encima; tarjeta `skin-raised` + `brand-halo` montada 48 px con descripción, WhatsApp, Cómo llegar y seña. Escritorio: foto `rounded-3xl` con margen y la tarjeta a la derecha | `color-block`: bloque `bg-primary` con nombre (`text-primary-foreground`), estado en sticker, WhatsApp y Cómo llegar en píldoras con filo; collage de 3 fotos (`rounded-feature`, círculo, `rounded-tile`) con `photo-frame`; borde inferior en onda (SVG con `fill: var(--background)`) | `stacked`: la actual, con `skin-card` en los bloques y título `display-type` |
| Servicios | `list`: por grupo, etiqueta en mayúsculas espaciadas; una `skin-card` por grupo con filas: nombre, precio 800 `tabular-nums`, duración, botón circular 44 px `skin-chip` con flecha | `photo-card`: chips de grupo (Todos, Cortes, Color…) que filtran; primera tarjeta 16:10 a todo el ancho con velo y "Reservar" `skin-glass`; resto en 2 columnas 4:5 con sticker de precio girado −4° | `round-thumb`: `skin-card` por servicio con miniatura redonda 48 px, nombre, duración con ícono, precio y "Reservar" en píldora `bg-brand-soft text-brand-text` | `list` |
| Equipo | `portrait` 4:5 con velo, nombre `display-type` sobre la foto, cargo y "Reservar con {nombre}" en `text-brand-text` | `avatar` 1:1 `rounded-portrait` en `skin-card`, nombre y cargo debajo (cargo en `text-brand-text`) | `portrait` en arco (`rounded-portrait`) con `photo-frame` (filo tinta), nombre y cargo debajo | `avatar` |
| Galería | mosaico 2 columnas, filas 150/180 px, `photo-frame` con viñeta; pie de foto sobre velo | mosaico 2×2 + pequeñas, `rounded-2xl`; la última con "+N" | mosaico con formas mezcladas (`rounded-feature`, círculo, `rounded-tile`) y `grid-template-areas` fijas en escritorio | mosaico simple `rounded-2xl` |
| Horario | `skin-card`, "Hoy" con `highlight: outline` y etiqueta HOY `skin-primary` | `skin-card`, "Hoy" `soft` | `skin-raised`, "Hoy" `solid` (renglón `skin-primary`) | `soft` |
| Mapa | iframe de Google Maps en `photo-frame rounded-2xl` + dirección + "Abrir en Google Maps" `skin-chip` | igual, con "Abrir mapa" a la derecha | igual con filo tinta | igual |
| Barra fija (móvil) | `skin-dock`: WhatsApp (`skin-chip`, 52 px) + "Reservar cita" `skin-primary` | igual | igual (primario con filo y sombra dura) | igual |

## 3. Reserva paso a paso (`pages/public/booking.tsx`, `components/booking/*`)
Misma estructura en las cuatro: encabezado con progreso, pregunta del paso en `display-type
text-title-3`, contenido, resumen que crece (`skin-raised`) y barra fija `skin-dock` con precio,
duración y primario. Referencias: `a-nocturna/capturas/reserva-390.png` y `reserva-1440.png`,
`b-luminosa/capturas/reserva-390.png` y `reserva-390-primer-pantallazo.png`,
`c-vibrante/capturas/reserva-390.png`, `actual/reserva-390.png`.

| Pieza | Qué cambia por plantilla (todo por tokens/skins salvo lo indicado) |
|---|---|
| Servicios | filas de `serviceRow` con casilla; elegido = `border-brand-line bg-brand-soft` |
| Profesional | `staffCard` en versión compacta (retrato 64 px `rounded-portrait`), "Sin preferencia" primero |
| Tira de días | `skin-chip` (día 22–26 px de número en `display-type`, salvo Vibrante: cifras en la fuente de cuerpo), elegido `skin-primary` (Luminosa: sube 4 px; Vibrante: pop), hoy marcado con punto `bg-primary`; días cerrados `opacity-50` tachados |
| Chips de hora | `skin-chip` 48 px por Mañana/Tarde/Noche, elegido `skin-primary` con `layoutId` hacia el resumen |
| Confirmar | resumen `skin-raised`, seña en `bg-brand-soft`; al confirmar check dibujado + confeti 1 vez |

## 4. Mis reservas (`pages/client/bookings/index.tsx`, `show.tsx`)
- **Lista**: mezcla salones, así que cada tarjeta es su propio `<BrandTheme brand theme>` (`skin-card`,
  foto o logo del salón 56 px `rounded-xl`, fecha grande en `display-type`, hora `tabular-nums`,
  quién atiende, precio, estado). El fondo de la página es de la plataforma. Dato que falta:
  `theme` del salón en cada reserva de la lista (hoy solo viaja `brand`).
- **Detalle**: página completa con la plantilla del salón: cabecera con portada del salón según
  `layout.hero` en versión corta (220 px; en `color-block` un bloque de marca sin collage), tarjeta
  `skin-raised` con cuándo / con quién / cuánto / seña, mapa, y acciones (Cómo llegar `skin-primary`,
  Cambiar día u hora y Cancelar `skin-chip`). Diálogo de mover re-envuelto en `BrandTheme`.

## 5. "Diseño de tu página" en Mi salón (`pages/owner/salon/*`, sección nueva en `components/salon-settings/`)
- Sección con título "Diseño de tu página" y ayuda "Así te verán tus clientes. Puedes cambiarlo
  cuando quieras".
- 4 opciones en `SALON_THEME_ORDER` (2 columnas en móvil, 4 en escritorio), cada una un `radio`
  accesible con miniatura real: un `<BrandTheme brand theme>` con un mini hero de su variante a
  escala (foto de portada del salón o de muestra, nombre del salón, botón "Reservar cita"), más
  `label` y `description` de `SALON_THEMES`. Elegida: anillo `ring-2 ring-primary` y check.
- Botón "Ver vista previa" abre la página pública con `?diseno=<plantilla>` en otra pestaña.
- Fotos de muestra cuando el salón no tiene portada o galería: `public/images/muestra/` (backend).
- La miniatura en oscuro: añadir `dark` al contenedor de la miniatura (el CSS lo admite).

## 6. Lo que las maquetas no resuelven (decisiones)
| Caso | Decisión |
|---|---|
| **Servicio sin foto** (hoy los servicios no tienen foto: dato que falta) | `list` y `round-thumb` no la necesitan: `round-thumb` usa el ícono de la categoría en un círculo `bg-brand-soft text-brand-text`. `photo-card` (Luminosa) sin fotos pasa a **tarjeta de texto**: `skin-card` con nombre en `display-type text-title-3`, duración y el sticker de precio, sin el bloque 4:5; si hay fotos de galería, la tarjeta destacada del grupo usa una de la galería con velo. Nunca una imagen genérica de banco repetida |
| **Muchos servicios** (la demo tiene ~40) | > 8 en un grupo: se muestran 6 y "Ver los N de {grupo}" (expande en sitio). Luminosa: los chips de grupo filtran; en las demás, el grupo es plegable |
| **Galería con 0 fotos** | no se muestra la sección |
| **1 foto** | una sola foto 16:9 `rounded-2xl` (Vibrante: `rounded-feature`) a todo el ancho |
| **2 fotos** | dos 4:5 lado a lado |
| **3–5 fotos** | mosaico de la plantilla con las que haya (una grande + el resto) |
| **6 o más (hasta 50)** | se muestran 6 (Luminosa: la 6.ª con "+N"); tocar abre un visor a pantalla completa (`Dialog` re-envuelto, deslizable, `loading="lazy"`, contador "3 de 50") |
| **Nombres largos** con Bebas/Syne (salón y profesionales) | `display-type display-fit` con `--longest-word` y padre `@container`: el tamaño baja hasta que la palabra más larga cabe; nunca se parte una palabra. Máximo 3 líneas en la portada; si no cabe, el tamaño sigue bajando hasta `--title-3`. Probar con "Peluquería Esteticistas Miraflores" y "Barbería Don Lucho & Hijos" |
| **Mapa real de Google en plantilla oscura** | el iframe gratuito no admite estilos: se deja claro, dentro de `photo-frame rounded-2xl` con filo, y sin filtros de color (invertir colores lo vuelve ilegible). Encima, una franja `skin-card` con la dirección y el botón. Si un día hay clave de Maps JS con estilos, Nocturna usaría el estilo oscuro |
| **Salón sin portada** | Nocturna y Luminosa: la primera foto de la galería; sin ninguna, el hero pasa a bloque `bg-band` con degradado de marca suave (`from-brand-soft`) y monograma grande; Vibrante: el bloque de color sin collage; Editorial: como hoy |
| **Salón sin color de marca** | cada plantilla trae su acento (ámbar, frambuesa, azul; editorial cobre/ámbar) |
| **Sin equipo publicado** | se omite la sección (el backend enviará nombre, cargo y foto del equipo: paso c2) |

## 7. Datos que faltan (para quien coordina)
- `theme` en `PublicSalonResource`, `BookableSalonResource`, `ClientBookingResource` (y en cada
  reserva de la lista de Mis reservas) y en `SalonSettingsResource`.
- Equipo en la página pública: nombre, cargo, foto (`-square`) y enlace "Reservar con {nombre}".
- Foto por servicio: no existe; las plantillas funcionan sin ella (§6). Si se añade, `photo-card`
  la usa.
- `BrandColor` mide contra `#FAF8F5`/`#121110`; los fondos más exigentes son `#F3F4F6` y `#111318`.
