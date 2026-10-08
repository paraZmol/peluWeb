# peluWeb — SaaS de reservas y gestión para peluquerías, barberías y salones

Plataforma multi-salón: los salones pagan una suscripción para usarla. Roles: **super admin**
(dueño de la plataforma), **dueño** del salón, **personal** (peluquero/a, barbero/a), **cliente**.

## Fuente de verdad
- El plan vive en [docs/PLAN.md](docs/PLAN.md). Al terminar un paso, marcarlo `[x]` y anotar la
  fecha. **Si una decisión cambia, se cambia primero el plan** (sección "Registro de cambios")
  y después el código. Nada se construye si no está en el plan.
- Catálogo de servicios para la semilla: [docs/catalogo-servicios.md](docs/catalogo-servicios.md).
- Investigación de mercado y precios: [docs/mercado.md](docs/mercado.md).

## Stack fijo (decidido 2026-10-04, no re-litigar sin pasar por el plan)
- **Laravel 13** (PHP ≥ 8.3) + **Inertia 3 + React 19 + TypeScript** (starter kit oficial)
- **Tailwind 4 + shadcn/ui + Motion** (`motion/react`) — ver skill `ui-system`
- **MySQL**: 9.6 local (servicio `MySQL96`), 8.4 en CI y producción → no usar nada exclusivo de 9.x.
  No usar la MariaDB de XAMPP. Montos en céntimos `INT`, fechas en UTC.
- **Filament 5** solo para el panel del super admin. Los paneles de dueño/personal son React.
- Roles **por salón con tabla propia** (`salon_user`: rol + permisos; ver `SalonMember`), sin spatie.
  Imágenes con `App\Support\Images\ImageStore` (Intervention, WebP). Sanctum para la futura app Flutter.
- E2E: **Playwright Test** (`npm run e2e`, `tests/e2e`), no pest-plugin-browser (se cuelga en Windows).
- Multi-tenant en **una sola base** con columna `salon_id` + trait `BelongsToSalon` (global scope).
- PWA primero (Android e iOS instalables). Flutter en una fase posterior consumiendo `/api/v1`.
- Mapas: pin con Leaflet + OSM para fijar, iframe Maps Embed (gratis) + enlace "Abrir en Google Maps".
- Pagos v1: **WhatsApp `wa.me` + confirmación manual** detrás de la interfaz `PaymentProvider`.

## Entorno local (Windows) — leer antes de ejecutar comandos
- PHP del proyecto: **`C:\php84\php.exe`** (8.4). El `php` del PATH global es el 8.2 de XAMPP y
  **falla** con Laravel 13 (incluido `npm run build`, que llama a php por Wayfinder).
  En Bash anteponer `export PATH=/c/php84:$PATH`; en PowerShell `$env:PATH = "C:\php84;$env:PATH"`.
- Composer: `php C:/ProgramData/ComposerSetup/bin/composer.phar` con el PHP de arriba.
- No tocar XAMPP ni el PATH global del usuario. Detalles en [docs/stack.md](docs/stack.md).
- `.env` tiene la contraseña del usuario MySQL `peluweb`; nunca imprimirla ni copiarla a otro archivo.
- Tests contra MySQL (`peluweb_test`), no SQLite: la agenda depende de bloqueos reales.

## Reglas de arquitectura
1. **Aislamiento entre salones**: todo modelo de negocio usa `BelongsToSalon`. Cada controlador
   autoriza con Policy. Un test por recurso demuestra que el salón A no ve/edita lo del salón B
   **atacando la URL con el id ajeno** (`/equipo/invitaciones/{id-de-otro-salón}` → 404).
   `ResolveCurrentSalon` corre antes de `SubstituteBindings` (bootstrap/app.php); sin eso el
   binding encuentra registros de otros salones (bug real atrapado el 2026-10-04).
   Modelos sin `BelongsToSalon` (ej. `SalonMember`) se comprueban a mano contra `CurrentSalon`.
2. **Agenda**: disponibilidad en una sola clase pura `AvailabilityCalculator`; guardado en
   transacción con `lockForUpdate()` sobre el profesional. Cargar skill `booking-domain` antes de tocarla.
3. **La autoridad es el servidor**: ocultar un botón no es seguridad. Lo VIP, los precios y los
   permisos se filtran en el backend.
4. **Dinero**: enteros en céntimos, moneda por salón (`PEN` por defecto). Nunca `float`.
5. **Imágenes**: validar tipo y tamaño, re-codificar a WebP, quitar EXIF, nunca aceptar SVG.
6. **Sin secretos en git**: `.env`, claves de Maps/WhatsApp fuera del repo.
7. Campos nuevos en tablas con datos: siempre `nullable` o con `default` (los registros viejos
   — la cuenta del super admin, el primer salón — son los que se rompen primero).

## Convenciones
- Código, nombres de tablas y comentarios en **inglés**; textos de UI en **español neutro**
  (sin asumir género de clientes ni del personal).
- **Orden y limpieza: cargar la skill `clean-code` antes de crear archivos o refactorizar.** Resumen:
  `Request → Controller (fino) → Action (app/Actions/{Dominio}) → Model`; lógica pura en
  `app/Domain/{Dominio}`; páginas en `resources/js/pages/{public,client,staff,owner}`; URLs con Wayfinder.
- Las skills de Boost (`laravel-best-practices`, `inertia-react-development`, `testing-best-practices`, …)
  dan las reglas generales; `clean-code`, `booking-domain` y `ui-system` mandan en lo propio del proyecto.
- Despliegue: hosting cPanel peruano o VPS (ver docs/stack.md). **No** se usa Laravel Cloud aunque
  las guías de Boost lo mencionen.
- Arrancar en local: `.\dev.cmd` (usa PHP 8.4 aunque el terminal tenga el de XAMPP).

## Calidad — antes de dar un paso por terminado
- `composer ci:check` en verde (formato, lint, tipos TS, Pint, PHPStan nivel 7 y tests).
- Lista de la sección 8 de la skill `clean-code` y `/simplify` sobre lo cambiado.
- Lanzar el agente **qa-tester** sobre el paso. Si tocó agenda → **booking-breaker**.
  Si tocó auth, roles, subidas, dinero o consultas por salón → **security-auditor**.
  Si tocó una pantalla → **visual-designer** (mira capturas y corrige la presentación para que
  se vea profesional) y después **ux-reviewer** (solo opina).
- Tests rápidos: `php artisan test --parallel --processes=6 --exclude-testsuite=Concurrency` (~1 min) y
  después `php artisan test --testsuite=Concurrency` (va solo: compite por bloqueos reales).
- Al cerrar cada fase: todos los agentes, y corregir CRÍTICO/ALTO antes de seguir. Dos corridas de Playwright a la vez
  necesitan carriles distintos: `E2E_PORT`, `E2E_DB_DATABASE` (el usuario `peluweb` puede crear bases
  `peluweb_test%`) y `E2E_OUTPUT`; sin eso comparten `peluweb_test` y el puerto 8123 y se pisan; los de solo
  lectura (security-auditor, ux-reviewer) sí pueden ir en paralelo.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
