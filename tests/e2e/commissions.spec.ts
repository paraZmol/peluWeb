import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
// A member of "Salón Dos Ñandú" without any permission.
const member = { email: 'personal@example.com', password: 'password' };
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;
// Who attends "Corte de prueba" in "Salón Uno", and what the rule pays.
const STAFF = 'Siempre Abierto';
const FIXED_COMMISSION_CENTS = 750;

async function openSidebar(page: Page) {
    if (page.viewportSize()!.width < 768) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
}

const soles = (cents: number) =>
    `S/ ${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;

/** The card's big amount, "S/ 19.00" → 1900. */
async function pendingCents(card: Locator): Promise<number> {
    const text = await card.getByText(/^S\/ \d+\.\d{2}$/).innerText();
    const [whole, cents] = text.replace('S/ ', '').split('.');

    return Number(whole) * 100 + Number(cents);
}

/** A S/ 35.00 visit for today in "Salón Uno", attended and paid by Yape. */
async function chargeVisit(page: Page, name: string) {
    await page.goto('/agenda/nueva');
    await page.getByRole('button', { name: /Corte de prueba/ }).click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    await page.getByRole('button', { name: new RegExp(STAFF) }).click();
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
    // Not cash: the till of the other specs is not touched.
    await checkout.getByText('Yape', { exact: true }).click();
    await checkout.getByRole('button', { name: 'Cobrar S/ 35.00' }).click();
    await expect(page.getByText('Cita completada y cobrada.')).toBeVisible();
}

test.describe('Comisiones del salón', () => {
    test('se fija una regla, la cita completada deja comisión pendiente, se liquida y queda en el historial y en Gastos', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        const tag = `${testInfo.project.name} ${Date.now() % 100000}`;

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        await page.getByRole('link', { name: 'Comisiones' }).click();
        await expect(page).toHaveURL(/\/comisiones$/);
        // On a phone the menu stays over the page after navigating.
        await page.keyboard.press('Escape');
        await expect(
            page.getByRole('link', { name: 'Este mes' }),
        ).toHaveAttribute('aria-current', 'true');
        const card = page.getByRole('listitem', { name: STAFF, exact: true });

        // The server's complaint shows next to the field.
        await card.getByRole('button', { name: `Reglas de ${STAFF}` }).click();
        const rules = page.getByRole('dialog', {
            name: `Comisión de ${STAFF}`,
        });
        await rules.getByLabel('Porcentaje general').fill('150');
        await rules.getByRole('button', { name: 'Guardar reglas' }).click();
        await expect(
            rules.getByText('El campo porcentaje no debe ser mayor que 100.'),
        ).toBeVisible();
        await rules.getByLabel('Porcentaje general').fill('40');

        // A fixed amount for the service, which cannot be left blank.
        await rules
            .getByRole('combobox', { name: 'Comisión de Corte de prueba' })
            .click();
        await page.getByRole('option', { name: 'Monto fijo' }).click();
        const fixed = rules.getByLabel('Monto fijo de Corte de prueba');
        await fixed.fill('');
        await rules.getByRole('button', { name: 'Guardar reglas' }).click();
        await expect(rules.getByText(/^Escribe un valor/)).toBeVisible();
        await fixed.fill('7,50');
        await rules.getByRole('button', { name: 'Guardar reglas' }).click();

        await expect(
            page.getByText(`Comisión de ${STAFF} actualizada.`),
        ).toBeVisible();
        await expect(rules).toHaveCount(0);
        await expect(card).toContainText(
            '40 % de cada servicio · 1 servicio con regla propia',
        );
        const before = await pendingCents(card);

        await chargeVisit(page, `Comisión ${tag}`);

        await page.goto('/comisiones');
        const owed = soles(before + FIXED_COMMISSION_CENTS);
        await expect(card.getByText(owed, { exact: true })).toBeVisible();
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);

        // Cash comes chosen; paying by Yape keeps the drawer out of it.
        await card
            .getByRole('button', { name: `Liquidar ${owed} a ${STAFF}` })
            .click();
        const settling = page.getByRole('dialog', {
            name: `Liquidar comisiones de ${STAFF}`,
        });
        await expect(settling).toContainText(owed);
        await expect(
            settling.getByRole('radio', { name: 'Efectivo' }),
        ).toBeChecked();
        await expect(settling).toContainText(
            'Queda registrado como gasto en «Sueldos y comisiones» y, al ser en efectivo, sale de la caja.',
        );
        await settling.getByText('Yape', { exact: true }).click();
        const sent = page.waitForRequest((request) =>
            request.url().endsWith('/liquidar'),
        );
        await settling.getByRole('button', { name: 'Sí, liquidar' }).click();
        expect((await sent).postDataJSON()).toMatchObject({ method: 'yape' });

        await expect(
            page.getByText(`Comisiones de ${STAFF} liquidadas.`),
        ).toBeVisible();
        await expect(settling).toHaveCount(0);
        await expect(card.getByText('S/ 0.00', { exact: true })).toBeVisible();
        await expect(
            card.getByRole('button', { name: /^Liquidar/ }),
        ).toBeDisabled();
        const lastSettlement = page
            .getByRole('listitem', { name: /^Liquidación de / })
            .first();
        await expect(lastSettlement).toContainText(STAFF);
        await expect(lastSettlement).toContainText('Total' + owed);

        // The payment is an expense of the salon.
        await page.goto('/gastos');
        const expense = page
            .getByRole('listitem', {
                name: new RegExp(
                    `^Sueldos y comisiones · ${owed.replace('.', '\\.')} `,
                ),
            })
            .first();
        await expect(expense).toContainText(`Comisiones de ${STAFF}`);
        await expect(expense).toContainText('Yape');
        assertNoErrors();
    });

    test('quien no tiene el permiso de caja no ve "Comisiones" y /comisiones le responde 403', async ({
        page,
    }) => {
        await login(page, member.email, member.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);

        await expect(
            page.getByRole('link', { name: 'Mis reservas' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Comisiones' }),
        ).toHaveCount(0);

        const response = await page.goto('/comisiones');
        expect(response?.status()).toBe(403);
    });
});
