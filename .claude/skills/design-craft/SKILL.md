---
name: design-craft
description: Oficio visual de peluWeb — cómo se hace una pantalla atrayente para quien reserva y para quien da de alta su peluquería; profundidad, fotografía, tipografía, color de marca por salón, movimiento, patrones de conversión y lo que está prohibido (plano, genérico, paleta del chat de Claude). Cargar junto con `ui-system` antes de diseñar, maquetar o construir cualquier pantalla pública o de alta de salón, y al revisar capturas.
---

# Oficio visual (design-craft)

`ui-system` dice **qué** tokens, fuentes y componentes usa el producto. Esta skill dice **cómo** se
usan para que una pantalla tenga energía de peluquería y convierta. Si chocan, manda esta en
lo público y `ui-system` en los paneles internos.

## 1. Para quién diseñamos y qué tiene que sentir
| Pantalla | Quién la ve | Qué debe sentir en 2 segundos |
|---|---|---|
| Portada, registro, alta del salón | Dueño de peluquería/barbería/salón de uñas en Perú, en el celular, entre clientes | "Esto es para negocios como el mío, se ve serio y moderno, lo armo en 10 minutos" |
| Página pública del salón | Cliente que llega por Instagram o WhatsApp | "Este salón es bueno, quiero ir, reservar es un toque" |
| Asistente de reserva | Mismo cliente, con prisa | "Ya casi está, no me van a complicar" |
| Mis reservas | Cliente después de reservar | "Todo claro: cuándo, con quién, cuánto, cómo llego" |

Los paneles internos (agenda, Mi salón, Equipo) heredan tokens y tipografía, pero su virtud es
la velocidad; no se decoran.

## 2. Lo prohibido (por experiencia, 2026-10-08)
- **Plano y genérico**: fondos de un solo tono con tarjetas blancas de borde gris, sin fotos, sin
  jerarquía. Se ve como una plantilla de panel de administración.
- **La paleta del chat de Claude servida plana**: hueso `#FAF8F5` con acento cobre/terracota y
  serif suave, sin fotos ni profundidad. El usuario la reconoció al instante y la rechazó. Después,
  al verla con fotos reales, decidió conservarla en dos sitios y solo ahí: la identidad de peluWeb
  en modo claro y la plantilla Editorial. No es la base de ninguna otra plantilla ni de una
  dirección nueva, y donde se usa va siempre con fotos, tres planos de profundidad y jerarquía.
- Colores escritos a mano en componentes; fotos deformadas o pixeladas; texto sobre foto sin
  velo de contraste; íconos de distintas familias; tres botones primarios en una vista.
- Un asistente de reserva "de formulario": campos y selects donde deberían ir chips, tiras de
  días y tarjetas con foto.

## 3. Principios
1. **La foto manda.** Portada del salón a sangre, galería real, retrato de cada profesional,
   el servicio con imagen cuando la tenga. Las fotos van con proporciones fijas (16:9, 4:5, 1:1),
   `object-cover`, esquinas coherentes y, cuando llevan texto encima, un degradado
   (`from-black/70 to-transparent`) o un velo de color de marca.
2. **Profundidad, no ruido.** Tres planos: fondo, superficie, elemento elevado. Se logra con
   sombras suaves de dos capas, degradados sutiles derivados del color de marca, bordes finos
   translúcidos y, en direcciones oscuras, brillos y desenfoques (`backdrop-blur`). Nunca con
   bordes grises gruesos ni sombras duras.
3. **El color de marca es del salón.** `--primary` lo pone cada salón; el sistema deriva tonos
   (`primary/10`, `primary/20`, degradado `from-primary to-primary-deep`) y el contraste del texto.
   Toda pantalla pública debe verse bien con un color oscuro (#1F2937), uno vivo (#E11D48) y uno
   claro (#F59E0B): se prueba con los tres antes de dar por buena una pantalla.
4. **Tipografía con carácter.** Una display reconocible para títulos y nombre del salón, una
   sans legible para el cuerpo; escala grande en móvil (título 32–40 px), números tabulares en
   precios y horas, mayúsculas espaciadas solo en etiquetas cortas.
5. **Una acción por pantalla, al alcance del pulgar.** Botón primario fijo abajo en móvil,
   ≥ 48 px, con el precio o el resumen al lado cuando ayuda a decidir.
6. **Movimiento con sentido.** Entradas escalonadas de 150–250 ms, chips que responden al toque,
   el check dibujado al confirmar, confeti una sola vez. Nada que bloquee ni que se repita en
   cada scroll. Siempre `useReducedMotion()`.
7. **Español neutro de Perú**, tuteo, sin género: "tus clientes", "quien te atiende", "tu equipo".

## 4. Patrones que convierten
**Portada (dueños):** titular con beneficio concreto ("Tu agenda llena, sin WhatsApp a medianoche"),
foto o maqueta del producto en un celular, prueba social (salones reales de la demo), 3 pasos
("Crea tu salón → comparte tu enlace → recibe reservas"), precio claro y CTA repetido. Un bloque
"Así se ve tu página" con la página pública real de un salón de demostración.

**Página pública del salón:** portada a sangre con nombre, tipo, distrito y estado (abierto/cierra
a las…), botón "Reservar" visible sin scroll; servicios como tarjetas con precio y duración
y "Reservar" por servicio; equipo con retratos; galería en mosaico; horario; mapa; WhatsApp.

**Reserva:** progreso visible, cada paso con una sola pregunta como título, chips grandes, resumen
que se va construyendo, tiempo y precio siempre a la vista, confirmación con celebración y
siguiente paso (seña o cómo llegar).

## 5. Proceso
1. **Dirección antes que píxeles.** Un rediseño empieza con 2–3 direcciones maquetadas con las
   fotos reales (`database/seeders/images/`) y capturas a 390 px y 1440 px; el usuario elige.
2. La elegida se vuelve spec: `ui-system` (tokens, fuentes, componentes) + un `README` de la
   dirección con paleta, tipografía, radios, sombras y movimiento.
3. `frontend-builder` construye por pantalla, con su propio carril de Playwright, y
   `visual-designer` compara capturas contra la maqueta antes de dar por terminado.
4. Nunca se mezclan dos direcciones en una pantalla.

## 6. Imágenes de demostración
- Viven en `database/seeders/images/` y las cargan `DemoSeeder`, `DemoScheduleSeeder` y
  `E2eSeeder`. Cada archivo está en `database/seeders/images/CREDITS.md` con origen, autor y
  licencia. Solo licencias que permitan uso comercial y modificación (Pexels, Unsplash, CC0,
  CC BY); nunca una foto "encontrada en Google".
- Formato JPEG ≤ 1600 px y ≤ 400 KB; retratos ya recortados a cuadrado (`-square.jpg`).
- Los logos de demostración se generan (monograma sobre el color de marca); no se usan logos reales.

## 7. Lista antes de dar por buena una pantalla pública
1. ¿Se entiende en 2 segundos qué es y qué hacer? ¿La acción principal está bajo el pulgar?
2. ¿Hay al menos una foto real protagonista y ninguna deformada?
3. ¿Se ve bien con los tres colores de marca de prueba y en claro y oscuro?
4. ¿Hay profundidad (tres planos) sin bordes grises ni sombras duras?
5. ¿Alguien diría "esto parece el chat de Claude" o "parece un panel de admin"? Si sí, no está.
6. ¿360 px sin desbordes, textos ≥ 14 px, contraste AA?
7. Capturas antes/después guardadas y comparadas con la maqueta de la dirección.
