---
name: ui-system
description: Sistema de diseño de peluWeb — identidad de la plataforma (claro hueso/cobre, oscuro Nocturna) y las cuatro plantillas que cada salón elige para su página pública, la reserva y Mis reservas (editorial, nocturna, luminosa, vibrante) - tokens claro/oscuro, color de marca por salón y sus derivados, fuentes y su carga, BrandTheme/data-theme, skins, variantes de disposición, escala tipográfica, radios, sombras, fotos, movimiento y patrones de pantalla. Cargar antes de crear o modificar cualquier pantalla, componente o estilo.
---

# Sistema de diseño (plataforma + plantillas por salón)

Decisión del usuario (2026-10-08, PLAN 4.12): **cada salón elige la plantilla de su página**.
`design-craft` dice cómo se hace una pantalla atrayente; esta skill dice con qué tokens, fuentes
y piezas. En lo público manda `design-craft`; en los paneles, esta skill.

## 1. Dos capas, una sola base de componentes

| Capa | Pantallas | Qué la decide |
|---|---|---|
| **Plataforma** | portada de ventas, registro, login, alta del salón, paneles de dueño y personal, super admin | `:root` (claro) y `.dark` (oscuro) |
| **Plantilla del salón** | página pública `/{slug}`, asistente de reserva, Mis reservas y el detalle de una reserva, vista previa en Mi salón | `data-theme="<plantilla>"` en `BrandTheme` |

- La plataforma: **claro = hueso + cobre + Fraunces/Inter** (el diseño de siempre) y **oscuro =
  paleta, superficies y profundidad de Nocturna** con la misma tipografía Fraunces/Inter.
- `editorial` **es** la plataforma: no redefine nada. Por eso `BrandTheme` sin `theme` (paneles,
  diálogos) se ve exactamente como antes.
- Plantillas: `nocturna`, `luminosa`, `vibrante`, `editorial` (`App\Enums\SalonTheme`, tipo TS
  `SalonTheme`). Las cuatro sirven para **cualquier negocio** — barbería, peluquería de mujeres,
  unisex, uñas, spa — cambiando solo el color de marca y las fotos. Ninguna lleva motivos de un
  solo público (navajas, bigotes, postes de barbero, labios): íconos lucide neutros.
- **Plantilla por defecto según el tipo de negocio** (la pone el servidor al crear el salón):
  `barbershop` → nocturna · `unisex` → nocturna · `beauty_salon`, `nails`, `spa` → luminosa.
- Nunca dos plantillas en una pantalla (salvo la vista previa de Mi salón, que son 4 miniaturas
  aisladas). Nunca colores a mano en componentes (`bg-[#…]`, `text-gray-500`, `rgb()` en `style`):
  solo tokens.
- Maquetas de referencia: `docs/design/propuestas/{a-nocturna,b-luminosa,c-vibrante}/` (README +
  capturas) y `docs/design/propuestas/actual/` (editorial). Qué construir por pantalla:
  `docs/design/plantillas.md`.

## 2. Tokens

### 2.1 Contrato (nombres que todo componente usa)
Los colores, radios y sombras van en `@theme inline` de `resources/css/app.css`: la utilidad lee la
variable donde se usa, así que cualquier contenedor con `data-theme` (o anidado) cambia todo.

| Variable | Utilidad | Uso |
|---|---|---|
| `--background` / `--foreground` | `bg-background` `text-foreground` | página y texto |
| `--band` | `bg-band` | franjas alternas de sección |
| `--card` / `--card-foreground` / `--surface-2` | `bg-card` `bg-surface-2` | tarjetas; `surface-2` = tope del degradado de tarjeta, botón secundario |
| `--muted` / `--muted-foreground` | `bg-muted` `text-muted-foreground` | chips, días, horarios sin elegir; texto secundario (≥ 4.5:1) |
| `--secondary`, `--accent`, `--popover` (+ `-foreground`) | shadcn | iguales a `muted`/`card` en cada plantilla |
| `--border` / `--border-strong` / `--input` | `border-border` `border-border-strong` `border-input` | filo translúcido; filo visible (en Vibrante = tinta); campos |
| `--primary` / `--primary-foreground` / `--ring` | `bg-primary` … | **color del salón** (servidor); botón, elegido, foco |
| `--brand-text` | `text-brand-text` | la marca usada como texto o ícono sobre el fondo (AA 4.5:1) |
| `--brand-soft` | `bg-brand-soft` | fondo suave de marca: "Hoy", avisos, "Reservar" secundario |
| `--brand-line` | `border-brand-line` | filo de marca (elegido, tarjeta destacada) |
| `--brand-2` | `to-brand-2` | segundo tono del degradado de marca |
| `--brand-glow` | (dentro de `shadow-brand`) | brillo detrás del primario |
| `--success` `--warning` `--destructive` (+ `-foreground`) | shadcn | estados |
| `--on-photo` / `--veil` | `text-on-photo` `from-veil/90` | texto sobre foto y color del velo |
| `--radius` → `rounded-sm/md/lg` | | controles |
| `--radius-card` → `rounded-xl` · `--radius-photo` → `rounded-2xl` · `--radius-hero` → `rounded-3xl` | | tarjetas · fotos y bloques · portada |
| `--shape-portrait/feature/tile` | `rounded-portrait` `rounded-feature` `rounded-tile` | forma de retrato, foto destacada, foto de mosaico (en Vibrante: arco, esquina asimétrica, hoja) |
| `--elevation-1/2/3` | `shadow-card` `shadow-raised` `shadow-float` | reposo · elevado · flotante |
| `--primary-shadow` | `shadow-brand` | bajo el primario y lo elegido |
| `--title-1/2/3` | `text-title-1/2/3` | nombre del salón · título de sección · pregunta del paso / subtítulo |

**Skins** (`@utility` en `app.css`; una por papel de superficie, cada plantilla rellena sus tokens.
No combinar un skin con `bg-*`, `border-*` ni `shadow-*` en el mismo elemento; el radio sí va aparte):

| Clase | Qué pinta |
|---|---|
| `skin-card` | tarjeta sobre el fondo (`--card-fill`, `--card-edge`, `--elevation-1`) |
| `skin-raised` | lo elevado: tarjeta de reserva, resumen, precio |
| `skin-primary` | botón primario, día y hora elegidos (relleno, degradado, filo y sombra de la plantilla) |
| `skin-chip` | chip, día de la tira, horario sin elegir |
| `skin-glass` | botón o chip sobre foto (vidrio con desenfoque) |
| `skin-dock` | barra fija inferior (translúcida con desenfoque) |
| `photo-frame` | contenedor de foto: `overflow-hidden`, `img` en `object-cover`, filo y viñeta de la plantilla |
| `photo-veil` / `photo-veil-top` | capa `absolute inset-0` con el velo de abajo / de arriba |
| `brand-halo` | capa difuminada con el degradado de marca detrás de una tarjeta clave (solo Luminosa; en las demás no pinta) |
| `display-type` | títulos display: familia, peso, tracking, interlineado y mayúsculas de la plantilla |
| `display-fit` | nombre del salón y títulos con nombres propios: reduce el tamaño lo justo para que la palabra más larga quepa sin partirse. El padre lleva `@container`; el componente pone `style={{ '--longest-word': n }}` (letras de la palabra más larga) y opcionalmente `--fit-size` (tope; por defecto `--title-1`). Usa `--display-char` (ancho del glifo más ancho: editorial .6, nocturna .46, luminosa .56, vibrante .8) |

Al añadir un token compuesto que lee otro token (`--x: 1px solid var(--border)`), declararlo en
`:root` **y** en `.dark` (y en el bloque de cada plantilla que lo cambie): una variable que
referencia otra se resuelve en el elemento donde se declara, no donde se usa.

### 2.2 Plataforma (= `editorial`)

| Token | Claro | Oscuro (Nocturna) |
|---|---|---|
| `--background` / `--band` | `#FAF8F5` / `#F4F0EB` | `#0B0F14` / `#0E131A` |
| `--card` / `--surface-2` / `--popover` | `#FFFFFF` / `#FDFCFA` / `#FFFFFF` | `#151B22` / `#1B232C` / `#1B232C` |
| `--muted` (= secondary, accent) | `#F1ECE6` | `#232C37` |
| `--foreground` / `--muted-foreground` | `#1F1B18` / `#6B635C` | `#F3F4F6` / `#A3ADBA` |
| `--border` / `--border-strong` / `--input` | `#E8E2DB` / `#D6CDC3` / `#E8E2DB` | blanco 10 % / 16 % / 16 % |
| `--primary` / `--primary-foreground` (sin marca) | `#8F5E45` / blanco | `#F2B544` (ámbar) / `#0B0F14` |
| `--success` / `--warning` | `#15803D` / `#B45309` | `#34D399` / `#FBBF24` |
| sidebar | `#F4F0EB`, acento `#EBE4DC` | `#0E131A`, acento `#1B232C` |
| tarjeta | plana, filo `--border` | degradado `surface-2 → card` + luz interior |
| sombras | tinta cálida 5–30 %, dos capas | luz interior `white/7` + negro 40–80 % |

Fondo previo a la carga en `app.blade.php`: `#FAF8F5` / `#0B0F14`.

### 2.3 Plantillas

| Token | Nocturna claro (humo) | Nocturna oscuro | Luminosa claro | Luminosa oscuro | Vibrante claro | Vibrante oscuro |
|---|---|---|---|---|---|---|
| `--background` | `#F3F4F6` | `#0B0F14` | `#FFFFFF` | `#111318` | `#FBFBFD` | `#0E0E11` |
| `--band` | `#E9ECF0` | `#0E131A` | `#F7F8FA` | `#161920` | `#F2F2F6` | `#141418` |
| `--card` | `#FFFFFF` | `#151B22` | `#FFFFFF` | `#1A1D24` | `#FFFFFF` | `#18181D` |
| `--surface-2` | `#FBFBFC` | `#1B232C` | `#F1F3F7` | `#232731` | `#F2F2F6` | `#222229` |
| `--muted` | `#EEF0F3` | `#232C37` | `#F1F3F7` | `#232731` | `#F2F2F6` | `#222229` |
| `--foreground` | `#0B0F14` | `#F3F4F6` | `#111318` | `#F3F4F7` | `#0E0E11` | `#F4F4F7` |
| `--muted-foreground` | `#48515E` (7.3:1) | `#A3ADBA` (8.5:1) | `#5A6172` (6.2:1) | `#A6ADBB` | `#565663` (7:1) | `#A7A7B4` |
| `--border` | tinta 9 % | blanco 10 % | tinta 7 % | blanco 7 % | tinta 10 % | blanco 9 % |
| `--border-strong` | tinta 15 % | blanco 16 % | tinta 14 % | blanco 12 % | `#0E0E11` | `#3C3C48` |
| `--input` | tinta 18 % | blanco 16 % | tinta 16 % | blanco 16 % | tinta 22 % | blanco 20 % |
| `--success` | `#047857` | `#34D399` | `#047857` | `#32D583` | `#047857` | `#32D583` |
| `--veil` (base) | `#080B0F` | `#080B0F` | `#0A0B0F` | `#0A0B0F` | `#0E0E11` | `#0E0E11` |
| `--primary` sin marca | ámbar `#F2B544` / tinta `#0B0F14` | igual | frambuesa `#D6336C` / blanco | igual | azul `#2D5BFF` / blanco | igual |
| `--ring` | marca | marca | marca | marca | tinta `#0E0E11` | `#F4F4F7` |

**Nocturna es siempre oscura** (decisión del 2026-10-08: es lo que mostraba la maqueta elegida y
lo que promete su nombre). `BrandTheme` añade `dark` al contenedor para las plantillas de
`ALWAYS_DARK_THEMES` (`components/salon/salon-themes.ts`); el CSS lo admite con
`[data-theme].dark` y `.brand-scope.dark`, y `PortalTheme` lo hereda. La columna "Nocturna claro
(humo)" sigue definida en `app.css` pero no se usa mientras Nocturna esté en ese conjunto.
Editorial, Luminosa y Vibrante siguen el modo del visitante (claro/oscuro, como el resto de la
app). Ojo: la variante `dark:` es `&:is(.dark *)`, así que no se aplica al propio contenedor de
`BrandTheme`, solo a lo que lleva dentro.

### 2.4 Color de marca del salón
1. El dueño elige un color; el servidor (`App\Support\BrandColor::tokens`, vía `Salon::brandTokens()`)
   devuelve `light` y `dark` con `primary` (≥ 3:1 contra el fondo) y `foreground` (blanco o
   `#1F1B18`, ≥ 4.5:1 sobre `primary`). Los recursos públicos envían `brand` y `theme`.
2. `BrandTheme` pone `--brand-light*`/`--brand-dark*` y la clase `.brand-scope`, que elige la
   variante según el modo y gana sobre el acento por defecto de la plantilla. **El texto sobre
   el primario siempre es `--primary-foreground`**: nunca se recalcula en CSS.
3. Derivados en CSS, declarados en `:root, .brand-scope, [data-theme]` (se recalculan donde cambia
   `--primary`), en tres niveles:
   - **Base (cualquier navegador)**: `brand-text = foreground`, `brand-soft = muted`,
     `brand-line = border-strong`, `brand-glow = transparent`, `brand-2 = primary`, sin degradados.
     Se ve sobrio pero correcto: nunca se pierde contraste.
   - **`color-mix`** (la base de Tailwind 4): `brand-text` = marca 62 % + negro (claro) / blanco
     (oscuro); `brand-soft` = marca 10–16 % sobre `card`; `brand-line` 45 %; `brand-glow` 0–42 %;
     `brand-hi` = marca + 22 % blanco; `brand-2` = marca + 18 % negro; velo teñido de marca.
   - **`oklch(from …)`**: `brand-text` con L limitada (claro ≤ 0.50, oscuro ≥ 0.74: ≥ 4.5:1 en
     todos los fondos) y `brand-2` por plantilla (Nocturna L −0.12; Luminosa L +0.05 y tono −40°;
     Vibrante = marca; Editorial L −0.10).
4. Perillas por plantilla (variables simples): `--brand-soft-amount`, `--brand-glow-amount`,
   `--brand-2-l`, `--brand-2-h`, `--veil-tint` (Nocturna 10 %, Luminosa 6 %: el velo toma un poco
   del color del salón, así una marca rosa da velos ciruela en vez de azul carbón).
5. Prueba obligatoria de cada pantalla pública con: grafito `#1F2937`, rojo `#E11D48`, ámbar
   `#F59E0B`, rosa `#F472B6` y lila `#A78BFA`, en claro y oscuro.
6. Pendiente del servidor (anotado para quien coordina): `BrandColor` mide el 3:1 contra
   `#FAF8F5`/`#121110`; los fondos más exigentes ahora son `#F3F4F6` (Nocturna claro) y `#111318`
   (Luminosa oscuro), con 1–4 % menos de contraste. Medir contra esos dos lo deja exacto.

## 3. Fuentes y cómo se cargan

| Plantilla | Display (títulos) | Cuerpo e interfaz |
|---|---|---|
| plataforma / editorial | **Fraunces** 500/600 | **Inter** 400/500/600 |
| nocturna | **Bebas Neue** 400, mayúsculas, tracking .01em, interlineado .92 | **Manrope** 400–800 |
| luminosa | **DM Serif Display** 400 (y cursiva), tracking −.01em, interlineado 1.05 | **Plus Jakarta Sans** 400–800 |
| vibrante | **Syne** 800 (700 respaldo), tracking −.03em, interlineado 1; **nunca cifras** | **Inter** 400–800 |

- Todas se **autoalojan** con `laravel-vite-plugin/fonts` (`bunny(...)` en `vite.config.ts`): sin
  peticiones a Google desde la página de un salón (privacidad, CSP, E2E sin red externa).
- `@fonts` en `app.blade.php` declara todas las `@font-face` (≈ 18 KB, 1.4 KB comprimido) pero
  **solo precarga Inter 400/500/600 y Fraunces**. Un navegador descarga una `@font-face` la primera
  vez que un texto la usa: una página Nocturna baja Bebas + Manrope y nada de Luminosa/Vibrante.
- Cada familia trae su respaldo métrico (`'<Familia> fallback'`, generado): sin saltos al cargar.
- La plantilla cambia `--font-sans` y `--font-display` en su contenedor; `BrandTheme` añade
  `font-sans`. Títulos con `display-type` (o `font-display` si solo se quiere la familia).
- Precios, horas, fechas y duraciones: `tabular-nums` y la fuente de cuerpo (en Vibrante también
  el precio grande del plan). Mayúsculas espaciadas (`tracking-[.16em] text-xs font-bold`) solo
  en etiquetas cortas.
- Nueva familia = añadirla en `vite.config.ts` con `preload: false` y en el bloque de su plantilla.

## 4. Arquitectura: `BrandTheme` + `data-theme` + variantes

```tsx
<BrandTheme brand={salon.brand} theme={salon.theme} className="min-h-svh bg-background">
```
- `resources/js/components/salon/brand-theme.tsx`: pone `data-theme`, `font-sans`, `.brand-scope`
  y las variables de marca; `theme` opcional → `editorial` (= plataforma).
- `useSalonTheme()` devuelve `{ theme, brand, layout }`. Los componentes **no** preguntan
  `theme === 'nocturna'`: leen `layout` (variantes) y usan tokens/skins.
- **Portales** (Dialog, Sheet, Drawer, Popover, Select, Tooltip, toasts) se montan en `body`, fuera
  del contenedor: envolver su contenido con
  `<BrandTheme brand={brand} theme={theme} className="contents">` (de `useSalonTheme()`).
- Configuración en `components/salon/salon-themes.ts` (`SALON_THEMES`, `SALON_THEME_ORDER`,
  etiquetas y descripción para "Diseño de tu página").

**Variantes de disposición** (lo único que cambia el DOM entre plantillas):

| Ranura | Variante | Qué es | Plantillas |
|---|---|---|---|
| `hero` | `full-bleed` | foto a sangre, alta (≈ 78svh móvil, 640 px escritorio), velo de abajo, logo + tipo + nombre + estado sobre la foto; en escritorio, tarjeta de reserva flotando a la derecha | nocturna |
| | `card-over-cover` | foto a sangre de 468 px con nombre sobre velo; tarjeta flotante (`skin-raised` + `brand-halo`) que monta 48 px sobre la foto con descripción, WhatsApp, Cómo llegar y seña | luminosa |
| | `color-block` | bloque `bg-primary` a sangre con nombre, estado y acciones; collage de 3 fotos con formas (`rounded-feature`, círculo, `rounded-tile`) y borde inferior ondulado | vibrante |
| | `stacked` | portada 16:9 que se funde con el fondo, logo montado, nombre y datos debajo (la actual) | editorial |
| `serviceRow` | `list` | filas en una `skin-card` por grupo: nombre, precio `tabular-nums`, duración y botón circular "Reservar" | nocturna, editorial |
| | `photo-card` | tarjetas 4:5 con foto, sticker de precio y duración; la primera del grupo destacada a 16:10 a todo el ancho; chips de filtro por grupo arriba | luminosa |
| | `round-thumb` | tarjeta `skin-card` con miniatura redonda 48 px, nombre, duración, precio y "Reservar" en píldora `bg-brand-soft` | vibrante |
| `staffCard` | `portrait` | retrato 4:5 (`rounded-portrait`) con velo y nombre display encima | nocturna |
| | `captioned-portrait` | retrato en arco con nombre (`font-sans` 16 px negrita) y cargo debajo de la foto | vibrante |
| | `avatar` | foto 1:1 (`rounded-portrait`) en tarjeta con nombre y cargo debajo | luminosa, editorial |
| `highlight` | `outline` | "Hoy"/elegido con `border-brand-line` + `bg-brand-soft` | nocturna |
| | `soft` | `bg-brand-soft` + `text-brand-text` | luminosa, editorial |
| | `solid` | `skin-primary` a todo el renglón | vibrante |

Galería, horario, mapa, barra fija, tira de días y chips de hora tienen **una sola disposición**;
cambian solo por tokens y skins (formas, sombras, radios).

## 5. Forma, sombras, fotos y movimiento por plantilla

| | Editorial (plataforma) | Nocturna | Luminosa | Vibrante |
|---|---|---|---|---|
| `--radius` · card · photo · hero | 14 · 12 · 16 · 24 px | 16 · 24 · 32 · 40 | 18 · 24 · 32 · 32 | 12 · 24 · 36 · 36 |
| Formas portrait/feature/tile | 16/16/12 | 24/32/16 | 18/24/18 | arco / asimétrica 22-64 / hoja 48-14 |
| Botones y chips | píldora | píldora, primario 52 px | píldora | píldora con filo 1.5 px |
| Tarjeta | plana + 2 capas cálidas | degradado + luz interior + filo translúcido | blanca + 2 capas frías + halo | filo tinta 1.5 px + sombra desplazada `4px 4px 0` de marca |
| Primario | sólido | degradado `hi → marca → brand-2`, brillo de marca debajo | degradado 135° `marca → brand-2`, sombra teñida | sólido + sombra dura `3px 3px 0` tinta |
| Escala móvil → escritorio (title-1/2/3) | 30→48 / 24→32 / 22 | 56→104 / 40→72 / 28→40 | 42→80 / 34→48 / 24→32 | 36→76 / 32→44 / 22→28 |
| Fotos | 16:9 portada, 1:1 galería | viñeta radial (`photo-frame`), velo fuerte, zoom 1.3 en portada móvil | grandes, sticker de precio girado −4°, velo inferior | formas con filo 1.5 px; stickers girados |
| Movimiento propio | entradas `rise` | brillo que cruza el primario (600 ms); anillo al elegir hora | tarjetas suben 4 px al pasar; día elegido sube 4 px | "pop" 0.96→1.04→1 (260 ms, curva `[.34,1.56,.64,1]`); botón se hunde 3 px al tocar |

Común a todas: velo en toda foto con texto (`photo-veil`, ≥ 0.9 detrás del texto); `object-cover`
con proporción fija (`aspect-*`); `loading="lazy"` salvo la portada; `alt` real en galería y equipo.

**Movimiento** (`motion/react`): micro 150 ms, transición 250 ms, celebración ≤ 600 ms, curva
`[0.22, 1, 0.36, 1]`. Entrada `rise` (opacidad + 12 px, escalonada 60 ms, una vez al cargar). Al
tocar: `scale(.97)`. Solo `transform` y `opacity`. `useReducedMotion()`: sin desplazamientos, solo
fades cortos. Nunca bloquear un botón mientras anima. Reserva: avance con slide lateral, chip de hora
→ resumen con `layoutId`, check dibujado + confeti una vez al confirmar. Login/registro: tarjeta
entra con fade + 12 px; error = sacudida corta; éxito = check antes de navegar. Agenda: citas
nuevas con fade; arrastrar con sombra elevada. Cambio de tema: 200 ms en color de fondo y texto.

## 6. Componentes
- **shadcn/ui** (`components/ui/*`) para controles: extender, no reescribir. Leen los tokens, así
  que dentro de `BrandTheme` toman la plantilla. Botones públicos grandes: `skin-primary`
  `rounded-full h-13` (no el `Button` de 36 px).
- Íconos **lucide-react**, trazo 1.75, una sola familia.
- `TimeWheel` (ruedas de hora/minuto, saltos de 5 min) para horarios del personal.
- Reserva: tira horizontal de días (`skin-chip`, elegido `skin-primary`) + chips de hora por
  Mañana / Tarde / Noche. Nada de selects para elegir día u hora.
- Agenda: "Hoy" en lista con la cita actual resaltada; semana por profesional en escritorio.
- Móvil: hojas inferiores (`Drawer`/`Sheet` abajo) en vez de modales centrados.
- Gráficos: chart de shadcn (Recharts) con `--chart-1..5`.
- Componentes ≤ 150 líneas: una variante de disposición = un componente
  (`hero/full-bleed-hero.tsx`, …) y un selector que lee `layout`.

## 7. Patrones de pantalla
- **Página pública**: portada (variante `hero`) → datos rápidos en chips (seña, dirección) →
  servicios por grupo (variante `serviceRow`; > 8 servicios en un grupo: muestra 6 y "Ver los N")
  → equipo (variante `staffCard`) → galería en mosaico → horario con "Hoy" (variante `highlight`)
  → mapa + "Abrir en Google Maps" → pie "Página hecha con peluWeb". Barra fija en móvil
  (`skin-dock`: WhatsApp + "Reservar cita"); en escritorio la tarjeta de reserva vive en la portada.
- **Reserva**: progreso visible, una pregunta por paso como título (`display-type text-title-3`),
  chips grandes, resumen que crece, barra fija con precio y duración + primario.
- **Mis reservas**: tarjeta por reserva con foto/logo del salón, fecha grande, quién atiende,
  precio, estado; detalle con mapa y acciones (cómo llegar, mover, cancelar). Toma la plantilla del
  salón de cada reserva; la lista mezcla salones, así que cada tarjeta es su propio `BrandTheme`.
- **Diseño de tu página** (Mi salón): 4 opciones con miniatura real (`BrandTheme` por miniatura,
  con las fotos y el color del salón o fotos de muestra), nombre, descripción y "Ver vista previa"
  (`?diseno=`). Ver `docs/design/plantillas.md`.
- **Plataforma**: 360 px primero; acción principal fija abajo ≥ 48 px; navegación inferior por
  rol (Cliente: Explorar, Mis reservas, Perfil · Personal: Hoy, Agenda, Clientes, Más · Dueño: Hoy,
  Agenda, Caja, Reportes, Más); en cada lista: skeleton, vacío con acción y error con reintento.

## 8. Textos
Español neutro de Perú, tuteo, sin género ("tus clientes", "quien te atiende", "tu equipo").
Sin jerga: "horario" no "slot"; "seña" o "adelanto" según el salón. Precios `S/ 35.00`; horas
`3:30 p. m.`; fechas "mar 14 oct".

## 9. Lista de verificación de una pantalla
1. ¿Solo tokens, skins y utilidades del contrato? (`rg "#[0-9a-fA-F]{3,6}|rgb\(|gray-|zinc-|slate-"` vacío en el componente)
2. ¿Probada en las 4 plantillas × claro/oscuro × 5 colores de marca (§2.4)? ¿Funciona igual en una
   barbería, un salón de mujeres y uno de uñas?
3. ¿Lee `useSalonTheme().layout` en vez de comparar nombres de plantilla? ¿Los portales re-envuelven?
4. ¿Toda foto con proporción fija, `object-cover` y velo bajo el texto?
5. ¿Títulos con `display-type`; precios y horas con `tabular-nums` en la fuente de cuerpo?
6. ¿Profundidad de tres planos (fondo, `skin-card`, `skin-raised`/primario) y un solo primario?
7. ¿360 px sin desbordes con nombres largos? (nombre del salón y de profesionales con
   `display-fit`; Syne y Bebas son anchas). ¿Texto ≥ 14 px, contraste AA, foco visible (`ring`)?
8. ¿Movimiento según §5, una sola vez, con `useReducedMotion()`?
9. Capturas a 390 y 1440 comparadas con la maqueta de su plantilla (`docs/design/propuestas/`).
