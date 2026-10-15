# Decisión de stack (investigado 2026-10-04)

## Elegido: Laravel 13 + Inertia 3 + React 19 + TypeScript + MySQL (9.6 local / 8.4 en CI y producción)

Instalado y verificado el 2026-10-04: Laravel **13.34**, Inertia 3, React 19.2, Tailwind 4, Fortify
(verificación de email, registro, 2FA, passkeys), Wayfinder, Larastan nivel 7, Pint, Pest 4.7, Motion.
Ojo: en Packagist `laravel/react-starter-kit` estable sigue siendo la versión vieja (Laravel 12);
la buena es `laravel/react-starter-kit:dev-main`, que es la que usa el instalador oficial.

| Criterio | Laravel + Inertia/React | Next.js + Prisma |
|---|---|---|
| Auth, 2FA, rate limit | Incluidos en el starter kit (Fortify) | Montar a mano |
| Roles, colas, tareas programadas, correo | Nativo / Spatie | Librerías sueltas |
| Panel de super admin | Filament 5 (horas, no semanas) | A mano |
| MySQL | Ciudadano de primera | Prisma 7 necesita adaptador, con fricciones |
| Hosting en Perú | cPanel con PHP desde ~S/ 60–130 al año; VPS desde ~US$ 6/mes | Necesita Node (VPS o Vercel) |
| API para Flutter | Sanctum | A mano |
| UI moderna con animaciones | React + shadcn + Motion (igual que Next) | Igual |

Fuentes: laravel.com/docs/13.x/starter-kits · laravel-news.com/laravel-13-released ·
endoflife.date/laravel · filamentphp.com · prisma.io/docs/v7

## Entorno de desarrollo (instalado 2026-10-04)
Laravel 13 pide **PHP ≥ 8.3** y XAMPP solo trae PHP 8.2.12 y MariaDB 10.4. Por eso:

| Pieza | Dónde | Notas |
|---|---|---|
| **PHP 8.4.26** (NTS x64, ZIP oficial de windows.php.net, SHA-256 verificado) | `C:\php84` | **No** está en el PATH global, así XAMPP y tus otros proyectos siguen con PHP 8.2. En VS Code, el terminal de este proyecto lo antepone solo (`.vscode/settings.json`). |
| **MySQL 9.6** | servicio Windows `MySQL96`, puerto 3306 (ya estaba instalado) | Base `peluweb` + `peluweb_test`, usuario `peluweb` con acceso solo a esas dos. La contraseña está únicamente en `.env`. |
| Composer 2.9 | el que ya tenías | Llamarlo con PHP 8.4: `C:\php84\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar …` (o desde el terminal de VS Code, solo `composer`). |
| Node 24 | el que ya tenías | — |
| XAMPP | sin cambios | Se puede seguir usando para otros proyectos. Su MariaDB **no** se usa aquí. |

**Paridad con producción:** los hostings usan MySQL 8.0/8.4. El CI corre los tests contra
**MySQL 8.4**, así que si se usara algo exclusivo de 9.x, el CI lo detecta. Regla: no usar funciones
exclusivas de MySQL 9 (ej. tipo `VECTOR`, procedimientos JavaScript).

### Comandos del día a día (en el terminal de VS Code de este proyecto)
| Qué | Comando |
|---|---|
| Levantar todo (servidor + Vite + colas + logs) | `composer dev` → http://localhost:8000 |
| Tests | `php artisan test` |
| Chequeo completo como el CI | `composer ci:check` |
| Migrar | `php artisan migrate` |

Si usas otro terminal (fuera de VS Code), antes ejecuta: `$env:PATH = "C:\php84;$env:PATH"` (PowerShell).

## Doble reserva en MySQL
MySQL no tiene *exclusion constraints*. El patrón es:
1. Transacción.
2. `SELECT … FROM staff WHERE id=? FOR UPDATE`.
3. Revalidar el solape.
4. Insertar.
5. COMMIT, reintentando ante un deadlock (`DB::transaction($fn, 3)`).

`GET_LOCK` se descarta porque el bloqueo es por conexión y no protege a quien escribe sin pedirlo.
Ver la skill `booking-domain`.

## Móvil
1. **PWA** (paso 6.4 del plan, todavía sin construir): instalable en Android y en iOS ("Añadir a pantalla de inicio"). Web Push funciona
   en iOS ≥ 16.4 **solo si la app está instalada** y el usuario aceptó tras pulsar un botón.
2. **Flutter** (Fase 9, opcional): app de tienda que consume `/api/v1` con Sanctum y push por FCM.

## Mapas (costo cero)
- El dueño fija el pin en Leaflet + OpenStreetMap → se guardan `lat`/`lng`.
- El perfil público usa un iframe de **Maps Embed API** (ilimitado y gratis, necesita una clave
  restringida por dominio) más un botón "Cómo llegar":
  `https://www.google.com/maps/dir/?api=1&destination=lat,lng` (sin clave).
- Desde mar-2025 Google cobra por SKU, con cupos gratis de 10k/mes. Places Autocomplete solo si hace falta.

## WhatsApp
- v1: enlaces `wa.me` armados en el servidor (gratis, el envío es manual con un toque).
- Futuro: Cloud API de Meta, que cobra por plantilla entregada (en Perú, utility ~US$ 0.03 desde
  oct-2026, *confirmar en developers.facebook.com/docs/whatsapp/pricing*). Sería un extra de pago.

## Correo y tareas programadas
- Los correos de la reserva (recibida, confirmada, cancelada, reprogramada, recordatorio) salen por
  la **cola** (`QUEUE_CONNECTION=database`). En local `MAIL_MAILER=log`: se escriben en
  `storage/logs/laravel.log`, no se envían.
- **Notificaciones push (paso 6.5)**: librería `minishlink/web-push` (aprobada el 2026-10-12). Cada servidor
  genera su par de claves con `php artisan webpush:keys` y lo pega en su `.env` (`VAPID_PUBLIC_KEY`,
  `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`); sin claves no se envía nada y el interruptor no aparece. Las
  notificaciones salen por la cola, igual que los correos. **En Windows local** PHP necesita dos cosas para
  enviar de verdad: `OPENSSL_CONF` apuntando a `C:\php84\extras\ssl\openssl.cnf` (ya lo pone `dev.cmd`) y un
  archivo de autoridades certificadoras en `curl.cainfo` de `C:\php84\php.ini` (sin él, el envío falla con
  "cURL error 60"). En Linux (producción) no hace falta ninguna de las dos.
- Producción necesita dos tareas cada minuto (cron de cPanel o del VPS), con el PHP 8.4:
  `php artisan schedule:run` (vence retenciones cada 5 min, recordatorios cada 15 min, borrado de alergias y fotos vencidas una vez al día) y
  `php artisan queue:work --stop-when-empty --max-time=50` (o un `queue:work` permanente con
  supervisor en un VPS). Sin la segunda, los correos se quedan en la tabla `jobs`.
- `APP_LOCALE=es`: las fechas de los correos salen en español.
- Proveedor de correo: por elegir (requisito P12 de `docs/legal/requisitos-de-producto.md`).

## Calidad
- Pruebas: Pest 4 (incluye pruebas de navegador con Playwright).
- Análisis estático: Larastan nivel 6+ y Pint.
- Dependencias: `composer audit` / `npm audit`.
- Escaneo dinámico: OWASP ZAP baseline contra staging.
- Agentes del proyecto: ver `.claude/agents/`.
