import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
// Not the usual client account: its bookings per day in the showcase salon are
// counted, and the template specs need all of them.
const client = { email: 'personal@example.com', password: 'password' };
// The showcase salon asks for a 30 % deposit: S/ 10.50 on a S/ 35.00 cut.
const showcaseOwner = { email: 'vitrina@example.com', password: 'password' };
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;

/** A visit for today in "Salón Uno" (no deposit), already being attended. */
async function startVisitToday(page: Page, name: string) {
    await login(page, multi.email, multi.password);
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/agenda/nueva');
    await page.getByRole('button', { name: /Corte de prueba/ }).click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    await page.getByRole('button', { name: /Siempre Abierto/ }).click();
    await page.getByRole('radio').first().click();
    await page.getByRole('button', { name: TIME }).first().click();
    await page.getByLabel('Nombre de tu cliente').fill(name);
    await page.getByRole('button', { name: 'Agendar cita' }).click();
    await expect(page).toHaveURL(/\/agenda\?fecha=/);

    const appointment = page.getByRole('article', { name: new RegExp(name) });
    await appointment.getByRole('button', { name: 'Llegó' }).click();
    await expect(appointment).toContainText('En atención');

    return appointment;
}

test.describe('Cobrar una cita', () => {
    test('el cobro se divide entre dos métodos con descuento, y no deja cobrar si no cuadra', async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);
        const appointment = await startVisitToday(
            page,
            `Cobro Dividido ${Date.now() % 100000}`,
        );

        await appointment.getByRole('button', { name: 'Cobrar' }).click();
        const dialog = page.getByRole('dialog', { name: 'Cobrar' });
        const total = dialog.getByRole('status').first();
        const problem = dialog.getByRole('status').last();
        await expect(total).toHaveText('S/ 35.00');
        await expect(
            dialog.getByRole('radio', { name: 'Efectivo' }),
        ).toBeChecked();
        await expect(
            dialog.getByRole('button', { name: 'Cobrar S/ 35.00' }),
        ).toBeEnabled();

        // A discount above what is owed blocks the charge.
        await dialog.getByRole('button', { name: 'Descuento' }).click();
        await dialog.getByLabel('Descuento').fill('40');
        await expect(problem).toHaveText(
            'El descuento no puede pasar de S/ 35.00.',
        );
        await expect(
            dialog.getByRole('button', { name: /^Cobrar/ }),
        ).toBeDisabled();

        await dialog.getByLabel('Descuento').fill('5');
        await expect(total).toHaveText('S/ 30.00');
        // The salon charges what was agreed: there is no tip to type.
        await expect(dialog.getByText(/propina/i)).toHaveCount(0);
        const charge = dialog.getByRole('button', { name: 'Cobrar S/ 30.00' });
        await expect(charge).toBeEnabled();

        // Split: nothing typed yet, so it cannot be charged.
        await dialog.getByRole('button', { name: 'Dividir el pago' }).click();
        await expect(charge).toBeDisabled();
        await dialog.getByLabel('Monto en Efectivo').fill('20');
        await expect(dialog.getByLabel('Monto en Yape')).toHaveValue('10.00');
        await expect(charge).toBeEnabled();

        await dialog.getByLabel('Monto en Yape').fill('5');
        await expect(problem).toHaveText(
            'Faltan S/ 5.00 para llegar al total.',
        );
        await expect(charge).toBeDisabled();
        await dialog.getByLabel('Monto en Yape').fill('15');
        await expect(problem).toHaveText(
            'Sobran S/ 5.00: los montos pasan del total.',
        );
        await expect(charge).toBeDisabled();

        // Typing the first amount again hands the rest to the last one.
        await dialog.getByLabel('Monto en Efectivo').fill('18.5');
        await expect(dialog.getByLabel('Monto en Yape')).toHaveValue('11.50');
        await expect(problem).toBeEmpty();
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);

        const sent = page.waitForRequest(
            (request) =>
                request.method() === 'POST' &&
                request.url().endsWith('/completar'),
        );
        await charge.click();
        expect((await sent).postDataJSON()).toEqual({
            discount: '5.00',
            payments: [
                { method: 'cash', amount: '18.50' },
                { method: 'yape', amount: '11.50' },
            ],
        });

        await expect(
            page.getByText('Cita completada y cobrada.'),
        ).toBeVisible();
        await expect(dialog).toHaveCount(0);
        await expect(appointment).toContainText('Completada');
        await expect(appointment).toContainText(
            'Cobrado S/ 30.00 · descuento S/ 5.00',
        );
        await expect(appointment).not.toContainText(/propina/i);
        await expect(
            appointment.getByRole('button', { name: 'Cobrar' }),
        ).toHaveCount(0);
        assertNoErrors();
    });

    test('la seña se recibe con su método y, si la cita se cancela, se devuelve', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, client.email, client.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/estudio-vitrina/reservar');
        await page.getByRole('button', { name: /Corte clásico/ }).click();
        await page.getByRole('button', { name: 'Continuar' }).click();
        await page.getByRole('button', { name: /José Antonio/ }).click();
        await page.getByRole('radio').nth(1).click();
        await page.getByRole('button', { name: TIME }).first().click();
        await page.getByRole('button', { name: 'Confirmar reserva' }).click();
        await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);

        await page.context().clearCookies();
        await login(page, showcaseOwner.email, showcaseOwner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/agenda');
        await page.getByRole('button', { name: 'Día siguiente' }).click();
        const appointment = page.getByRole('article', {
            name: /Pedro Personal/,
        });
        await expect(appointment).toContainText('Pendiente de seña');

        // Deposit received: Yape comes chosen, Plin is what arrived.
        await appointment
            .getByRole('button', { name: 'Seña recibida' })
            .click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toContainText('S/ 10.50');
        await expect(dialog.getByRole('radio', { name: 'Yape' })).toBeChecked();
        await dialog.getByText('Plin', { exact: true }).click();
        await expect(dialog.getByRole('radio', { name: 'Plin' })).toBeChecked();
        const confirmed = page.waitForRequest((request) =>
            request.url().endsWith('/confirmar'),
        );
        await dialog.getByRole('button', { name: 'Sí, la recibí' }).click();
        expect((await confirmed).postDataJSON()).toEqual({ method: 'plin' });
        await expect(page.getByText('Reserva confirmada.')).toBeVisible();
        await expect(appointment).toContainText('Confirmada');

        await appointment
            .getByRole('button', { name: 'Cancelar cita' })
            .click();
        await page
            .getByRole('button', { name: 'Sí, cancelar la cita' })
            .click();
        await expect(appointment).toContainText('Cancelada');
        await expect(appointment).toContainText(
            'Seña de S/ 10.50 pendiente de devolver.',
        );

        // Giving it back: the amount is fixed, only the method is chosen.
        await appointment
            .getByRole('button', { name: 'Devolver seña' })
            .click();
        await expect(dialog).toContainText('S/ 10.50');
        await dialog.getByText('Efectivo', { exact: true }).click();
        const refunded = page.waitForRequest((request) =>
            request.url().endsWith('/devolver-sena'),
        );
        await dialog.getByRole('button', { name: 'Sí, la devolví' }).click();
        expect((await refunded).postDataJSON()).toEqual({ method: 'cash' });
        await expect(page.getByText('Seña devuelta.')).toBeVisible();
        await expect(appointment).toContainText('Seña de S/ 10.50 devuelta.');
        await expect(
            appointment.getByRole('button', { name: 'Devolver seña' }),
        ).toHaveCount(0);
        assertNoErrors();
    });
});
