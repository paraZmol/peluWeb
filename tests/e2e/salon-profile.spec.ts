import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };

test.describe('Perfil y página pública del salón', () => {
    test('el color de marca elegido pinta los botones de la página pública', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-salon');
        await page
            .getByRole('button', { name: 'Usar el color #1d4ed8' })
            .click();
        await page.getByRole('button', { name: 'Guardar color' }).click();
        await expect(page.getByText('Color guardado')).toBeVisible();

        await page
            .getByLabel('Descripción')
            .fill('Cortes clásicos y modernos.');
        await page.getByRole('button', { name: 'Guardar datos' }).click();
        await expect(page.getByText('Datos del salón guardados')).toBeVisible();

        await page.goto('/s/salon-uno');
        const cta = page.getByRole('link', { name: 'Reservar por WhatsApp' });
        await expect(cta).toBeVisible();
        await expect(cta).toHaveCSS('background-color', 'rgb(29, 78, 216)');
        await expect(
            page.getByText('Cortes clásicos y modernos.'),
        ).toBeVisible();
        assertNoErrors();
    });

    test('la página pública no tiene scroll horizontal ni errores (claro y oscuro)', async ({
        page,
    }) => {
        for (const colorScheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);

            await page.goto('/s/salon-uno');
            await expect(
                page.getByRole('heading', { name: 'Salón Uno' }),
            ).toBeVisible();
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBe(false);
            assertNoErrors();
        }
    });

    test('la dueña agrega un servicio del catálogo y aparece en su página', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'escritorio',
            'un solo flujo que modifica datos',
        );

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/servicios');
        await page
            .getByRole('button', { name: 'Agregar del catálogo' })
            .click();
        await page.getByLabel('Buscar en el catálogo').fill('balayage');
        await page.getByText('Balayage', { exact: true }).click();
        await page.getByRole('button', { name: 'Agregar 1' }).click();
        await expect(page.getByText('Agregamos 1 servicio')).toBeVisible();

        await page.goto('/s/salon-uno');
        await expect(page.getByText('Balayage', { exact: true })).toBeVisible();
    });
});
