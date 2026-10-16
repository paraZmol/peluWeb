import { expect, test } from '@playwright/test';
import { login, owner, watchForErrors } from './support';

test.describe('Dueño nuevo', () => {
    test('entra, crea su salón e invita a su primer barbero', async ({
        page,
    }, testInfo) => {
        // One salon per account in this flow: only run it on one device.
        test.skip(testInfo.project.name !== 'movil', 'flujo único en móvil');
        const assertNoErrors = watchForErrors(page);

        await login(page, owner.email, owner.password);
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByText('Hola, Rosa')).toBeVisible();

        await page.getByRole('link', { name: 'Crear mi salón' }).click();
        await expect(page).toHaveURL(/\/salones\/nuevo$/);
        await page.getByLabel('Nombre del salón').fill('Barbería Don Lucho');
        await page.getByText('Barbería', { exact: true }).click();
        await page.getByText('Hombres', { exact: true }).click();
        await page.getByLabel(/WhatsApp del salón/).fill('+51 999 888 777');
        // Casilla 5: without it the server refuses the salon.
        await page.getByRole('button', { name: 'Crear mi salón' }).click();
        await expect(
            page.getByText(/acepta los Términos para salones/),
        ).toBeVisible();
        await page
            .getByRole('checkbox', { name: /Términos para salones/ })
            .check();
        await page.getByRole('button', { name: 'Crear mi salón' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(
            page.getByText('Barbería Don Lucho ya está creado'),
        ).toBeVisible();

        await page.getByRole('link', { name: /Tu equipo/ }).click();
        await expect(page).toHaveURL(/\/equipo$/);
        await page.getByLabel('Correo electrónico').fill('barbero@example.com');
        await page.getByRole('button', { name: 'Enviar invitación' }).click();

        await expect(
            page.getByText('Invitación enviada a barbero@example.com'),
        ).toBeVisible();
        await expect(page.getByText('Invitaciones pendientes')).toBeVisible();
        assertNoErrors();
    });
});

test('una contraseña incorrecta muestra el error sin salir del login', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    const assertNoErrors = watchForErrors(page);

    await login(page, owner.email, 'incorrecta');

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText(/credenciales no coinciden/)).toBeVisible();
    assertNoErrors();
});

for (const colorScheme of ['light', 'dark'] as const) {
    test(`páginas públicas sin errores (${colorScheme})`, async ({ page }) => {
        await page.emulateMedia({ colorScheme });
        const assertNoErrors = watchForErrors(page);

        for (const path of [
            '/',
            '/login',
            '/register',
            '/legal/terminos-clientes',
            '/legal/terminos-salones',
            '/legal/privacidad',
            '/legal/cookies',
        ]) {
            await page.goto(path);
            await expect(page.locator('body')).toBeVisible();
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(overflow, `scroll horizontal en ${path}`).toBe(false);
        }

        assertNoErrors();
    });
}

test('el registro pide aceptar los términos y el pie los enlaza', async ({
    page,
}, testInfo) => {
    const assertNoErrors = watchForErrors(page);
    const email = `nuevo-${testInfo.project.name}-${Date.now()}@example.com`;

    await page.goto('/register');
    await page.getByLabel('Nombre').fill('Ale Nuevo');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña', { exact: true }).fill('password-Segura-1');
    await page.getByLabel('Confirmar contraseña').fill('password-Segura-1');
    await page.getByRole('button', { name: /Crear mi cuenta/ }).click();
    await expect(page.getByText(/marca que leíste y aceptas/)).toBeVisible();

    await page.getByRole('checkbox', { name: /Leí y acepto/ }).check();
    await page.getByLabel('Contraseña', { exact: true }).fill('password-Segura-1');
    await page.getByLabel('Confirmar contraseña').fill('password-Segura-1');
    await page.getByRole('button', { name: /Crear mi cuenta/ }).click();
    await expect(page).not.toHaveURL(/\/register$/);
    await expect(page).not.toHaveURL(/\/legal\/aceptar$/);

    const footer = page.getByRole('navigation', { name: 'Legal' }).last();
    await footer.getByRole('link', { name: 'Política de privacidad' }).click();
    await expect(page).toHaveURL(/\/legal\/privacidad$/);
    await expect(
        page.getByRole('heading', { name: 'Política de privacidad' }),
    ).toBeVisible();
    await expect(page.getByText(/Versión 1 · vigente desde/)).toBeVisible();
    assertNoErrors();
});
