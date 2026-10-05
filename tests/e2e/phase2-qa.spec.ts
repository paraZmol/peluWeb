import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
const staff = { email: 'personal@example.com', password: 'password' };

test.describe('Fase 2 · acceso por rol', () => {
    test('un visitante es enviado al login desde /mi-salon y /servicios', async ({
        page,
    }) => {
        for (const path of ['/mi-salon', '/servicios']) {
            await page.goto(path);
            await expect(page).toHaveURL(/\/login/);
        }
    });

    test('personal sin permisos no entra a /mi-salon ni /servicios', async ({
        page,
    }) => {
        await login(page, staff.email, staff.password);
        await expect(page).toHaveURL(/dashboard$/);
        for (const path of ['/mi-salon', '/servicios']) {
            const response = await page.goto(path);
            expect(response?.status()).toBe(403);
        }
    });

    test('una página pública inexistente o ajena responde 404', async ({
        page,
    }) => {
        const response = await page.goto('/s/no-existe-nunca');
        expect(response?.status()).toBe(404);
    });
});

for (const colorScheme of ['light', 'dark'] as const) {
    test.describe(`Fase 2 · pantallas del dueño (${colorScheme})`, () => {
        test(`/mi-salon, /servicios y la página pública sin errores ni scroll horizontal`, async ({
            page,
        }) => {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);
            await login(page, multi.email, multi.password);
            await expect(page).toHaveURL(/dashboard$/);

            for (const path of [
                '/dashboard',
                '/mi-salon',
                '/servicios',
                '/s/salon-uno',
            ]) {
                await page.goto(path);
                await page.waitForLoadState('networkidle');
                const overflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        document.documentElement.clientWidth,
                );
                expect(overflow, `scroll horizontal en ${path}`).toBe(false);
            }
            assertNoErrors();
        });
    });
}

test.describe('Fase 2 · servicio propio de punta a punta', () => {
    test('crear con precio "35,50", nombre con ñ y emoji, pausar y volver a mostrar', async ({
        page,
    }, testInfo) => {
        const name = `Peinado ñandú ✨ ${testInfo.project.name}`;
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/servicios');
        await page
            .getByRole('button', { name: 'Crear servicio' })
            .first()
            .click();
        await page.getByLabel('Nombre').fill(name);
        await page.getByLabel('Precio (S/)').fill('35,50');
        await page
            .getByRole('button', { name: 'Crear servicio' })
            .last()
            .click();
        await expect(page.getByText('Servicio creado')).toBeVisible();
        await expect(page.getByText(name)).toBeVisible();

        await page.goto('/s/salon-uno');
        await expect(page.getByText(name)).toBeVisible();
        await expect(page.getByText('35.50').first()).toBeVisible();

        await page.goto('/servicios');
        const row = page.getByRole('listitem').filter({ hasText: name });
        await row.getByRole('checkbox').click();
        await expect(row.getByRole('checkbox')).not.toBeChecked();
        await page.goto('/s/salon-uno');
        await expect(page.getByText(name)).toHaveCount(0);

        await page.goto('/servicios');
        await page
            .getByRole('listitem')
            .filter({ hasText: name })
            .getByRole('checkbox')
            .click();
        await page.goto('/s/salon-uno');
        await expect(page.getByText(name)).toBeVisible();
        assertNoErrors();
    });
});
