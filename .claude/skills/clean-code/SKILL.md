---
name: clean-code
description: Orden y limpieza del código de peluWeb — dónde va cada archivo (backend Laravel y frontend React), cómo se nombran las cosas, tamaño máximo de clases y componentes, qué capa hace qué, y la lista de revisión antes de dar algo por terminado. Cargar SIEMPRE antes de crear archivos nuevos, refactorizar o revisar código. Complementa a `laravel-best-practices` (reglas generales de Laravel) — esta skill manda en estructura y nombres del proyecto.
---

# Código limpio en peluWeb

Regla cero: **consistencia antes que preferencia**. Antes de crear algo, mira un archivo hermano
y copia su forma. Si esta skill y el código existente chocan, se discute y se actualiza la skill;
no se introduce una segunda forma de hacer lo mismo.

## 1. Dónde va cada cosa — backend

Se usa la estructura estándar de Laravel, **agrupada por dominio dentro de cada carpeta**
(`Booking`, `Salon`, `Staff`, `Catalog`, `Accounting`, `Billing`, `Reviews`, `Platform`).

| Qué | Dónde | Ejemplo |
|---|---|---|
| Recibir la petición, autorizar, llamar a una Action, devolver respuesta | `app/Http/Controllers/{Area}/` | `Owner/ServiceController` |
| Validación de entrada | `app/Http/Requests/{Area}/` | `Owner/StoreServiceRequest` |
| **Una operación de negocio** (escribe en la base, dispara eventos) | `app/Actions/{Dominio}/` — verbo + sustantivo, método `handle()` | `Actions/Booking/CreateBooking` |
| Lógica pura sin base de datos ni `now()` interno (cálculos) | `app/Domain/{Dominio}/` | `Domain/Booking/AvailabilityCalculator` |
| Modelos, relaciones, casts, scopes | `app/Models/` | `Booking` |
| Estados y listas cerradas | `app/Enums/` (backed enums con `label()` en español) | `BookingStatus` |
| Permisos | `app/Policies/` | `BookingPolicy` |
| Rasgos reutilizables | `app/Concerns/` | `BelongsToSalon` |
| Datos que viajan entre capas | `app/Data/{Dominio}/` (clases `readonly`) | `Data/Booking/SlotRequest` |
| Formato de salida a Inertia/API | `app/Http/Resources/` | `BookingResource` |
| Valores con reglas (dinero, color) | `app/Support/` | `Support/Money` |

Áreas de controladores: `Public` (visitante), `Client`, `Staff`, `Owner`, `Platform` (super admin
fuera de Filament), `Api/V1`.

**Flujo obligatorio:** `Request (valida) → Controller (autoriza) → Action (hace) → Model`.
- El controlador **no** contiene lógica de negocio: ≤ 15 líneas por método.
- Una Action hace **una** cosa y se puede llamar desde un controlador, un Job, un comando o un test.
- Nada de `app/Services/` "cajón de sastre": si no es una Action ni lógica pura, probablemente
  pertenece al modelo (scope, accessor) o a `Support/`.

## 2. Dónde va cada cosa — frontend (`resources/js`)

| Qué | Dónde |
|---|---|
| Páginas Inertia | `pages/{public,client,staff,owner}/...` (una carpeta por área, igual que los controladores) |
| Componentes de dominio | `components/{booking,salon,schedule,accounting,...}/` |
| Primitivas shadcn | `components/ui/` (no editar a mano salvo para tokens) |
| Lógica de estado reutilizable | `hooks/use-*.ts` |
| Utilidades puras (formatos de dinero, horas) | `lib/` |
| Tipos compartidos | `types/` |
| URLs | **siempre** las funciones generadas por Wayfinder (`@/routes`, `@/actions`); nunca escribir `'/salons/' + id` |

Archivos en **kebab-case** (`booking-summary.tsx`), componentes en **PascalCase**
(`BookingSummary`), hooks `useAlgo`.

## 3. Tamaños máximos (si se pasa, se divide)
| Elemento | Máximo |
|---|---|
| Método PHP | 30 líneas |
| Clase PHP (excepto modelos) | 200 líneas |
| Componente React | 150 líneas — extraer subcomponentes o un hook |
| Parámetros de una función | 4 — si son más, usar un objeto `Data` / un tipo |
| Niveles de anidación (`if` dentro de `if`…) | 2 — usar retornos tempranos |

## 4. Nombres
- **Código en inglés, textos de UI en español neutro.** Nunca mezclar dentro de un identificador.
- Booleanos: `is*`, `has*`, `can*` (`isVip`, `hasDeposit`).
- Fechas: sufijo que diga la zona si hay duda (`startsAtUtc`, `localDate`).
- Dinero: sufijo `Cents` y tipo `int` (`priceCents`). Nunca `float` para dinero.
- Nada de abreviaturas crípticas (`bk`, `srv`, `tmp2`). Nombres que se lean en voz alta.
- Tablas en plural snake_case (`booking_segments`); columnas snake_case; tablas pivote en orden
  alfabético (`service_staff`).

## 5. Reglas de estilo que el linter no ve
- **Sin números mágicos:** `30` minutos de retención → constante o configuración del salón.
- **Sin strings mágicos de estado:** usar el enum (`BookingStatus::Confirmed`), nunca `'confirmed'`.
- **Retornos tempranos** en lugar de `else` largos.
- **Sin código comentado** ni `dd()`/`dump()`/`console.log` olvidados.
- **Comentarios:** solo el *por qué*, nunca el *qué* (el código ya dice el qué). Español o inglés
  según el archivo vecino; en PHP/TS el proyecto usa inglés.
- **Tipos siempre:** tipos de retorno y de parámetros en todo método PHP; nada de `any` en TS.
- **Sin consultas en bucles** (N+1): `with()` / `load()`; en desarrollo `Model::shouldBeStrict()`
  hace que falle ruidosamente.
- **Errores:** las Actions lanzan excepciones de dominio con nombre (`SlotNoLongerAvailable`);
  el controlador las traduce a mensajes amables. Nunca tragar excepciones con un `catch` vacío.
- **Transacciones** cuando una operación escribe en más de una tabla.
- **Inmutabilidad:** fechas `CarbonImmutable` (ya configurado); `readonly` en clases de datos.

## 6. Tests
- Un test por Action y por regla de negocio; nombre descriptivo en español o inglés consistente
  con el archivo: `it('rejects a booking that overlaps another one')`.
- Estructura **Arrange / Act / Assert** separada por líneas en blanco.
- Factories con estados con nombre (`Booking::factory()->confirmed()`), no arrays sueltos.
- Lógica pura (`app/Domain`) → tests unitarios sin base de datos.

## 7. Herramientas que lo hacen cumplir
| Herramienta | Qué revisa | Comando |
|---|---|---|
| Pint | formato PHP | `composer lint` |
| PHPStan / Larastan nivel 7 | tipos y errores PHP | `composer types:check` |
| Vite+ (oxlint + formateador) | lint y formato TS/React | `npm run check` |
| TypeScript | tipos frontend | `npm run types:check` |
| Todo junto + tests | — | `composer ci:check` |
| Skill integrada `/simplify` | repeticiones y código mejorable en lo modificado | al final de cada paso |
| Skill integrada `/code-review` | bugs en el diff | antes de cada commit importante |

## 8. Lista antes de dar algo por terminado
1. ¿Cada archivo nuevo está en la carpeta de la tabla 1 o 2?
2. ¿El controlador solo coordina? ¿La lógica está en una Action o en `Domain`?
3. ¿Algún método, clase o componente supera la tabla 3?
4. ¿Quedan números o strings mágicos, `dd`, `console.log` o código comentado?
5. ¿Las URLs del frontend salen de Wayfinder?
6. ¿Hay tests nuevos para el comportamiento nuevo?
7. ¿`composer ci:check` pasa?
8. Correr `/simplify` sobre lo cambiado.
