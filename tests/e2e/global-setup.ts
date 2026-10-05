import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { testEnv } from '../../playwright.config';

const php =
    process.env.PHP ??
    (process.platform === 'win32' ? 'C:\\php84\\php.exe' : 'php');

/**
 * Fresh test database with the known e2e accounts before every run.
 */
export default function globalSetup(): void {
    // With public/hot present Laravel serves assets from the Vite dev server
    // and every page renders blank once it is stopped.
    if (existsSync('public/hot')) {
        throw new Error(
            'Existe public/hot: detén dev.cmd (o borra public/hot) y ejecuta "npm run build" antes de los e2e.',
        );
    }

    const env = { ...process.env, ...testEnv };

    execFileSync(php, ['artisan', 'migrate:fresh', '--force'], {
        env,
        stdio: 'inherit',
    });
    execFileSync(php, ['artisan', 'db:seed', '--class=E2eSeeder', '--force'], {
        env,
        stdio: 'inherit',
    });
}
