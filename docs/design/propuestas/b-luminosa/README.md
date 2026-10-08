# Propuesta B · "Salón luminoso" (`b-luminosa`)

Maqueta: [index.html](index.html) (las tres pantallas, una debajo de otra). Capturas: [capturas/](capturas/).
Regenerar: `node docs/design/propuestas/b-luminosa/capturas.mjs` (desde la raíz; usa `playwright` del proyecto, sin servidor).
Parámetros de la maqueta: `?only=portada|salon|reserva`, `&theme=dark`, `&brand=0F9D7A&ink=1F1B18` (otro color de marca).

## Manifiesto
1. Un salón recién abierto a media mañana: blanco de verdad, luz fría y nada de beige.
2. La foto es la vitrina: grande, con esquinas redondeadas, y el precio pegado encima como un sticker.
3. El color del salón no tiñe la página; la ilumina: halos y degradados suaves de dos tonos que salen de su marca.
4. Todo flota un poco: tarjetas con sombra de dos capas, botones en píldora que suben al tocarlos.
5. Una sola acción por pantalla, grande y al alcance del pulgar.

## Paleta (variables CSS de la maqueta)
| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--bg` | `#FFFFFF` | `#111318` (antracita) | fondo de página |
| `--bg-soft` | `#F7F8FA` | `#161920` | secciones alternas ("Así se ve tu página") |
| `--surface` | `#FFFFFF` | `#1A1D24` | tarjetas |
| `--surface-2` | `#F1F3F7` | `#232731` | pastillas, chips inactivos, día cerrado |
| `--ink` | `#111318` | `#F3F4F7` | texto principal |
| `--ink-2` | `#5A6172` (6.2:1 en blanco) | `#A6ADBB` | texto secundario |
| `--edge` | `rgb(17 19 24 / .07)` | `rgb(255 255 255 / .07)` | borde fino translúcido (nunca gris sólido) |
| `--ok` / `--ok-text` | `#12B76A` / `#047857` | `#32D583` / `#6CE9A6` | "Abierto", seña confirmada |
| `--brand` | marca del salón (def. `#D6336C` frambuesa) | la versión oscura que da el servidor | botón primario, día/hora elegidos |
| `--brand-ink` | texto sobre la marca (blanco o `#1F1B18`, del servidor) | ídem | |
| `--on-photo`, `--veil`, `--glass`, `--glass-edge` | blanco, velo `rgb(10 11 15 / .84)`, cristal blanco 16 % | iguales | texto y botones sobre foto |

**Derivados de `--brand`** (se calculan solos; nada se escribe a mano por salón):
- `--brand-2: oklch(from var(--brand) calc(l + .05) c calc(h - 40))` — segundo tono del degradado
  (frambuesa → magenta violáceo; esmeralda → verde hoja; grafito → pizarra).
- `--brand-soft: color-mix(in oklch, var(--brand) 11%, var(--surface))` — fondos de botones secundarios y fila "hoy".
- `--brand-text: color-mix(in oklch, var(--brand) 80%, var(--ink))` — marca usada como texto (≥ 4.5:1 incluso con esmeralda).
- `--halo-a / --halo-b`: marca y segundo tono al 30 % (claro) o 20 % (oscuro) para halos y fondos `wash`.
- `--shadow-brand`: sombra de dos capas teñida con la marca, solo bajo el botón primario y lo elegido.

## Tipografía
- **DM Serif Display** 400 (y cursiva) — títulos, nombre del salón, números grandes del precio del plan.
  Móvil: H1 42–48 px, secciones 34 px. Escritorio: 76–84 px y 44–52 px. `text-wrap: balance`.
- **Plus Jakarta Sans** 400/500/600/700/800 — todo lo demás. Cuerpo 16 px, secundario 14 px (mínimo en
  móvil), etiquetas 12 px en mayúsculas espaciadas (solo eyebrows y días de la tira). Precios y horas
  con `font-variant-numeric: tabular-nums`, peso 700–800.
- Carga: Google Fonts con `preconnect` (en la app: `app.blade.php`), `display=swap`.

## Radios
Tarjetas 24 px · fotos dentro de tarjeta 18 px (radio exterior menos el padding de 8 px, redondeado)
· portada y bloques grandes 32 px · día de la tira 22 px · botones, chips de hora y pestañas: píldora.

## Sombras (siempre dos capas: contacto + difusa)
- `--shadow-1` reposo: `0 1px 2px /.06` + `0 6px 16px -4px /.08`
- `--shadow-2` al tocar/elevado: `0 2px 6px /.06` + `0 18px 36px -10px /.18`
- `--shadow-3` flotante (tarjetas sobre foto, notificaciones): `0 4px 10px /.06` + `0 36px 72px -18px /.28`
- Oscuro: mismas capas en negro más densas + brillo interior `inset 0 1px 0 rgb(255 255 255 / .05)`.
- `.halo`: degradado `--halo-a → --halo-b` desenfocado 26 px detrás de las tarjetas clave
  (reserva, resumen, precio, WhatsApp). Si se quitan sombras y halos, la maqueta se ve plana: es a propósito.

## Fotos
- Proporciones fijas: portada 468 px de alto a sangre en móvil y tarjeta de 32 px en escritorio;
  servicio destacado 16:10, servicios 4:5, equipo 1:1, galería en mosaico (2×2 + pequeñas). `object-fit: cover`.
- Texto sobre foto siempre con velo inferior (`--veil` → transparente) y velo superior para los botones
  de cristal. En la portada de la plataforma, los ejemplos de salón usan **velo del color de marca** del salón.
- Sticker de precio: píldora blanca (`--surface`) girada −4°, `--brand-text`, sombra 2; "desde" en 12 px encima.
- Fotos usadas: `pexels-705255` (portada Studio Bella), `3993311` balayage, `3356170` corte, `2799605` tinte,
  `6503329` keratina, `3065171` peinado, `3993133/3065170/7440133` galería, retratos `3993312/3992875/7440133-square`,
  portada plataforma `1813272` (barbería), ejemplos `7518728`, `705255`, `16363470`.

## Movimiento
- Entrada: subida de 12 px + opacidad, 250 ms, `cubic-bezier(.22,1,.36,1)`, escalonada 60 ms por elemento, una vez.
- Tarjetas: suben 4 px y pasan a `--shadow-2` al pasar el dedo o el mouse (150 ms). Botones y chips: `scale(.97)` al presionar.
- Día elegido: sube 4 px y se llena con el degradado de marca. Punto "Abierto" late 2 veces y se detiene.
- Solo `transform` y `opacity`; con `prefers-reduced-motion` no hay desplazamientos.

## Cómo entra el color de marca de cada salón
1. El servidor (`BrandColor::tokens`) ya devuelve `primary` y `foreground` para claro y oscuro: se mapean a
   `--brand` y `--brand-ink`. Todo lo demás (`--brand-2`, `--brand-soft`, `--brand-text`, halos, sombra de marca)
   se deriva en CSS. Cambiar de salón = cambiar una variable (captura `salon-390-marca-verde.png`).
2. Cualquier bloque puede tener su propia marca con `data-brand style="--brand:…"` (así se muestran los tres
   salones de ejemplo en la portada de la plataforma: grafito, frambuesa y ámbar).
3. La plataforma (portada para dueños) usa la frambuesa por defecto como su propia marca.

## Capturas
`portada-390`, `portada-1440`, `salon-390`, `salon-1440`, `salon-390-oscuro`, `salon-390-marca-verde`,
`reserva-390`, y además `salon-390-primer-pantallazo` y `reserva-390-primer-pantallazo` (lo que se ve sin
hacer scroll, con la barra fija). En las capturas de página completa las barras fijas aparecen al final.
