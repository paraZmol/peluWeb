import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests in a real browser against `php artisan serve`, using the
 * test database (never the development one). See tests/e2e.
 *
 * On Windows the project PHP lives in C:\php84 (XAMPP's PHP is too old);
 * elsewhere `php` from PATH is used. Override with the PHP env variable.
 */
const php =
    process.env.PHP ??
    (process.platform === 'win32' ? 'C:\\php84\\php.exe' : 'php');
const port = 8123;
const baseURL = `http://127.0.0.1:${port}`;

export const testEnv = {
    APP_ENV: 'local',
    APP_URL: baseURL,
    DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'peluweb_test',
    MAIL_MAILER: 'log',
    QUEUE_CONNECTION: 'sync',
    // Rate limits live in the cache: with `array` they reset on every request,
    // so the many logins of the suite are not throttled (5 per minute).
    CACHE_STORE: 'array',
};

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL,
        locale: 'es-PE',
        timezoneId: 'America/Lima',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'movil', use: { ...devices['Pixel 7'] } },
        { name: 'escritorio', use: { viewport: { width: 1440, height: 900 } } },
    ],
    webServer: {
        command: `"${php}" artisan serve --host=127.0.0.1 --port=${port}`,
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: testEnv,
    },
});
