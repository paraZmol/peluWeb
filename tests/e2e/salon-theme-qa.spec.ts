import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';
import {
    LABEL,
    SLUG,
    THEMES,
    chooseTheme,
    chooseUntilConfirm,
    client,
    expectNoHorizontalScroll,
    expectTemplate,
    isDark,
    lightSurfaces,
    owner,
} from './theme-qa-support';

/**
 * Step 4.12 (d): the four templates through the eyes of each role. The
 * showcase salon (vitrina) is switched template by template by its owner, a
 * visitor and a client then walk the real pages. The salon ends on Nocturna,
 * its default, so the other specs find it as they expect.
 */

test.describe('Plantillas por rol (4.12 d)', () => {
    for (const theme of THEMES) {
        test(`${LABEL[theme]}: dueño la elige; visitante y cliente la ven en página, reserva y Mis reservas`, async ({
            page,
        }, testInfo) => {
            test.setTimeout(150_000);
            const assertNoErrors = watchForErrors(page);
            const isMobile = testInfo.project.name === 'movil';

            // Owner chooses and saves it.
            await login(page, owner.email, owner.password);
            await expect(page).toHaveURL(/dashboard$/);
            await chooseTheme(page, theme);
            await page.reload();
            await expect(
                page
                    .locator('#diseno')
                    .getByRole('radio', { name: new RegExp(`^${LABEL[theme]}`) }),
            ).toBeChecked();

            // Visitor: the page uses it, and the query cannot change it.
            await page.context().clearCookies();
            await page.goto(`/s/${SLUG}`);
            await expectTemplate(page, theme);
            await expect(page.getByRole('heading', { level: 1 })).toContainText('Estudio Ñusta');
            await expectNoHorizontalScroll(page);
            await page.goto(`/s/${SLUG}?diseno=${theme === 'vibrante' ? 'luminosa' : 'vibrante'}&muestra=1`);
            await expectTemplate(page, theme);
            await expect(page.getByText('Vista previa')).toHaveCount(0);

            // Client: wizard, confirm, Mis reservas (list + detail) in the same template.
            await login(page, client.email, client.password);
            await expect(page).toHaveURL(/dashboard$/);
            await page.goto(`/s/${SLUG}/reservar`);
            await page.evaluate(() => window.sessionStorage.clear());
            await page.goto(`/s/${SLUG}/reservar`);
            await expectTemplate(page, theme);
            await chooseUntilConfirm(page);
            await expectTemplate(page, theme);
            await expectNoHorizontalScroll(page);
            const confirm = page.getByRole('button', { name: 'Confirmar reserva' });
            await expect(confirm).toBeInViewport();
            await expect(confirm).toBeEnabled();
            await page.screenshot({
                path: `storage/app/shots/qa/confirm-${theme}-${testInfo.project.name}.png`,
            });
            await confirm.click();

            await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
            await expectTemplate(page, theme);
            await expectNoHorizontalScroll(page);
            const code = page.url().split('/').pop();
            await page.screenshot({
                path: `storage/app/shots/qa/detalle-${theme}-${testInfo.project.name}.png`,
                fullPage: true,
            });

            await page.goto('/mis-reservas');
            await expect(page.getByText('Estudio Ñusta Barber & Spa').first()).toBeVisible();
            const seen = await page
                .locator('[data-theme]')
                .evaluateAll((els) => els.map((el) => el.getAttribute('data-theme')));
            expect(seen, 'tarjetas de Mis reservas').toContain(theme);
            await expectNoHorizontalScroll(page);
            await page.screenshot({
                path: `storage/app/shots/qa/lista-${theme}-${testInfo.project.name}.png`,
                fullPage: true,
            });

            // Cleanup: the dialog of cancelling also has to wear the template.
            await page.goto(`/mis-reservas/${code}`);
            await page.getByRole('button', { name: 'Cancelar reserva' }).click();
            const dialog = page.getByRole('alertdialog').or(page.getByRole('dialog'));
            await expect(dialog.first()).toBeVisible();
            await expect(dialog.first()).toHaveAttribute('data-theme', theme);
            await page.getByRole('button', { name: 'Sí, cancelar' }).click();
            await expect(page.getByText('Cancelada', { exact: true })).toBeVisible();
            assertNoErrors();
            void isMobile;
        });
    }
});

test.describe('Nocturna siempre oscura; las demás siguen al visitante', () => {
    for (const scheme of ['light', 'dark'] as const) {
        test(`con el navegador en ${scheme === 'light' ? 'claro' : 'oscuro'}`, async ({
            page,
        }, testInfo) => {
            test.setTimeout(150_000);
            const assertNoErrors = watchForErrors(page);
            await page.emulateMedia({ colorScheme: scheme });

            // Salón Uno stays on Nocturna (nobody changes its template).
            await page.goto('/s/salon-uno');
            await expectTemplate(page, 'nocturna');
            expect(await isDark(page), 'página pública oscura').toBe(true);
            expect(await lightSurfaces(page), 'página pública').toEqual([]);

            await page.goto('/s/salon-uno/reservar');
            await page.evaluate(() => window.sessionStorage.clear());
            await page.goto('/s/salon-uno/reservar');
            await chooseUntilConfirm(page, /Corte de prueba/, /Siempre Abierto/, 3);
            expect(await lightSurfaces(page), 'reserva (confirmar)').toEqual([]);

            await login(page, client.email, client.password);
            await expect(page).toHaveURL(/dashboard$/);
            await page.goto('/s/salon-uno/reservar');
            await page.evaluate(() => window.sessionStorage.clear());
            await page.goto('/s/salon-uno/reservar');
            await chooseUntilConfirm(page, /Corte de prueba/, /Siempre Abierto/, 3);
            await page.getByRole('button', { name: 'Confirmar reserva' }).click();
            await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
            const code = page.url().split('/').pop();
            await page.waitForLoadState('networkidle');
            expect(await isDark(page), 'detalle oscuro').toBe(true);
            expect(await lightSurfaces(page), 'Mis reservas (detalle)').toEqual([]);
            await page.screenshot({
                path: `storage/app/shots/qa/nocturna-detalle-${scheme}-${testInfo.project.name}.png`,
                fullPage: true,
            });

            // Dialog: change day/time.
            await page.getByRole('button', { name: 'Cambiar día u hora' }).click();
            const move = page.getByRole('dialog');
            await expect(move).toBeVisible();
            await expect(move).toHaveAttribute('data-theme', 'nocturna');
            await page.waitForTimeout(600);
            expect(await lightSurfaces(page, move), 'diálogo de mover').toEqual([]);
            await page.screenshot({
                path: `storage/app/shots/qa/nocturna-mover-${scheme}-${testInfo.project.name}.png`,
            });
            await page.keyboard.press('Escape');
            await expect(move).toBeHidden();

            // Reference photo section, with a photo in it.
            await page.getByLabel('Subir foto').setInputFiles({
                name: 'estilo.png',
                mimeType: 'image/png',
                buffer: Buffer.from(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                    'base64',
                ),
            });
            await expect(page.getByAltText('Foto de referencia de tu estilo')).toBeVisible();
            expect(await lightSurfaces(page), 'foto de referencia').toEqual([]);

            // Dialog: cancel.
            await page.getByRole('button', { name: 'Cancelar reserva' }).click();
            const cancel = page.getByRole('alertdialog').or(page.getByRole('dialog'));
            await expect(cancel.first()).toBeVisible();
            await page.waitForTimeout(400);
            expect(await lightSurfaces(page, cancel.first()), 'diálogo de cancelar').toEqual([]);
            await page.screenshot({
                path: `storage/app/shots/qa/nocturna-cancelar-${scheme}-${testInfo.project.name}.png`,
            });
            await page.getByRole('button', { name: 'Sí, cancelar' }).click();
            await expect(page.getByText('Cancelada', { exact: true })).toBeVisible();

            // The list: the card is dark even though the page around it is the platform's.
            await page.goto('/mis-reservas');
            const card = page.getByRole('link', { name: /Salón Uno/ }).first();
            await expect(card).toBeVisible();
            const cardTheme = await card.evaluate((el) => ({
                dark: el.closest('.dark') !== null,
                background: getComputedStyle(el).backgroundColor,
            }));
            expect(cardTheme.dark, 'tarjeta de Mis reservas oscura').toBe(true);
            await page.screenshot({
                path: `storage/app/shots/qa/nocturna-lista-${scheme}-${testInfo.project.name}.png`,
                fullPage: true,
            });
            void code;
            assertNoErrors();
        });
    }
});
