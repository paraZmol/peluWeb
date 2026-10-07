import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };

const noHorizontalScroll = (page: import('@playwright/test').Page) =>
    page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );

test.describe('QA fase 3', () => {
    test('la lista de profesionales se ve bien en claro y oscuro, sin errores ni scroll lateral', async ({
        page,
    }) => {
        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);

        for (const colorScheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);
            await page.goto('/profesionales');
            await expect(
                page.getByRole('heading', { name: 'Días que el salón cierra' }),
            ).toBeVisible();
            expect(await noHorizontalScroll(page)).toBe(false);
            assertNoErrors();
        }
    });

    test('un feriado con tildes, ñ y emoji se guarda y se lista', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'escritorio',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);
        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/profesionales');

        const future = new Date();
        future.setDate(future.getDate() + 30);
        const iso = future.toISOString().slice(0, 10);
        await page.getByLabel('Fecha').fill(iso);
        await page.getByLabel('Motivo').fill('Día del Ñandú 🎉');
        await page
            .getByRole('button', { name: 'Agregar', exact: true })
            .click();

        await expect(page.getByText('Día de cierre guardado.')).toBeVisible();
        await expect(page.getByText('Día del Ñandú 🎉')).toBeVisible();
        assertNoErrors();
    });

    test('quien no tiene perfil de profesional en el salón actual recibe un aviso en "Mi horario"', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'escritorio',
            'cambia el salón actual de la sesión',
        );
        const assertNoErrors = watchForErrors(page);
        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);

        await page.locator('[data-test="salon-switcher"]').click();
        await page.getByRole('menuitem', { name: 'Salón Dos Ñandú' }).click();
        await expect(
            page.locator('[data-test="salon-switcher"]'),
        ).toContainText('Salón Dos Ñandú');

        await page.goto('/mi-horario');
        await expect(page).toHaveURL(/dashboard$/);
        await expect(
            page.getByText('Aún no tienes un perfil de profesional'),
        ).toBeVisible();
        assertNoErrors();
    });

    test('un profesional inexistente o de otro salón muestra 404 sin romper la app', async ({
        page,
    }) => {
        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);

        const response = await page.goto('/profesionales/999999/horario');
        expect(response?.status()).toBe(404);
    });

    test('un salón sin horarios no muestra la sección de horario en su página pública (claro y oscuro)', async ({
        page,
    }) => {
        for (const colorScheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);
            await page.goto('/s/salon-dos');
            await expect(
                page.getByRole('heading', { name: 'Salón Dos Ñandú' }),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', { name: 'Horario de atención' }),
            ).toHaveCount(0);
            expect(await noHorizontalScroll(page)).toBe(false);
            assertNoErrors();
        }
    });
});
