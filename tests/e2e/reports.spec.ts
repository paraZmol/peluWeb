import { readFile } from 'node:fs/promises';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
// A member of "Salón Dos Ñandú" without any permission.
const member = { email: 'personal@example.com', password: 'password' };
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;
const STAFF = 'Siempre Abierto';
const VISIT_CENTS = 3500;
const EXPENSE_CENTS = 1250;
// What Excel reads as the first line of the file, after the UTF-8 mark.
const CSV_HEADING = 'Fecha;Hora;Tipo;Detalle;Método;Entrada;Salida';

async function openSidebar(page: Page) {
    if (page.viewportSize()!.width < 768) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
}

const soles = (cents: number) =>
    `S/ ${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;

/** One of the three big figures, "S/ 35.00" → 3500. */
async function figureCents(page: Page, testId: string): Promise<number> {
    const text = await page.getByTestId(testId).innerText();
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

/** A S/ 12.50 expense paid today by Yape, so the drawer stays out of it. */
async function registerExpense(page: Page, description: string) {
    await page.goto('/gastos');
    await page
        .getByRole('button', { name: 'Registrar gasto', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Registrar gasto' });
    await dialog.getByLabel('Monto').fill('12,50');
    await dialog.getByText('Yape', { exact: true }).click();
    await dialog.getByLabel(/^Descripción/).fill(description);
    await dialog.getByRole('button', { name: 'Guardar gasto' }).click();
    await expect(page.getByText('Gasto registrado.')).toBeVisible();
}

test.describe('Reportes del salón', () => {
    test('una cita cobrada y un gasto se ven en las cifras, se cambia de período y se descargan los movimientos', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        const tag = `${testInfo.project.name} ${Date.now() % 100000}`;

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        await page.getByRole('link', { name: 'Reportes', exact: true }).click();
        await expect(page).toHaveURL(/\/reportes$/);
        // On a phone the menu stays over the page after navigating.
        await page.keyboard.press('Escape');
        const thisMonth = page.getByRole('link', { name: 'Este mes' });
        await expect(thisMonth).toHaveAttribute('aria-current', 'true');
        const incomeBefore = await figureCents(page, 'report-income');
        const expensesBefore = await figureCents(page, 'report-expenses');

        await chargeVisit(page, `Reporte ${tag}`);
        await registerExpense(page, `Insumos ${tag}`);

        // The three figures hold together: what is left is income − expenses.
        await page.goto('/reportes');
        const income = incomeBefore + VISIT_CENTS;
        const expenses = expensesBefore + EXPENSE_CENTS;
        const summary = page.getByRole('region', {
            name: 'Resumen del período',
        });
        await expect(page.getByTestId('report-income')).toHaveText(
            soles(income),
        );
        await expect(page.getByTestId('report-expenses')).toHaveText(
            soles(expenses),
        );
        await expect(page.getByTestId('report-profit')).toHaveText(
            soles(Math.abs(income - expenses)),
        );
        await expect(
            summary.getByText(income < expenses ? 'Pérdida' : 'Ganancia', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            summary.getByText('Sin datos del período anterior'),
        ).toHaveCount(3);

        // The chart has its numbers as a table too.
        const chart = page.getByRole('region', {
            name: 'Ingresos y gastos por día',
        });
        await expect(chart.getByRole('img')).toBeVisible();
        await chart.getByText('Ver los valores en una tabla').click();
        await expect(
            chart.getByRole('columnheader', { name: 'Resultado' }),
        ).toBeVisible();

        await expect(
            page.getByRole('region', { name: 'De dónde vino el ingreso' }),
        ).toContainText('Servicios');
        // Tips are between the client and whoever attends: never on the report.
        await expect(page.getByText(/propina/i)).toHaveCount(0);
        await expect(
            page
                .getByRole('region', { name: 'Por profesional' })
                .getByRole('listitem', { name: STAFF }),
        ).toContainText('Ventas');
        await expect(
            page.getByRole('region', { name: 'Servicios más vendidos' }),
        ).toContainText('Corte de prueba');
        await expect(
            page.getByRole('region', { name: 'Dinero recibido por método' }),
        ).toContainText('Yape');
        await expect(
            page.getByRole('region', { name: 'Gastos por categoría' }),
        ).toContainText('Sin categoría');
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);

        // Printing is the browser's own dialog: only that it is asked for.
        await page.evaluate(() => {
            window.print = () => document.body.setAttribute('data-printed', '');
        });
        await page
            .getByRole('button', { name: 'Imprimir o guardar como PDF' })
            .click();
        await expect(page.locator('body[data-printed]')).toHaveCount(1);

        // The file covers the period on screen and opens in Excel.
        const downloading = page.waitForEvent('download');
        await page
            .getByRole('link', { name: 'Descargar movimientos (CSV)' })
            .click();
        const download = await downloading;
        expect(download.suggestedFilename()).toMatch(
            /^movimientos-salon-uno-\d{4}-\d{2}-01-a-\d{4}-\d{2}-\d{2}\.csv$/,
        );
        const csv = await readFile(await download.path(), 'utf8');
        expect(csv.replace(/^﻿/, '').split(/\r?\n/)[0]).toBe(CSV_HEADING);
        expect(csv).toContain(`Insumos ${tag}`);

        // Last month had nothing: the figures say so and offer a way out.
        await page.getByRole('link', { name: 'Mes pasado' }).click();
        await expect(page).toHaveURL(/\/reportes\?desde=.+&hasta=.+/);
        await expect(
            page.getByRole('link', { name: 'Mes pasado' }),
        ).toHaveAttribute('aria-current', 'true');
        await expect(page.getByTestId('report-income')).toHaveText('S/ 0.00');
        await expect(
            page.getByText('No hubo movimientos en este período'),
        ).toBeVisible();
        await expect(chart).toHaveCount(0);

        await thisMonth.click();
        await expect(page.getByTestId('report-income')).toHaveText(
            soles(income),
        );
        assertNoErrors();
    });

    test('quien no tiene el permiso de reportes no ve "Reportes" y /reportes y su CSV le responden 403', async ({
        page,
    }) => {
        await login(page, member.email, member.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);

        await expect(
            page.getByRole('link', { name: 'Mis reservas' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Reportes', exact: true }),
        ).toHaveCount(0);

        const response = await page.goto('/reportes');
        expect(response?.status()).toBe(403);
        const file = await page.request.get('/reportes/movimientos.csv');
        expect(file.status()).toBe(403);
    });
});
