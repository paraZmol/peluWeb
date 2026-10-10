import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
// A member of "Salón Dos Ñandú" without any permission.
const member = { email: 'personal@example.com', password: 'password' };
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;

async function openSidebar(page: Page) {
    if (page.viewportSize()!.width < 768) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
}

/** A S/ 35.00 visit for today in "Salón Uno", attended and paid in cash. */
async function chargeVisitInCash(page: Page, name: string) {
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
    await appointment.getByRole('button', { name: 'Cobrar' }).click();
    const checkout = page.getByRole('dialog', { name: 'Cobrar' });
    await expect(
        checkout.getByRole('radio', { name: 'Efectivo' }),
    ).toBeChecked();
    await checkout.getByRole('button', { name: 'Cobrar S/ 35.00' }).click();
    await expect(page.getByText('Cita completada y cobrada.')).toBeVisible();
}

/** S/ 12.30 out of the drawer today. */
async function recordCashExpense(page: Page, description: string) {
    await page.goto('/gastos');
    await page
        .getByRole('button', { name: 'Registrar gasto', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Registrar gasto' });
    await dialog.getByLabel('Monto').fill('12,30');
    await expect(dialog.getByRole('radio', { name: 'Efectivo' })).toBeChecked();
    await dialog.getByLabel(/^Descripción/).fill(description);
    await dialog.getByRole('button', { name: 'Guardar gasto' }).click();
    await expect(page.getByText('Gasto registrado.')).toBeVisible();
}

test.describe('Caja del salón', () => {
    test('se abre con un fondo, espera lo cobrado menos lo gastado en efectivo y se cierra con un faltante', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        // Both projects share the database: names of their own.
        const tag = `${testInfo.project.name} ${Date.now() % 100000}`;
        const note = `Vuelto de más ${tag}`;

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        await page.getByRole('link', { name: 'Caja', exact: true }).click();
        await expect(page).toHaveURL(/\/caja$/);
        // On a phone the menu stays over the page after navigating.
        await page.keyboard.press('Escape');
        await expect(
            page.getByRole('heading', { name: 'La caja está cerrada' }),
        ).toBeVisible();

        // The server's complaint shows next to the field.
        await page.getByRole('button', { name: 'Abrir caja' }).click();
        const opening = page.getByRole('dialog', { name: 'Abrir caja' });
        await opening.getByLabel('Fondo inicial').fill('cien');
        await opening.getByRole('button', { name: 'Abrir caja' }).click();
        await expect(
            opening.getByText('Escribe un monto en soles'),
        ).toBeVisible();
        await opening.getByLabel('Fondo inicial').fill('50');
        await opening.getByRole('button', { name: 'Abrir caja' }).click();

        await expect(page.getByText('Caja abierta.')).toBeVisible();
        await expect(opening).toHaveCount(0);
        const expected = page.getByTestId('cash-expected');
        await expect(expected).toHaveText('S/ 50.00');

        await chargeVisitInCash(page, `Caja ${tag}`);
        await recordCashExpense(page, `Insumos ${tag}`);

        // 50.00 + 35.00 − 12.30
        await page.goto('/caja');
        await expect(expected).toHaveText('S/ 72.70');
        const cashRow = page.getByRole('listitem', { name: 'Efectivo' });
        await expect(cashRow).toContainText('Entró' + 'S/ 35.00');
        await expect(cashRow).toContainText('Salió' + 'S/ 12.30');
        await expect(cashRow).toContainText('Neto' + 'S/ 22.70');
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);

        // The difference follows what is typed.
        await page.getByRole('button', { name: 'Cerrar caja' }).click();
        const closing = page.getByRole('dialog', { name: 'Cerrar caja' });
        const result = closing.getByRole('status');
        await closing.getByLabel('Efectivo contado').fill('72,70');
        await expect(result).toHaveText('Cuadra');
        await closing.getByLabel('Efectivo contado').fill('75');
        await expect(result).toHaveText('Sobran S/ 2.30');
        await closing.getByLabel('Efectivo contado').fill('70');
        await expect(result).toHaveText('Faltan S/ 2.70');
        await closing.getByLabel(/^Nota/).fill(note);
        await closing.getByRole('button', { name: 'Cerrar caja' }).click();

        await expect(page.getByText('Caja cerrada.')).toBeVisible();
        await expect(closing).toHaveCount(0);
        await expect(
            page.getByRole('heading', { name: 'La caja está cerrada' }),
        ).toBeVisible();
        const lastClose = page
            .getByRole('listitem', { name: /^Cierre del / })
            .first();
        await expect(lastClose).toContainText('Esperado' + 'S/ 72.70');
        await expect(lastClose).toContainText('Contado' + 'S/ 70.00');
        await expect(lastClose).toContainText('Faltaron S/ 2.70');
        await expect(lastClose).toContainText(note);

        // The next opening suggests what was left in the drawer.
        await page.getByRole('button', { name: 'Abrir caja' }).click();
        await expect(opening.getByLabel('Fondo inicial')).toHaveValue('70.00');
        await page.keyboard.press('Escape');
        await expect(opening).toHaveCount(0);
        assertNoErrors();
    });

    test('quien no tiene el permiso de caja no ve "Caja" y /caja le responde 403', async ({
        page,
    }) => {
        await login(page, member.email, member.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);

        await expect(
            page.getByRole('link', { name: 'Mis reservas' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Caja', exact: true }),
        ).toHaveCount(0);

        const response = await page.goto('/caja');
        expect(response?.status()).toBe(403);
    });
});
