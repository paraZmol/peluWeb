import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login } from './support';

/*
 * Phase 5 QA: the money rules by role in a real browser. "Salón Caja" (see
 * E2eSeeder::seedTillSalon) has an owner, a professional who attends visits
 * but has no cash permission, and one with the cash and reports permissions.
 */
const till = {
    owner: 'caja-duena@example.com',
    withoutCash: 'sin-caja@example.com',
    withCash: 'con-caja@example.com',
};
const multi = 'multi@example.com';
const client = 'cliente@example.com';
const PASSWORD = 'password';
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;
const MONEY_PAGES = ['/gastos', '/caja', '/comisiones', '/reportes'];

/** Like watchForErrors, but a deliberate 403/404 is not an error of the page. */
function watchForUnexpectedErrors(page: Page): () => void {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (
            message.type() === 'error' &&
            !/status of (403|404)/.test(message.text())
        ) {
            errors.push(message.text());
        }
    });

    return () => expect(errors, 'errores de JavaScript').toEqual([]);
}

async function openSidebar(page: Page) {
    if (page.viewportSize()!.width < 768) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
}

async function hasHorizontalScroll(page: Page) {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

/** A visit for now in "Salón Caja" for the "Corte de caja" service, then "Llegó". */
async function startVisit(page: Page, staffName: string | null, name: string) {
    await page.goto('/agenda/nueva');
    await page.getByRole('button', { name: /Corte de caja/ }).click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    if (staffName !== null) {
        // Not the sidebar: its user menu carries the same name.
        await page
            .getByRole('main')
            .getByRole('button', { name: new RegExp(staffName) })
            .click();
    }
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

test.describe('Fase 5: cobro sin permiso de caja', () => {
    test('quien atiende sin permiso de caja no ve el descuento, cobra sin él, y un POST con descuento recibe 403', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForUnexpectedErrors(page);
        const name = `Sin Caja ${testInfo.project.name} ${Date.now() % 100000}`;

        await login(page, till.withoutCash, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        const appointment = await startVisit(page, 'Sara Sin Caja', name);

        await appointment.getByRole('button', { name: 'Cobrar' }).click();
        const dialog = page.getByRole('dialog', { name: 'Cobrar' });
        await expect(dialog.getByRole('status').first()).toHaveText('S/ 35.00');
        await expect(
            dialog.getByRole('button', { name: 'Descuento' }),
        ).toHaveCount(0);
        await expect(dialog.getByLabel('Descuento')).toHaveCount(0);
        await expect(dialog.getByText(/descuento/i)).toHaveCount(0);

        // Tampering: the same request the form sends, with a discount added.
        let tampered = true;
        await page.route('**/completar', async (route) => {
            if (!tampered) {
                return route.continue();
            }
            const body = route.request().postDataJSON() as Record<
                string,
                unknown
            >;
            await route.continue({
                postData: JSON.stringify({
                    ...body,
                    discount: '5.00',
                    payments: [{ method: 'cash', amount: '30.00' }],
                }),
                headers: {
                    ...route.request().headers(),
                    'content-type': 'application/json',
                },
            });
        });
        const refused = page.waitForResponse(
            (response) =>
                response.url().endsWith('/completar') &&
                response.request().method() === 'POST',
        );
        await dialog.getByRole('button', { name: 'Cobrar S/ 35.00' }).click();
        expect((await refused).status()).toBe(403);

        // Nothing was charged: the visit is still in service.
        tampered = false;
        await page.goto('/agenda');
        const again = page.getByRole('article', { name: new RegExp(name) });
        await expect(again).toContainText('En atención');
        await expect(again).not.toContainText(/descuento/i);

        await again.getByRole('button', { name: 'Cobrar' }).click();
        const second = page.getByRole('dialog', { name: 'Cobrar' });
        await second.getByRole('button', { name: 'Cobrar S/ 35.00' }).click();
        await expect(
            page.getByText('Cita completada y cobrada.'),
        ).toBeVisible();
        await expect(again).toContainText('Cobrado S/ 35.00');
        await expect(again).not.toContainText(/descuento/i);
        assertNoErrors();
    });

    test('con el permiso de caja el descuento sí aparece', async ({
        page,
    }, testInfo) => {
        const name = `Con Caja ${testInfo.project.name} ${Date.now() % 100000}`;

        await login(page, till.withCash, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        const appointment = await startVisit(page, 'Cleo Con Caja', name);
        await appointment.getByRole('button', { name: 'Cobrar' }).click();
        const dialog = page.getByRole('dialog', { name: 'Cobrar' });
        await expect(
            dialog.getByRole('button', { name: 'Descuento' }),
        ).toBeVisible();
        await dialog.getByRole('button', { name: 'Descuento' }).click();
        await dialog.getByLabel('Descuento').fill('5');
        await dialog.getByRole('button', { name: 'Cobrar S/ 30.00' }).click();
        await expect(appointment).toContainText(
            'Cobrado S/ 30.00 · descuento S/ 5.00',
        );
    });
});

test.describe('Fase 5: recorrido por rol', () => {
    test('el visitante no alcanza ninguna ruta de dinero', async ({
        request,
    }) => {
        for (const url of [
            ...MONEY_PAGES,
            '/reportes/movimientos.csv',
            '/gastos/1/comprobante',
        ]) {
            const response = await request.get(url, { maxRedirects: 0 });
            expect(response.status(), url).toBe(302);
            expect(response.headers()['location'], url).toMatch(/\/login$/);
        }
        for (const url of [
            '/gastos',
            '/caja/abrir',
            '/gastos/1',
            '/comisiones/1/liquidar',
        ]) {
            const response = await request.post(url, { maxRedirects: 0 });
            // 419 (no CSRF token) or a redirect to the login: never a success.
            expect([302, 401, 419], url).toContain(response.status());
        }
    });

    test('un cliente no alcanza ninguna ruta de dinero', async ({ page }) => {
        await login(page, client, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        for (const label of ['Gastos', 'Caja', 'Comisiones', 'Reportes']) {
            await expect(
                page.getByRole('link', { name: label, exact: true }),
            ).toHaveCount(0);
        }
        for (const url of [...MONEY_PAGES, '/reportes/movimientos.csv']) {
            const response = await page.goto(url);
            // A user with no salon is sent to "Crea tu salón": never the money page.
            const refused = [403, 404].includes(response?.status() ?? 0);
            const sentAway = !new URL(page.url()).pathname.startsWith(url);
            expect(refused || sentAway, url).toBe(true);
            await expect(
                page.getByRole('heading', {
                    name: /^(Gastos|Caja|Comisiones|Reportes)$/,
                }),
            ).toHaveCount(0);
        }
    });

    test('el personal sin permisos no ve ni alcanza las pantallas de dinero; con permisos, solo las suyas', async ({
        page,
    }) => {
        await login(page, till.withoutCash, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        for (const label of ['Gastos', 'Caja', 'Comisiones', 'Reportes']) {
            await expect(
                page.getByRole('link', { name: label, exact: true }),
            ).toHaveCount(0);
        }
        for (const url of [...MONEY_PAGES, '/reportes/movimientos.csv']) {
            expect((await page.goto(url))?.status(), url).toBe(403);
        }

        await page.context().clearCookies();
        await login(page, till.withCash, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        for (const url of MONEY_PAGES) {
            expect((await page.goto(url))?.status(), url).toBe(200);
        }
        // Rules are the owner's: cash handlers see the screen without the editor.
        await page.goto('/comisiones');
        await expect(
            page.getByRole('button', { name: /^Reglas de / }),
        ).toHaveCount(0);
    });

    test('el id de un gasto de otro salón da 404, también para la foto', async ({
        page,
    }, testInfo) => {
        const note = `Gasto ajeno ${testInfo.project.name} ${Date.now() % 100000}`;

        await login(page, till.owner, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/gastos');
        await page
            .getByRole('button', { name: 'Registrar gasto', exact: true })
            .click();
        const dialog = page.getByRole('dialog', { name: 'Registrar gasto' });
        await dialog.getByLabel('Monto').fill('3');
        await dialog.getByText('Yape', { exact: true }).click();
        await dialog.getByLabel(/^Descripción/).fill(note);
        await dialog.getByRole('button', { name: 'Guardar gasto' }).click();
        await expect(page.getByText('Gasto registrado.')).toBeVisible();

        // The id the owner's own screen offers (full load: the embedded page is fresh).
        await page.goto('/gastos');
        const id = await page.evaluate(async () => {
            const page = JSON.parse(
                document.querySelector('script[data-page="app"]')
                    ?.textContent ?? '{}',
            ) as {
                props?: { expenses?: { id: number; description: string }[] };
            };
            return page.props?.expenses?.[0]?.id ?? null;
        });
        expect(id, 'id del gasto en las props de Inertia').not.toBeNull();

        await page.context().clearCookies();
        await login(page, multi, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        const receipt = await page.goto(`/gastos/${id}/comprobante`);
        expect(receipt?.status()).toBe(404);
        const xsrf = decodeURIComponent(
            (await page.context().cookies()).find(
                (c) => c.name === 'XSRF-TOKEN',
            )!.value,
        );
        const removal = await page.request.delete(`/gastos/${id}`, {
            headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' },
        });
        expect(removal.status()).toBe(404);

        // Still there for its owner.
        await page.context().clearCookies();
        await login(page, till.owner, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/gastos');
        await expect(page.getByText(note).first()).toBeVisible();
    });
});

for (const colorScheme of ['light', 'dark'] as const) {
    test(`Comisiones avisa que liquidar no se deshace y Reportes muestra Horas ocupadas (${colorScheme})`, async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForUnexpectedErrors(page);
        await page.emulateMedia({ colorScheme });
        const name = `Ocupada ${colorScheme} ${testInfo.project.name} ${Date.now() % 100000}`;

        await login(page, till.owner, PASSWORD);
        await expect(page).toHaveURL(/dashboard$/);

        // The rule first: the commission is frozen when the visit is completed.
        await page.goto('/comisiones');
        const card = page.getByRole('listitem', {
            name: 'Sara Sin Caja',
            exact: true,
        });
        await card
            .getByRole('button', { name: 'Reglas de Sara Sin Caja' })
            .click();
        const rules = page.getByRole('dialog', {
            name: 'Comisión de Sara Sin Caja',
        });
        await rules.getByLabel('Porcentaje general').fill('40');
        await rules.getByRole('button', { name: 'Guardar reglas' }).click();
        await expect(rules).toHaveCount(0);

        // A visit attended by Sara, completed: busy time and a commission.
        const appointment = await startVisit(page, 'Sara Sin Caja', name);
        await appointment.getByRole('button', { name: 'Cobrar' }).click();
        const checkout = page.getByRole('dialog', { name: 'Cobrar' });
        await checkout.getByText('Yape', { exact: true }).click();
        await checkout.getByRole('button', { name: 'Cobrar S/ 35.00' }).click();
        await expect(
            page.getByText('Cita completada y cobrada.'),
        ).toBeVisible();

        await page.goto('/comisiones');
        await card.getByRole('button', { name: /^Liquidar S\/ / }).click();
        const settling = page.getByRole('dialog', {
            name: 'Liquidar comisiones de Sara Sin Caja',
        });
        await expect(settling).toContainText(
            'Una liquidación no se puede deshacer ni editar después.',
        );
        expect(await hasHorizontalScroll(page)).toBe(false);
        await settling.getByRole('button', { name: 'Volver' }).click();
        await expect(settling).toHaveCount(0);

        // Reports: the new block.
        await page.goto('/reportes');
        const block = page.getByRole('region', { name: 'Horas ocupadas' });
        await expect(block).toBeVisible();
        const sara = block.getByRole('listitem', { name: 'Sara Sin Caja' });
        const cleo = block.getByRole('listitem', { name: 'Cleo Con Caja' });
        await expect(sara).toContainText(/\d+ %/);
        await expect(cleo).toContainText(/\d+ %/);
        // Sara attended at least 30 minutes: she is above zero, and nobody over 100 % here.
        await expect(sara).not.toContainText(/^Sara Sin Caja · 0 min de/);
        const percents = await block
            .getByRole('listitem')
            .evaluateAll((items) =>
                items.map((item) => {
                    const match = item.textContent?.match(/(\d+) %/);
                    return match ? Number(match[1]) : -1;
                }),
            );
        for (const percent of percents) {
            expect(percent).toBeGreaterThanOrEqual(0);
            expect(percent).toBeLessThanOrEqual(100);
        }
        expect(await hasHorizontalScroll(page)).toBe(false);
        await block.scrollIntoViewIfNeeded();
        await block.screenshot({
            path: `${testInfo.outputDir}/occupancy-${colorScheme}.png`,
        });

        // Print: the block stays whole and inside the page width.
        await page.emulateMedia({ colorScheme, media: 'print' });
        const printed = await block.evaluate((element) => {
            const rect = element.getBoundingClientRect();
            const rows = [...element.querySelectorAll('li')].map(
                (row) => getComputedStyle(row).breakInside,
            );
            return {
                right: rect.right,
                width: window.innerWidth,
                rows,
                visible: getComputedStyle(element).display !== 'none',
            };
        });
        expect(printed.visible).toBe(true);
        expect(printed.right).toBeLessThanOrEqual(printed.width + 1);
        expect(new Set(printed.rows)).toEqual(new Set(['avoid']));
        expect(await hasHorizontalScroll(page)).toBe(false);
        await page.screenshot({
            path: `${testInfo.outputDir}/report-print-${colorScheme}.png`,
            fullPage: true,
        });
        assertNoErrors();
    });
}
