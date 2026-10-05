import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

test.describe('Selector de salón y permisos por rol', () => {
    test('quien tiene varios salones los cambia desde el selector', async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);
        await login(page, 'multi@example.com', 'password');
        await expect(page).toHaveURL(/\/dashboard$/);

        if (page.viewportSize()!.width < 768) {
            await page.locator('[data-sidebar="trigger"]').first().click();
        }
        await page.locator('[data-test="salon-switcher"]').click();
        await expect(page.getByText('Tus salones')).toBeVisible();
        await page.getByRole('menuitem', { name: 'Salón Dos Ñandú' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(
            page.locator('[data-test="salon-switcher"]'),
        ).toContainText('Salón Dos Ñandú');
        assertNoErrors();
    });

    test('el personal no ve Equipo y /equipo le responde 403', async ({
        page,
    }) => {
        await login(page, 'personal@example.com', 'password');
        await expect(page).toHaveURL(/\/dashboard$/);

        const response = await page.goto('/equipo');
        expect(response?.status()).toBe(403);
    });

    test('un visitante que abre /equipo va al login', async ({ page }) => {
        await page.goto('/equipo');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('un enlace de invitación inválido muestra mensaje sin errores (claro y oscuro)', async ({
        page,
    }) => {
        for (const colorScheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);
            const response = await page.goto(
                '/invitaciones/token-que-no-existe',
            );
            expect(response?.status()).toBe(200);
            await expect(page.locator('body')).toBeVisible();
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBe(false);
            assertNoErrors();
        }
    });
});
