import { expect, type Page } from '@playwright/test';

export const owner = { email: 'duena@example.com', password: 'password' };

/**
 * Fails the test on any uncaught error or console.error in the page.
 */
export function watchForErrors(page: Page): () => void {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    return () => expect(errors, 'errores de JavaScript').toEqual([]);
}

export async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
}
