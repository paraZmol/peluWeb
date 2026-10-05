---
name: ui-system
description: Sistema de diseño de peluWeb — tokens de color claro/oscuro, marca personalizable por salón (logo y color), tipografía, componentes shadcn/ui, reglas de animación con Motion y patrones de pantalla móvil. Cargar antes de crear o modificar cualquier pantalla, componente o estilo.
---

# Sistema de diseño

## Dirección visual
"Salón editorial": limpio, cálido y con aire premium, sin recargar. Mucho espacio en blanco,
fotos del salón como protagonistas, un único color de acento que **pone cada salón**.
La plataforma tiene su propia marca neutra; en las páginas de un salón manda la marca del salón.

## Tokens (CSS variables en `resources/css/app.css`, consumidas por Tailwind 4 / shadcn)
Nunca escribir colores a mano en componentes (`bg-[#...]`, `text-gray-500`): solo tokens.

| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--background` | `#FAF8F5` (hueso) | `#121110` (carbón cálido) | fondo de página |
| `--card` | `#FFFFFF` | `#1C1A18` | tarjetas, hojas |
| `--foreground` | `#1F1B18` | `#F2EEE9` | texto principal |
| `--muted-foreground` | `#6B635C` | `#A39A91` | texto secundario |
| `--border` | `#E8E2DB` | `#2E2A26` | bordes |
| `--primary` | marca del salón (def. `#8F5E45` cobre, 5.4:1 con blanco) | def. `#D4A58A` con texto oscuro | botón principal, foco |
| `--success` / `--warning` | `#15803D` / `#B45309` | `#4ADE80` / `#FBBF24` | estados (texto encima según `*-foreground`) |

**Marca por salón**: el dueño elige logo y color. En el servidor se calcula `--primary` y
`--primary-foreground` (blanco o negro según contraste WCAG ≥ 4.5) y una versión para oscuro.
Si el color no alcanza contraste, se ajusta la luminosidad automáticamente y se avisa al dueño.
El logo se guarda en dos variantes opcionales (fondo claro / fondo oscuro).

## Tipografía
- Títulos: **Fraunces** (serif con carácter, peso 500–600). Solo en títulos y nombre del salón.
- Interfaz y texto: **Inter** (400/500/600). Números tabulares en precios y horas (`tabular-nums`).
- Escala: 12 / 14 / 16 (base) / 18 / 22 / 28 / 36. Nunca texto de cuerpo < 14 px en móvil.

## Componentes
- Base: **shadcn/ui** (ya en el starter kit). Extender, no reescribir.
- Iconos: **lucide-react**, trazo 1.75.
- Radios: `--radius: 14px` en tarjetas, botones píldora para acciones principales.
- Selector de hora estilo **alarma** (`TimeWheel`): ruedas de hora/minuto con scroll-snap y
  saltos de 5 min; en escritorio también acepta teclado. Usarlo para horarios del personal.
- Calendario de reserva: tira horizontal de días (scroll) + grilla de horarios en chips
  agrupados por Mañana / Tarde / Noche. Nada de dropdowns para elegir hora.
- Agenda del profesional: vista "Hoy" en lista vertical con la cita actual resaltada y la
  siguiente con cuenta regresiva; vista semana con columnas por profesional en escritorio.
- Hojas inferiores (`Drawer`) en móvil en vez de modales centrados.
- Gráficos: componentes chart de shadcn (Recharts) usando los tokens; ver skill `dataviz` si está.

## Animación (Motion — `motion/react`)
- Duraciones: micro 150 ms, transición 250 ms, celebración ≤ 600 ms. Curva `[0.22, 1, 0.36, 1]`.
- Animar solo `transform` y `opacity` (rendimiento en celulares de gama baja).
- Momentos con animación definida:
  - **Login/registro**: tarjeta entra con fade + subida de 12 px; error = sacudida horizontal
    corta (3 ciclos, 200 ms); éxito = el botón se transforma en check antes de navegar.
  - **Reserva**: avance entre pasos con slide lateral; al elegir hora, el chip se expande hacia
    el resumen (`layoutId`); al confirmar, check dibujado (SVG path) + confeti discreto 1 vez.
  - **Agenda**: citas nuevas entran con fade; arrastrar para mover con sombra elevada.
  - **Cambio de tema**: transición de color 200 ms en `background-color`/`color`.
- Siempre respetar `useReducedMotion()`: sin desplazamientos, solo fades cortos.
- Nunca bloquear un botón mientras anima; la acción ocurre de inmediato.

## Patrones móviles
- Diseñar a 360 px primero; acción principal fija abajo (zona del pulgar), ≥ 44 px de alto.
- Navegación inferior por rol: Cliente (Explorar, Mis reservas, Perfil) · Personal (Hoy,
  Agenda, Clientes, Más) · Dueño (Hoy, Agenda, Caja, Reportes, Más).
- Estados obligatorios en cada lista: skeleton, vacío con acción sugerida, error con reintento.

## Textos
Español neutro LATAM, tuteo. Sin asumir género ("tus clientes", "quien te atiende", "tu equipo").
Sin jerga: "horario" no "slot", "seña" o "adelanto" según configuración del salón.
Precios `S/ 35.00`; horas `3:30 p. m.`; fechas "mar 14 oct".
