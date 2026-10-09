import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';
import {
    SLUG,
    chooseTheme,
    chooseUntilConfirm,
    client,
    expectTemplate,
    owner,
} from './theme-qa-support';

/** Step 4.12 (d): preview, roles without permission and the neutral brand color. */
test.describe('Vista previa y roles sin permiso', () => {
    test('móvil: "Ver cómo quedaría" abre la página real en otra pestaña con esa plantilla', async ({
        page,
        context,
    }, testInfo) => {
        test.skip(testInfo.project.name !== 'movil', 'solo móvil');
        const assertNoErrors = watchForErrors(page);

        await login(page, owner.email, owner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-salon');

        const link = page.getByRole('link', {
            name: 'Ver cómo quedaría con Luminosa',
        });
        await expect(link).toHaveAttribute('target', '_blank');
        const [popup] = await Promise.all([
            context.waitForEvent('page'),
            link.click(),
        ]);
        await popup.waitForLoadState();
        await expect(popup).toHaveURL(/diseno=luminosa/);
        await expect(
            popup.locator('[data-theme="luminosa"]').first(),
        ).toBeAttached();
        await expect(
            popup.getByText('Vista previa de la plantilla Luminosa'),
        ).toBeVisible();
        await popup.close();

        // The saved template did not change.
        await page.reload();
        await expect(
            page.locator('#diseno').getByRole('radio', { name: /^Luminosa/ }),
        ).not.toBeChecked();
        assertNoErrors();
    });

    test('escritorio: dentro del teléfono ningún enlace navega ni abre pestañas', async ({
        page,
        context,
    }, testInfo) => {
        test.skip(testInfo.project.name !== 'escritorio', 'solo escritorio');
        const assertNoErrors = watchForErrors(page);

        await login(page, owner.email, owner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-salon');
        await page
            .getByRole('button', { name: 'Ver cómo quedaría con Vibrante' })
            .click();
        const dialog = page.getByRole('dialog', { name: 'Vibrante' });
        const preview = dialog.frameLocator(
            'iframe[title="Vista previa de la plantilla Vibrante"]',
        );
        await expect(preview.getByRole('heading', { level: 1 })).toContainText(
            'Estudio Ñusta',
        );
        await expect(
            preview.locator('[data-theme="vibrante"]').first(),
        ).toBeAttached();

        const frame = page
            .frames()
            .find((candidate) => candidate.url().includes('diseno=vibrante'));
        expect(frame, 'iframe de la vista previa').toBeDefined();
        const frameUrl = frame!.url();
        const topUrl = page.url();
        const pagesBefore = context.pages().length;

        const links = preview.locator('a[href]');
        const total = await links.count();
        expect(total, 'enlaces en la vista previa').toBeGreaterThan(3);
        for (let index = 0; index < total; index++) {
            // The frame is scaled to 75 %: a real pointer misses the target.
            await links.nth(index).dispatchEvent('click');
        }
        await page.waitForTimeout(1200);

        expect(frame!.url(), 'la URL del iframe no cambia').toBe(frameUrl);
        expect(page.url(), 'Mi salón no cambia de URL').toBe(topUrl);
        expect(context.pages().length, 'no se abren pestañas').toBe(
            pagesBefore,
        );
        await expect(preview.getByRole('heading', { level: 1 })).toContainText(
            'Estudio Ñusta',
        );
        await expect(dialog).toBeVisible();
        assertNoErrors();
    });

    test('personal sin permiso: ni Mi salón ni vista previa; dueño de otro salón y visitante tampoco', async ({
        page,
    }) => {
        // The 403 of Mi salón is expected and logs a console error: no error watch here.

        // Pedro attends Salón Dos without the profile permission.
        await login(page, 'personal@example.com', 'password');
        await expect(page).toHaveURL(/dashboard$/);
        const forbidden = await page.goto('/mi-salon');
        expect(forbidden?.status()).toBe(403);
        await page.goto('/s/salon-dos?diseno=vibrante&muestra=1');
        await expectTemplate(page, 'nocturna');
        await expect(
            page.getByText('Vista previa de la plantilla'),
        ).toHaveCount(0);

        // The showcase owner has no say in Salón Uno.
        await page.context().clearCookies();
        await login(page, owner.email, owner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/salon-uno?diseno=vibrante&muestra=1');
        await expectTemplate(page, 'nocturna');
        await expect(
            page.getByText('Vista previa de la plantilla'),
        ).toHaveCount(0);
        await expect(page.locator('img[src*="muestra"]')).toHaveCount(0);

        // Visitors: same, and a salon without photos shows no sample photos.
        await page.context().clearCookies();
        await page.goto('/s/salon-uno?diseno=luminosa&muestra=1');
        await expectTemplate(page, 'nocturna');
        await expect(page.locator('img[src*="muestra"]')).toHaveCount(0);
        await expect(
            page.getByText('Vista previa de la plantilla'),
        ).toHaveCount(0);
    });
});

test.describe('Color de marca oscuro neutro en Nocturna', () => {
    test('el botón primario es claro con texto oscuro legible', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, owner.email, owner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await chooseTheme(page, 'nocturna');
        await page.getByLabel('Color de tu marca').fill('#1f1b18');
        await page.getByRole('button', { name: 'Guardar color' }).click();
        await expect(page.getByText('Color guardado')).toBeVisible();

        try {
            for (const scheme of ['light', 'dark'] as const) {
                await page.emulateMedia({ colorScheme: scheme });
                await page.goto(`/s/${SLUG}`);
                const cta = page
                    .getByRole('link', { name: 'Reservar cita', exact: true })
                    .first();
                await expect(cta).toBeVisible();
                const colors = await cta.evaluate((element) => {
                    const style = getComputedStyle(element);
                    return {
                        background: style.backgroundColor,
                        text: style.color,
                    };
                });
                const parse = (value: string) =>
                    value.match(/\d+(\.\d+)?/g)!.slice(0, 3).map(Number);
                const luminance = ([r, g, b]: number[]) => {
                    const channel = (v: number) => {
                        const c = v / 255;
                        return c <= 0.03928
                            ? c / 12.92
                            : ((c + 0.055) / 1.055) ** 2.4;
                    };
                    return (
                        0.2126 * channel(r) +
                        0.7152 * channel(g) +
                        0.0722 * channel(b)
                    );
                };
                const background = luminance(parse(colors.background));
                const text = luminance(parse(colors.text));
                expect(
                    background,
                    `fondo del botón ${colors.background} claro`,
                ).toBeGreaterThan(0.6);
                expect(
                    text,
                    `texto del botón ${colors.text} oscuro`,
                ).toBeLessThan(0.05);
                const contrast =
                    (Math.max(background, text) + 0.05) /
                    (Math.min(background, text) + 0.05);
                expect(contrast, 'contraste del botón').toBeGreaterThanOrEqual(
                    4.5,
                );
                await page.screenshot({
                    path: `storage/app/shots/qa/marca-neutra-${scheme}.png`,
                });
            }
        } finally {
            await page.emulateMedia({ colorScheme: 'light' });
            await page.goto('/mi-salon');
            await page.getByLabel('Color de tu marca').fill('#1f6f64');
            await page.getByRole('button', { name: 'Guardar color' }).click();
            await expect(page.getByText('Color guardado')).toBeVisible();
        }
        assertNoErrors();
    });
});

test.describe('Avisos en Nocturna', () => {
    // KNOWN BUG (4.12 d): the global toast follows the browser's mode, not the
    // page's template, so Nocturna shows a white toast over a dark page.
    test.fail('el aviso "¡Reserva confirmada!" es oscuro en Nocturna con el navegador en claro', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        await page.emulateMedia({ colorScheme: 'light' });
        await login(page, client.email, client.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/salon-uno/reservar');
        await page.evaluate(() => window.sessionStorage.clear());
        await page.goto('/s/salon-uno/reservar');
        await chooseUntilConfirm(page, /Corte de prueba/, /Siempre Abierto/, 3);
        await page.getByRole('button', { name: 'Confirmar reserva' }).click();
        await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
        const toast = page.locator('[data-sonner-toast]').first();
        await expect(toast).toBeVisible();
        await page.screenshot({
            path: 'storage/app/shots/qa/aviso-nocturna-claro.png',
        });
        const background = await toast.evaluate(
            (element) => getComputedStyle(element).backgroundColor,
        );
        const [r, g, b] = background.match(/\d+(\.\d+)?/g)!.slice(0, 3).map(Number);

        await page.getByRole('button', { name: 'Cancelar reserva' }).click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        await expect(
            page.getByText('Cancelada', { exact: true }),
        ).toBeVisible();

        expect(
            (r + g + b) / 3,
            `fondo del aviso ${background}`,
        ).toBeLessThan(90);
    });
});
