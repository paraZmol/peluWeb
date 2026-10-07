import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;

test.describe('Citas creadas por el personal', () => {
    test('el salón agenda a alguien sin cuenta, mueve la cita y la cancela', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);
        const name = `Lucho Sincuenta ${Date.now() % 100000}`;

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/agenda');
        await page.getByRole('link', { name: 'Nueva cita' }).click();
        await expect(page).toHaveURL(/\/agenda\/nueva$/);

        await page.getByRole('button', { name: /Corte de prueba/ }).click();
        await page.getByRole('button', { name: 'Continuar' }).click();
        await page.getByRole('button', { name: /Siempre Abierto/ }).click();
        await expect(
            page.getByRole('heading', { name: '¿Cuándo?' }),
        ).toBeVisible();
        // Tomorrow: always has free times, whatever the hour the suite runs at.
        await page.getByRole('radio').nth(1).click();
        await page.getByRole('button', { name: TIME }).first().click();

        await expect(
            page.getByRole('heading', { name: '¿Para quién?' }),
        ).toBeVisible();
        await page.getByLabel('Nombre de tu cliente').fill(name);
        await page.getByLabel('WhatsApp (opcional)').fill('987654321');
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);
        await page.getByRole('button', { name: 'Agendar cita' }).click();

        await expect(page).toHaveURL(/\/agenda\?fecha=\d{4}-\d{2}-\d{2}$/);
        await expect(
            page.getByText(`Cita agendada para ${name}.`),
        ).toBeVisible();
        const appointment = page.getByRole('article', {
            name: new RegExp(name),
        });
        await expect(appointment).toContainText('Confirmada');
        const firstDay = new URL(page.url()).searchParams.get('fecha');

        // Move it two days ahead, to the first free time.
        await appointment.getByRole('button', { name: 'Mover' }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByRole('radio').nth(3).click();
        await dialog.getByRole('button', { name: TIME }).first().click();
        await dialog.getByRole('button', { name: /^Mover a las/ }).click();

        await expect(
            page.getByText('Cita movida. Avísale a tu cliente.'),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/agenda\?fecha=\d{4}-\d{2}-\d{2}$/);
        expect(new URL(page.url()).searchParams.get('fecha')).not.toBe(
            firstDay,
        );
        await expect(appointment).toBeVisible();

        await appointment.getByRole('button', { name: 'Cancelar' }).click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        await expect(appointment).toContainText('Cancelada');
        assertNoErrors();
    });
});
