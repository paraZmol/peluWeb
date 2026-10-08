# Dirección A · Barbería nocturna

## Manifiesto
1. De noche se ve mejor: carbón azulado profundo, fotos a sangre y un solo acento que brilla.
2. La foto manda y siempre lleva velo; el texto nunca pelea con la imagen.
3. Títulos altos y condensados que se leen como un letrero de barrio; cuerpo sobrio y legible.
4. Profundidad con luz, no con bordes: superficies en capas, filos translúcidos, brillo detrás de la acción.
5. El color es del salón: cambias `--brand` y toda la página se reacomoda, en oscuro y en claro.

## Archivos
| Archivo | Qué es |
|---|---|
| `index.html` | Índice de la dirección con enlaces a las tres pantallas y variantes |
| `portada.html` | Portada de la plataforma para dueños |
| `salon.html` | Página pública de Barbería Don Lucho |
| `reserva.html` | Paso "¿Cuándo?" del asistente |
| `nocturna.css` | Tokens y piezas comunes (lo que pasaría a `app.css`) |
| `nocturna.js` | `?tema=claro`, `?marca=E63946` e íconos lucide en línea |
| `capturas.mjs` | `node docs/design/propuestas/a-nocturna/capturas.mjs [filtro]` → `capturas/` |

## Paleta (tokens)
| Token | Oscuro (principal) | Claro (humo) | Uso |
|---|---|---|---|
| `--bg` | `#0B0F14` | `#F3F4F6` | fondo de página |
| `--bg-band` | `#0E131A` | `#E9ECF0` | franjas alternas |
| `--surface` | `#151B22` | `#FFFFFF` | tarjetas (base del degradado) |
| `--surface-2` | `#1B232C` | `#FBFBFC` | tarjetas (tope del degradado), botón secundario |
| `--surface-3` | `#232C37` | `#EEF0F3` | chips, días, horarios |
| `--line` / `--line-strong` | `white/10` / `white/16` | `#0B0F14/9` / `/15` | filos translúcidos |
| `--highlight` | `white/7` | `white/90` | luz interior superior (relieve) |
| `--text` | `#F3F4F6` | `#0B0F14` | texto principal |
| `--text-muted` | `#A3ADBA` (8.5:1 sobre bg, 7:1 sobre surface-2) | `#48515E` (7.3:1) | secundario |
| `--text-subtle` | `#8591A0` (6:1 sobre bg, 4.9:1 sobre surface-2) | `#5B6573` (5.4:1) | etiquetas de grupo, pie |
| `--success` | `#34D399` | `#047857` | "Abierto" |
| `--on-photo` / `--on-photo-muted` | `#F3F4F6` / `/78` | igual | texto sobre foto, siempre con velo |
| `--veil` | `8 11 15` (rgb) | igual | velos y degradados de foto |
| `--device` / `--device-edge` | `#05070A` / `#2A323C` | igual | marco del celular en la portada |

Las fotos con texto (portada, cierre, tarjetas de equipo) son oscuras en los dos temas: en
claro la portada del salón queda como un bloque oscuro con esquinas inferiores redondeadas.

## Color de marca
Entra un solo valor, `--brand` (por defecto ámbar `#F2B544`; ejemplo de otro salón: rojo
barbero `#E63946`). Todo lo demás se deriva en CSS con color relativo `oklch(from …)`:

| Derivado | Oscuro | Claro | Uso |
|---|---|---|---|
| `--brand-ui` | L limitada a 0.62–0.90 | L 0.30–0.84 | rellenos (botón, día y hora elegidos, monograma) |
| `--brand-ink` | negro si L > 0.58, si no blanco | igual | texto sobre `--brand-ui` (misma regla WCAG que el servidor) |
| `--brand-text` | L ≥ 0.74 | L ≤ 0.50 | acento como texto sobre el fondo (AA) |
| `--brand-hi` / `--brand-deep` | +22 % blanco / −0.12 L | igual | degradado del botón: luz arriba, sombra abajo |
| `--brand-soft` / `--brand-line` / `--brand-glow` | 14 % / 45 % / 42 % alfa | 16 % / 45 % / 38 % | fondos suaves, filos, brillo detrás del botón |

- Ámbar y rojo llevan tinta negra (rojo `#E63946`: 5.0:1 con negro y 4.2:1 con blanco).
- Un color oscuro (`#1F2937`) se aclara solo en modo oscuro para no desaparecer
  (captura `salon-390-marca-oscura.png`): funciona, pero pierde brillo.
- Para shadcn: `--primary` = `--brand-ui`, `--primary-foreground` = `--brand-ink`, `--ring` =
  `--brand-ui`; `--background` = `--bg`, `--card` = `--surface`, `--muted` = `--surface-3`,
  `--muted-foreground` = `--text-muted`, `--border` = `--line`.

## Tipografía
- **Bebas Neue 400** (display, solo mayúsculas): nombre del salón, títulos de sección, la
  pregunta de cada paso, números de día, precio del plan, numerales 01/02/03. `line-height .92`,
  `letter-spacing .01em`, `text-wrap: balance`. Escala: 28 / 40 / 48 / 58–64 en móvil →
  56 / 72 / 96–104 en escritorio.
- **Manrope 400–800** (cuerpo e interfaz): 16 base, 15 en filas y horarios, 14 secundario,
  17 en lead; 12 solo en etiquetas en mayúsculas con `letter-spacing .16em`. Precios y horas con
  `font-variant-numeric: tabular-nums`; precios en 800.
- Carga: Google Fonts con `preconnect` (`Bebas+Neue` y `Manrope:wght@400;500;600;700;800`).

## Forma
- Radios: 10 (pequeño) · 16 (filas, chips cuadrados, horarios) · 20 (días) · 24 (tarjetas) ·
  32–40 (bloques con foto) · píldora para botones y chips.
- Botón primario: píldora, 52 px (44 px el pequeño), degradado `hi → ui → deep`, luz interior
  arriba, brillo de marca debajo. Secundario: superficie con filo. Sobre foto: vidrio
  (`white/10`, `blur 14px`). Un solo primario por vista; en móvil vive en la barra fija.

## Sombras
- `--shadow-1`: luz interior + sombra corta + caída suave (tarjetas).
- `--shadow-2`: lo elevado (tarjeta de reserva, precio, resumen).
- `--glow` y `0 12px 32px -8px var(--brand-glow)`: solo detrás de lo elegido y del primario.
- `--lift`: relieve de chips, días y horarios. `--dock-shadow`: barra fija inferior.
- En claro las sombras bajan a tinta carbón al 6–32 %; nunca negro duro.

## Fotos
- A sangre en las portadas; proporciones fijas: 4:5 equipo, 3:4 prueba social, mosaico de
  galería en filas de 150/180 px; siempre `object-fit: cover`.
- Cada foto lleva viñeta radial (`.photo::after`) y grano SVG al 12 % en `overlay` (`.grain`).
- Texto encima: velo `--veil` de abajo hacia arriba (≥ 0.9 detrás del texto) y un poco arriba
  para los botones de vidrio. En escritorio el velo de la portada va de izquierda a derecha.
- Portada del salón en móvil con zoom 1.3 desde abajo para acercar a quien atiende.

## Movimiento
- Entrada escalonada `rise`: 250 ms, 12 px hacia arriba, 60 ms entre piezas, curva
  `cubic-bezier(.22, 1, .36, 1)`. Una vez, al cargar; nada en cada scroll.
- Al tocar: botones, días y horarios `scale(.97)` en 150 ms y un halo de marca.
- Botón primario: un brillo cruza la píldora al pasar o tocar (600 ms).
- Al elegir hora: un anillo de marca sale del chip una sola vez (600 ms).
- `prefers-reduced-motion`: sin animaciones ni transiciones; todo aparece en su estado final.

## Qué construir encima de lo que existe
Misma estructura y datos que `pages/public/salon.tsx` y `components/booking/*`: portada
(`SalonHero`), datos rápidos en chips, `ServiceList` por grupos con "Reservar" por servicio,
equipo con retrato, `PhotoGallery` en mosaico, `OpeningHours` con "Hoy", `SalonLocation`,
`StickyActions` (WhatsApp + Reservar) en móvil y tarjeta de reserva sobre la portada en escritorio.
