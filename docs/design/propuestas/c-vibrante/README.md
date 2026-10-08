# Dirección C — "Vibrante"

## Manifiesto
1. El color del salón es el protagonista: bloques a sangre, no un acento tímido.
2. Fondo casi blanco, tarjetas blancas con borde negro fino y una sombra corta del color de marca: profundidad gráfica, no difusa.
3. Títulos en una geométrica ancha y gruesa (Syne 800); todo lo que se lee o se compara (precios, horas, fechas) va en Inter con cifras tabulares.
4. Las fotos van recortadas en formas (arco, círculo, hoja, esquina asimétrica) con el mismo borde negro; los stickers inclinados ponen el guiño.
5. Energía de app moderna, pero seria: un solo botón primario por vista, siempre bajo el pulgar.

## Archivos
- `index.html`: portada de la plataforma para dueños (hero, para quién, "así se ve tu página" con la página real en dos colores, 3 pasos, precio y cierre).
- `salon.html`: página pública de Corte & Color Surco (portada, servicios, equipo, galería, horario, ubicación, seña y barra fija).
- `reserva.html`: paso "¿Cuándo?" (tira de días, chips por Mañana/Tarde/Noche, resumen y barra fija). Se pueden tocar los chips.
- `vibrante.css`: tokens y componentes compartidos. `vibrante.js`: marca, tema e íconos.
- `capturas.mjs`: `node docs/design/propuestas/c-vibrante/capturas.mjs [filtro]` desde la raíz del repo.
- Parámetros de URL: `?brand=FF6B1A` (otro color de marca), `?theme=dark`, `?embed=1` (sin animaciones).

## Tokens
| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--bg` | `#FBFBFD` | `#0E0E11` | fondo de página |
| `--surface` | `#FFFFFF` | `#18181D` | tarjetas, chips, barra fija |
| `--surface-2` | `#F2F2F6` | `#222229` | píldoras internas, día cerrado |
| `--ink` | `#0E0E11` | `#F4F4F7` | texto principal |
| `--muted` | `#565663` (7:1) | `#A7A7B4` | texto secundario |
| `--line` | `#0E0E11` | `#3C3C48` | borde 1.5 px de tarjetas |
| `--line-mid` | ink 22 % | ink 20 % | borde de chips sin elegir |
| `--line-soft` | ink 10 % | ink 9 % | divisores internos |
| `--hard` | `#0E0E11` | marca 40 % + negro | sombra desplazada neutra (botones, stickers) |
| `--brand` | marca del salón (def. `#2D5BFF`) | igual | bloques a sangre, primario, elegido |
| `--brand-ink` | blanco o `#0E0E11` según WCAG | igual | texto sobre `--brand` |
| `--brand-text` | marca oscurecida hasta 5:1 con `--bg` | marca aclarada hasta 6:1 | marca usada como texto o ícono |
| `--brand-soft` | marca 10 % sobre surface | marca 16 % | fondos de aviso, "Reservar" de cada servicio |
| `--brand-tint` | marca 22 % | marca 28 % | mapa estilizado |
| `--pop` / `--pop-ink` | `#FFE14D` / `#0E0E11` | igual | amarillo "chicha", **solo en la portada de la plataforma** |
| `--ok` | `#12A150` | igual | punto "abierto" |
| `--on-photo`, `--veil` | blanco + degradado negro 82 %→0 | 85 %→0 | texto sobre foto, siempre con velo |

## Tipografía
- Display: **Syne 800** (700 de respaldo), tracking −0.03em, interlínea 1, `text-wrap: balance`. Solo palabras: nombre del salón, títulos de sección, "¿Cuándo?". **Nunca cifras** (sus números son raros).
- Cuerpo e interfaz: **Inter 400/500/600/700/800**. Precios, horas, días y el precio grande del plan en Inter con `tabular-nums`.
- Escala móvil: 13 (etiquetas, stickers en mayúsculas) / 14 / 15 / 16 / 17 / 20 / 32 (h2) / `clamp(36px, 11vw, 44px)` (nombre del salón). Escritorio: h2 44, nombre del salón 76, titular de portada 54.
- Carga: Google Fonts `Syne:wght@700;800` + `Inter:wght@400;500;600;700;800` con preconnect.

## Radios y bordes
`--r-sm 12` · `--r-md 18` (días) · `--r-lg 24` (tarjetas) · `--r-pill` (botones, chips, stickers).
Formas de foto: `--r-arch` (999 999 22 22), círculo, `--r-leaf` (48 14 48 14), `--r-asym` (22 64 22 22). Borde siempre 1.5 px.

## Sombras
- `--shadow-brand`: `4px 4px 0 var(--brand)` + `--shadow-soft` (difusa de dos capas): tarjetas sobre el fondo.
- `--shadow-hard`: `3px 3px 0 var(--hard)`: botones primarios, stickers, chip/día elegido, tarjetas sobre un bloque de marca (donde la sombra de marca no se vería).
- Botón al presionar: se hunde 3 px y la sombra pasa a 0; al pasar el cursor sube 1 px y la sombra crece a 4 px.

## Fotos
- Proporciones fijas (4:5 retratos, 1:1 círculos, 16:9 mapa y mosaicos anchos) con `object-fit: cover` y borde 1.5 px.
- Portada del salón: foto principal en arco + círculo + hoja, dentro del bloque de marca; galería en mosaico con formas mezcladas (en escritorio con `grid-template-areas` fijas para no dejar huecos).
- Texto sobre foto solo con `--veil` (mosaico "Barberías / Salones / Uñas").
- Fotos usadas: 705255, 3065171, 1319462, 3356170, 2076932, 3065170, 3993312, 3993311, 7518731, retratos `-square` 3993312, 3992875, 4625648; portada: 3993311, 18483774-square, 16363470, 1813272, 3422099.

## Movimiento
- Entradas: `rise` (opacidad + 12 px, 250 ms, escalonado de 70 ms, curva `[0.22, 1, 0.36, 1]`), una vez.
- Chips y días: suben 2 px al pasar (150 ms, curva con rebote `[0.34, 1.56, 0.64, 1]`); al elegir, "pop" de escala 0.96→1.04→1 (260 ms).
- Stickers: al pasar giran al ángulo opuesto y crecen 5 % (250 ms, rebote).
- Solo `transform` y `opacity`; con `prefers-reduced-motion` todo queda quieto.

## Cómo entra el color de marca
1. El salón elige un color; el servidor guarda `--brand` y calcula `--brand-ink` (blanco si da ≥ 4.5:1, si no `#0E0E11`) y `--brand-text` (la marca oscurecida/aclarada hasta 5:1 en claro y 6:1 en oscuro). `vibrante.js` hace lo mismo en la maqueta.
2. Los derivados (`--brand-soft`, `--brand-tint`, `--hard` oscuro) salen de `color-mix()` en CSS: no hace falta guardarlos.
3. Probado con azul `#2D5BFF` (texto blanco), naranja `#FF6B1A` y ámbar `#F59E0B` (texto negro) y grafito `#1F2937` (texto blanco); ver `capturas/`.
4. El modo oscuro mantiene la marca tal cual en los bloques; solo cambian fondo, superficies, bordes y la sombra dura.
