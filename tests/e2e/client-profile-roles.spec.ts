import { expect, test } from '@playwright/test';
import type { Browser, Page, TestInfo } from '@playwright/test';
import { join } from 'node:path';
import { login, watchForErrors } from './support';

// QA 6.1: who sees what in the client's file ("Salón Fichas" of E2eSeeder).
const fabia = { email: 'ficha-duena@example.com', password: 'password' };
const paco = { email: 'paco-ficha@example.com', password: 'password' };
const rita = { email: 'rita-ficha@example.com', password: 'password' };
const carla = { email: 'cliente@example.com', password: 'password' };
const marta = { email: 'multi@example.com', password: 'password' };

const IMAGES = join(process.cwd(), 'database/seeders/images');
const FILES = process.env.QA_FILES ?? '';
const CARE = 'Alergia al amoníaco';

type Found = { href: string; photoUrl: string | null };

/** The owner's view tells us the file's URL and its picture's URL. */
async function lookup(
    browser: Browser,
    testInfo: TestInfo,
    name: string,
): Promise<Found> {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { defaultBrowserType, ...use } = testInfo.project.use as Record<
        string,
        unknown
    >;
    const context = await browser.newContext(use);
    const page = await context.newPage();
    await signIn(page, fabia);
    await expect(page).toHaveURL(/dashboard$/, { timeout: 20_000 });
    await page.goto('/clientes');
    const href = await page
        .getByRole('link', { name: `Ver ficha de ${name}` })
        .getAttribute('href');
    await page.goto(href!);
    const photo = page.locator('img[src*="/fotos/"]').first();
    const photoUrl = (await photo.count())
        ? await photo.getAttribute('src')
        : null;
    await context.close();

    return { href: href!, photoUrl };
}

/** Logs in and waits until the session is really there (the dev server is slow). */
async function signIn(page: Page, who: { email: string; password: string }) {
    await login(page, who.email, who.password);
    await expect(page).toHaveURL(/dashboard$/, { timeout: 30_000 });
}

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

async function logout(page: Page) {
    const menu = page.locator('[data-test="sidebar-menu-button"]');
    if (!(await menu.isVisible())) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
    await page.locator('[data-test="sidebar-menu-button"]').click();
    await page.locator('[data-test="logout-button"]').click();
    await expect(page).toHaveURL(/\/($|login)/, { timeout: 30_000 });
}

for (const scheme of ['light', 'dark'] as const) {
    test.describe(`ficha por rol · ${scheme}`, () => {
        test.use({ colorScheme: scheme });

        test('profesional que atiende: ve alergias y fotos, no ve dinero; no abre fichas ajenas', async ({
            page,
            browser,
        }, testInfo) => {
            const watch = watchForErrors(page);
            const mine = await lookup(browser, testInfo, 'Atendida Paco');
            const foreign = await lookup(browser, testInfo, 'Ajena Fabia');
            const tomorrows = await lookup(browser, testInfo, 'Ficha Movil');

            await signIn(page, paco);
            await expect(page).toHaveURL(/dashboard$/, { timeout: 20_000 });
            await page.goto(mine.href);
            await expect(
                page.getByRole('heading', { name: 'Atendida Paco' }),
            ).toBeVisible();
            await expect(
                page.getByRole('textbox', { name: 'Alergias y cuidado' }),
            ).toHaveValue(CARE);
            const photos = page.getByRole('region', {
                name: 'Fotos antes y después',
            });
            await expect(photos.getByText('1 de 40')).toBeVisible();
            const img = page.locator('img[src*="/fotos/"]').first();
            await expect(img).toBeVisible();
            await expect
                .poll(() =>
                    img.evaluate((el: HTMLImageElement) => el.naturalWidth),
                )
                .toBeGreaterThan(0);
            // No money: neither the summary nor the history show amounts.
            await expect(
                page.getByLabel('Resumen de visitas'),
            ).not.toContainText('S/');
            await expect(
                page.getByRole('region', { name: 'Historial' }),
            ).not.toContainText('Cobrado');
            expect(await hasHorizontalScroll(page)).toBe(false);
            await page.screenshot({
                path: join(
                    process.env.QA_SHOTS ?? 'test-results-2',
                    `paco-${testInfo.project.name}-${scheme}.png`,
                ),
                fullPage: true,
            });

            // The agenda only lists his own people.
            await page.goto('/agenda');
            await page.getByRole('button', { name: 'Día siguiente' }).click();
            await expect(
                page.getByRole('link', { name: 'Ver ficha de Atendida Paco' }),
            ).toBeVisible();
            await expect(
                page.getByRole('link', { name: /Ver ficha de Ficha/ }),
            ).toHaveCount(0);

            watch();
            // Someone else's person: 403, with nothing of theirs in the page.
            for (const other of [foreign, tomorrows]) {
                const response = await page.goto(other.href);
                expect(response!.status()).toBe(403);
                const html = await page.content();
                expect(html).not.toContain('Ajena Fabia');
                expect(html).not.toContain('Ficha Movil');
            }
            // Writing their notes is not possible either.
            const csrf = decodeURIComponent(
                (await page.context().cookies()).find(
                    (c) => c.name === 'XSRF-TOKEN',
                )!.value,
            );
            const put = await page.request.put(`${foreign.href}/notas`, {
                headers: { 'X-XSRF-TOKEN': csrf, Accept: 'application/json' },
                data: { notes: 'intruso' },
            });
            expect(put.status()).toBe(403);
        });

        test('recepción sin atender: abre la ficha pero sin alergias, fotos ni rastro', async ({
            page,
            browser,
        }, testInfo) => {
            const watch = watchForErrors(page);
            const attended = await lookup(browser, testInfo, 'Atendida Paco');
            expect(attended.photoUrl).not.toBeNull();

            await signIn(page, rita);
            await expect(page).toHaveURL(/dashboard$/, { timeout: 20_000 });
            const response = await page.goto(attended.href);
            expect(response!.status()).toBe(200);
            await expect(
                page.getByRole('heading', { name: 'Atendida Paco' }),
            ).toBeVisible();
            await expect(
                page.getByLabel('Resumen de visitas'),
            ).not.toContainText('S/');

            for (const trace of [
                'Alergias y cuidado',
                'Fotos antes y después',
                'Imprimir la hoja',
                'Sin alergias registradas',
                'autorización escrita',
                'amoníaco',
            ]) {
                await expect(page.getByText(trace)).toHaveCount(0);
            }
            await expect(page.locator('img[src*="/fotos/"]')).toHaveCount(0);
            const html = await page.content();
            expect(html).not.toContain('amoníaco');
            expect(html).not.toContain('/fotos/');
            // The Inertia payload carries no care data or pictures either.
            const props = await page.evaluate(
                () =>
                    document.querySelector('script[data-page]')?.textContent ??
                    '',
            );
            expect(props.length).toBeGreaterThan(200);
            expect(props).not.toContain('amon');
            expect(props).not.toMatch(/"canSeeCare":true/);
            expect(props).toMatch(/"photos":\[\]/);

            // The picture itself is refused, not just hidden.
            const photo = await page.request.get(attended.photoUrl!, {
                maxRedirects: 0,
            });
            expect(photo.status()).toBe(403);
            const del = await page.request.fetch(attended.photoUrl!, {
                method: 'DELETE',
                headers: {
                    'X-XSRF-TOKEN': decodeURIComponent(
                        (await page.context().cookies()).find(
                            (c) => c.name === 'XSRF-TOKEN',
                        )!.value,
                    ),
                    Accept: 'application/json',
                },
            });
            expect(del.status()).toBe(403);
            expect(await hasHorizontalScroll(page)).toBe(false);
            watch();
        });

        test('cliente final, visitante y dueño de otro salón: nada', async ({
            page,
            browser,
            playwright,
        }, testInfo) => {
            const attended = await lookup(browser, testInfo, 'Atendida Paco');

            // Visitor: sent to the login, picture refused.
            await page.goto(attended.href);
            await expect(page).toHaveURL(/\/login/);
            const anon = await playwright.request.newContext({
                baseURL: testInfo.project.use.baseURL,
            });
            for (const url of [attended.photoUrl!, attended.href]) {
                const res = await anon.get(url, { maxRedirects: 0 });
                expect(res.status()).toBe(302);
                expect(res.headers()['location']).toContain('/login');
                expect(res.headers()['content-type'] ?? '').not.toContain(
                    'image',
                );
            }
            await anon.dispose();

            // Final client and the owner of another salon.
            for (const who of [carla, marta]) {
                await page.context().clearCookies();
                await login(page, who.email, who.password);
                await expect(page).not.toHaveURL(/login/, { timeout: 20_000 });
                const file = await page.goto(attended.href);
                expect([403, 404]).toContain(file!.status());
                expect(await page.content()).not.toContain('Atendida Paco');
                const photo = await page.request.get(attended.photoUrl!, {
                    maxRedirects: 0,
                });
                expect([403, 404]).toContain(photo.status());
                expect(photo.headers()['content-type'] ?? '').not.toContain(
                    'image',
                );
            }
        });

        test('alergias de punta a punta con un profesional: firma, hoja impresa y retiro', async ({
            page,
            browser,
        }, testInfo) => {
            const watch = watchForErrors(page);
            const name =
                testInfo.project.name === 'movil'
                    ? 'Flujo Movil'
                    : 'Flujo Escritorio';
            const flow = await lookup(browser, testInfo, name);

            await signIn(page, paco);
            await expect(page).toHaveURL(/dashboard$/, { timeout: 20_000 });
            await page.goto(flow.href);
            const care = page.getByRole('textbox', {
                name: 'Alergias y cuidado',
            });
            const consent = page.getByRole('checkbox', {
                name: `Declaro que ${name} firmó la autorización impresa y que el salón la conserva.`,
            });
            await expect(care).toBeDisabled();
            await consent.check();
            await care.fill('Reacción al tinte con PPD 💥 ñandú');
            await page.getByRole('button', { name: 'Guardar notas' }).click();
            await expect(page.getByText('Ficha actualizada.')).toBeVisible({
                timeout: 25_000,
            });
            await page.reload();
            await expect(
                page.getByText(
                    /^Autorización registrada el \d+ \w+ \d{4} por Paco Profesional$/,
                ),
            ).toBeVisible();
            await expect(care).toHaveValue(
                'Reacción al tinte con PPD 💥 ñandú',
            );

            // The sheet to sign, on paper.
            await page.emulateMedia({ media: 'print' });
            const sheet = page.locator('article.report-sheet');
            await expect(sheet).toBeVisible();
            await expect(sheet).toContainText('Salón Fichas');
            await expect(sheet).toContainText(
                'Autorización para guardar datos de salud: alergias y reacciones a productos',
            );
            await expect(sheet).toContainText(`Nombre: ${name}`);
            await expect(sheet).toContainText('Firma');
            await expect(sheet).toContainText('Fecha');
            await expect(sheet).toContainText('Autorizo a Salón Fichas');
            const printed = await page.locator('body').innerText();
            for (const screenOnly of [
                'Notas del equipo',
                'Próximas citas',
                'Historial',
                'Cerrar sesión',
                'Guardar notas',
                'Reacción al tinte',
                'Fotos antes',
            ]) {
                expect(printed, `en papel: ${screenOnly}`).not.toContain(
                    screenOnly,
                );
            }
            // Dark mode on screen still prints dark ink on a light sheet.
            const colors = await sheet.evaluate((el) => {
                const lum = (css: string) => {
                    const m = css.match(/[\d.]+/g)!.map(Number);

                    return 0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2];
                };
                let bg = 'rgba(0, 0, 0, 0)';
                for (let n: HTMLElement | null = el; n; n = n.parentElement) {
                    const c = getComputedStyle(n).backgroundColor;
                    if (!/rgba\(.*, 0\)|transparent/.test(c)) {
                        bg = c;
                        break;
                    }
                }

                return {
                    ink: lum(getComputedStyle(el).color),
                    paper: lum(bg),
                };
            });
            expect(colors.ink).toBeLessThan(110);
            expect(colors.paper).toBeGreaterThan(200);
            if (scheme === 'light') {
                await page.pdf({
                    path: join(
                        process.env.QA_SHOTS ?? 'test-results-2',
                        `hoja-${testInfo.project.name}.pdf`,
                    ),
                    format: 'A4',
                });
            }
            await page.emulateMedia({ media: 'screen' });

            // Withdrawal asks first and then erases.
            await consent.uncheck();
            await page.getByRole('button', { name: 'Guardar notas' }).click();
            const confirmation = page.getByRole('dialog', {
                name: /^¿Retirar la autorización de /,
            });
            await confirmation.getByRole('button', { name: 'Volver' }).click();
            await page.getByRole('button', { name: 'Guardar notas' }).click();
            await confirmation
                .getByRole('button', { name: 'Retirar y borrar alergias' })
                .click();
            await expect(page.getByText('Ficha actualizada.')).toBeVisible({
                timeout: 25_000,
            });
            await page.reload();
            await expect(care).toBeDisabled();
            await expect(care).toHaveValue('');
            expect(await page.content()).not.toContain('PPD');
            watch();
        });
    });
}

test.describe('casos borde', () => {
    test('notas: 1000 exactos, 1001, emojis, saltos de línea y <script> como texto', async ({
        page,
    }) => {
        const watch = watchForErrors(page);
        let dialogs = 0;
        page.on('dialog', async (dialog) => {
            dialogs += 1;
            await dialog.dismiss();
        });
        await signIn(page, fabia);
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Sin Datos' })
            .click();
        const prefs = page.getByLabel('Preferencias');
        const save = page.getByRole('button', { name: 'Guardar notas' });

        // Exactly the limit is kept.
        const exact = 'ñ'.repeat(1000);
        await prefs.fill(exact);
        await save.click();
        await expect(page.getByText('Ficha actualizada.')).toBeVisible({
            timeout: 25_000,
        });
        await page.reload();
        await expect(prefs).toHaveValue(exact);

        // The field stops typing at the limit...
        await prefs.fill(exact + 'x');
        await expect(prefs).toHaveValue(exact);
        await expect(page.getByText('1000 / 1000').first()).toBeVisible();
        // ...and the server refuses 1001 if someone skips the form.
        const token = decodeURIComponent(
            (await page.context().cookies()).find(
                (c) => c.name === 'XSRF-TOKEN',
            )!.value,
        );
        const url = new URL(page.url()).pathname;
        const over = await page.request.put(`${url}/notas`, {
            headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' },
            data: { notes: exact + 'x', formula_notes: '' },
        });
        expect(over.status()).toBe(422);
        await page.reload();
        await expect(prefs).toHaveValue(exact);

        // Emoji, new lines, angle brackets: text, never markup.
        const nasty =
            'Línea 1 💈✂️\nLínea 2 <script>window.__xss=1</script> <img src=x onerror="window.__xss=2">\n\nTilde áéíóú ñ "comillas" & más';
        await prefs.fill(nasty);
        await page
            .getByLabel('Fórmula y técnica')
            .fill('<b>negrita</b> 7.1 + 20 vol');
        await save.click();
        await expect(page.getByText('Ficha actualizada.')).toBeVisible({
            timeout: 25_000,
        });
        await page.reload();
        await expect(prefs).toHaveValue(nasty);
        expect(
            await page.evaluate(
                () => (window as unknown as { __xss?: number }).__xss,
            ),
        ).toBeUndefined();
        expect(dialogs).toBe(0);
        await expect(page.locator('b', { hasText: 'negrita' })).toHaveCount(0);
        await prefs.fill('');
        await page.getByLabel('Fórmula y técnica').fill('');
        await save.click();
        await expect(page.getByText('Ficha actualizada.')).toBeVisible({
            timeout: 25_000,
        });
        watch();
    });

    test('sin teléfono, sin visitas ni citas: sin WhatsApp y con estados vacíos', async ({
        page,
    }) => {
        await signIn(page, fabia);
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Sin Datos' })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Sin Datos' }),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: /WhatsApp/ })).toHaveCount(
            0,
        );
        await expect(page.getByText('No tiene citas próximas.')).toBeVisible();
        await expect(
            page.getByText(/^Todavía no hay visitas terminadas/),
        ).toBeVisible();
        await expect(page.getByLabel('Resumen de visitas')).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);
        // With a phone the button does exist.
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Ajena Fabia' })
            .click();
        await expect(
            page.getByRole('link', { name: /WhatsApp/ }),
        ).toBeVisible();
    });

    test('más de 30 visitas: se listan 30 y se avisa del recorte', async ({
        page,
    }) => {
        await signIn(page, fabia);
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Larga Historia' })
            .click();
        const history = page.getByRole('region', { name: 'Historial' });
        await expect(history.getByRole('listitem')).toHaveCount(30);
        await expect(history).toContainText('Mostramos las últimas 30 citas.');
        await expect(page.getByLabel('Resumen de visitas')).toContainText('35');
        expect(await hasHorizontalScroll(page)).toBe(false);
        // Someone with less than 30 gets no warning.
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Ajena Fabia' })
            .click();
        await expect(page.getByText(/^Mostramos las últimas/)).toHaveCount(0);
    });

    // Back after logout, from a file reached by clicking inside the app.
    test('cerrar sesión y «Atrás» no devuelven la ficha (navegación dentro de la app)', async ({
        page,
    }) => {
        await signIn(page, fabia);
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Atendida Paco' })
            .click();
        await expect(
            page.getByRole('textbox', { name: 'Alergias y cuidado' }),
        ).toHaveValue(CARE);
        if (page.viewportSize()!.width < 768) {
            await page.locator('[data-sidebar="trigger"]').first().click();
        }
        await page.getByRole('link', { name: 'Panel' }).first().click();
        await expect(page).toHaveURL(/dashboard$/);
        await logout(page);
        for (let i = 0; i < 4; i += 1) {
            await page.goBack().catch(() => undefined);
            await page.waitForTimeout(1500);
            const html = await page.content();
            expect(html, `atrás #${i + 1}`).not.toContain('amoníaco');
            await expect(
                page.getByRole('heading', { name: 'Atendida Paco' }),
            ).toHaveCount(0);
        }
    });

    // Opened as a full page load (F5, bookmark, link from outside): the file
    // is served with `no-store`, so the browser has nothing to show on Back.
    test('cerrar sesión y «Atrás» no devuelven la ficha (carga completa del documento)', async ({
        page,
        browser,
    }, testInfo) => {
        const attended = await lookup(browser, testInfo, 'Atendida Paco');
        await signIn(page, fabia);
        await page.goto(attended.href);
        await expect(
            page.getByRole('textbox', { name: 'Alergias y cuidado' }),
        ).toHaveValue(CARE);
        await page.goto('/dashboard');
        await logout(page);
        for (let i = 0; i < 2; i += 1) {
            await page.goBack().catch(() => undefined);
            await page.waitForTimeout(1500);
        }
        expect(await page.content()).not.toContain('amoníaco');
    });
});

test.describe('fotos con archivos reales', () => {
    // The oversized and malformed files are not in the repo: point QA_FILES
    // at a folder with grande.jpg, enorme.jpg and the rejected samples.
    test.skip(FILES === '', 'needs QA_FILES');

    test('sube, ve la miniatura y el visor, cabeceras privadas y elimina', async ({
        page,
        playwright,
    }, testInfo) => {
        const watch = watchForErrors(page);
        await signIn(page, fabia);
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Sin Datos' })
            .click();
        const section = page.getByRole('region', {
            name: 'Fotos antes y después',
        });
        // The upload form lives in a dialog opened with "Agregar foto".
        const dialog = page.getByRole('dialog', { name: 'Agregar foto' });
        const openUpload = () =>
            section.getByRole('button', { name: 'Agregar foto' }).click();
        const consent = dialog.getByRole('checkbox', {
            name: /autorizó estas fotos para uso interno/,
        });
        const save = dialog.getByRole('button', { name: 'Guardar foto' });
        const input = dialog.getByLabel('Foto del trabajo', { exact: true });
        const count = async () =>
            Number(
                (await section.getByLabel(/ de 40 fotos$/).innerText()).split(
                    ' ',
                )[0],
            );
        const before = await count();

        const upload = async (file: string, kind: 'Antes' | 'Después') => {
            await openUpload();
            await dialog.getByText(kind, { exact: true }).click();
            await input.setInputFiles(file);
            await consent.check();
            await save.click();
        };

        // A big phone-like photo (11 MB, 3900x3000) is scaled down by the browser.
        await upload(join(FILES, 'grande.jpg'), 'Antes');
        await expect(page.getByText('Foto guardada.')).toBeVisible({
            timeout: 30_000,
        });
        await expect.poll(count, { timeout: 25_000 }).toBe(before + 1);
        // A repo photo.
        await upload(join(IMAGES, 'pexels-1813272.jpg'), 'Después');
        await expect.poll(count, { timeout: 25_000 }).toBe(before + 2);

        const tile = section.getByRole('button', {
            name: /^Ver foto: Después/,
        });
        await expect(tile).toBeVisible();
        const thumb = section.locator('img[src*="/fotos/"]').first();
        await expect
            .poll(() =>
                thumb.evaluate((el: HTMLImageElement) => el.naturalWidth),
            )
            .toBeGreaterThan(0);
        expect(await hasHorizontalScroll(page)).toBe(false);
        await tile.click();
        const viewer = page.getByRole('dialog', { name: 'Foto' });
        await expect(viewer.getByAltText(/^Después · /)).toBeVisible();
        const viewerImg = viewer.locator('img').first();
        await expect
            .poll(() =>
                viewerImg.evaluate((el: HTMLImageElement) => el.naturalWidth),
            )
            .toBeGreaterThan(0);
        await page.screenshot({
            path: join(
                process.env.QA_SHOTS ?? 'test-results-2',
                `visor-${testInfo.project.name}.png`,
            ),
        });
        await page.keyboard.press('Escape');

        // Headers of the picture.
        const src = (await thumb.getAttribute('src'))!;
        const res = await page.request.get(src);
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toBe('image/webp');
        const cache = res.headers()['cache-control'];
        expect(cache).toContain('private');
        expect(cache).toContain('no-store');
        expect(cache).not.toContain('public');
        // WebP re-encoding: RIFF....WEBP, and it fits the 2000 px of the first.
        const body = await res.body();
        expect(body.subarray(0, 4).toString()).toBe('RIFF');
        expect(body.subarray(8, 12).toString()).toBe('WEBP');
        // No EXIF block survives in the file.
        expect(body.includes(Buffer.from('Exif'))).toBe(false);
        // The same URL without a session is not served.
        const anon = await playwright.request.newContext({
            baseURL: testInfo.project.use.baseURL,
        });
        const noSession = await anon.get(src, { maxRedirects: 0 });
        expect(noSession.status()).toBe(302);
        await anon.dispose();

        // Remove what this test added.
        for (let i = 0; i < 2; i += 1) {
            // Removing is inside the viewer of each picture.
            await section
                .getByRole('button', { name: /^Ver foto/ })
                .first()
                .click();
            await page.getByRole('button', { name: /^Eliminar foto/ }).click();
            await page.getByRole('button', { name: 'Sí, eliminar' }).click();
            await expect(page.getByText('Foto eliminada.')).toBeVisible({
                timeout: 25_000,
            });
            await expect.poll(count, { timeout: 25_000 }).toBe(before + 1 - i);
        }
        expect((await page.request.get(src)).status()).toBe(404);
        watch();
    });

    test('archivos que no se aceptan: más de 5 MB, svg, gif, falso .jpg y de más de 4000 px', async ({
        page,
    }) => {
        // Five uploads, each waited on: well over the default minute on a phone.
        test.setTimeout(240_000);
        await signIn(page, fabia);
        await page.goto('/clientes');
        await page
            .getByRole('link', { name: 'Ver ficha de Sin Datos' })
            .click();
        const section = page.getByRole('region', {
            name: 'Fotos antes y después',
        });
        // The upload form lives in a dialog opened with "Agregar foto".
        const dialog = page.getByRole('dialog', { name: 'Agregar foto' });
        const openUpload = () =>
            section.getByRole('button', { name: 'Agregar foto' }).click();
        const consent = dialog.getByRole('checkbox', {
            name: /autorizó estas fotos para uso interno/,
        });
        const save = dialog.getByRole('button', { name: 'Guardar foto' });
        const input = dialog.getByLabel('Foto del trabajo', { exact: true });
        // By attribute, not by role: while the upload dialog stays open (a
        // refused file keeps it open) the page behind is hidden from roles.
        const count = async () =>
            Number(
                (
                    (await page
                        .locator('[aria-label$=" de 40 fotos"]')
                        .getAttribute('aria-label')) ?? ''
                ).split(' ')[0],
            );
        const before = await count();
        const outcomes: Record<string, string> = {};

        for (const file of [
            'enorme.jpg',
            'logo.svg',
            'anim.gif',
            'falsa.jpg',
            'ancha-5000.jpg',
        ]) {
            await page.reload();
            await openUpload();
            await input.setInputFiles(join(FILES, file));
            await page.waitForTimeout(1500);
            if (await consent.isVisible()) {
                await consent.check();
            }
            if (await save.isEnabled()) {
                await save.click();
                await page
                    .locator(
                        '[role="alert"]:not(:empty), [data-sonner-toast], :text("Foto guardada.")',
                    )
                    .first()
                    .waitFor({ timeout: 25_000 })
                    .catch(() => undefined);
                await page.waitForTimeout(800);
            }
            const alerts = await dialog.getByRole('alert').allInnerTexts();
            const toast = await page.getByText('Foto guardada.').count();
            outcomes[file] =
                `alertas=${JSON.stringify(alerts)} guardada=${toast} fotos=${await count()}`;
        }
        console.log('RESULTADOS', JSON.stringify(outcomes, null, 1));
        // Whatever the UX says, svg, gif, fake and oversize-dimension never land.
        for (const file of [
            'logo.svg',
            'anim.gif',
            'falsa.jpg',
            'ancha-5000.jpg',
        ]) {
            expect(outcomes[file], file).toContain('guardada=0');
        }
        // The browser scales big photos down; the server still refuses 5 MB+ sent straight.
        const token = decodeURIComponent(
            (await page.context().cookies()).find(
                (c) => c.name === 'XSRF-TOKEN',
            )!.value,
        );
        const direct = await page.request.post(
            `${new URL(page.url()).pathname}/fotos`,
            {
                headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' },
                multipart: {
                    kind: 'before',
                    consent: '1',
                    photo: {
                        name: 'enorme.jpg',
                        mimeType: 'image/jpeg',
                        buffer: (await import('node:fs')).readFileSync(
                            join(FILES, 'enorme.jpg'),
                        ),
                    },
                },
            },
        );
        // 413 when PHP's post_max_size cuts it first, 422 from the rule.
        expect([413, 422]).toContain(direct.status());
        // Leave nothing behind (enorme.jpg may legitimately be scaled and kept).
        await page.reload();
        while ((await count()) > before) {
            // Removing is inside the viewer of each picture.
            await section
                .getByRole('button', { name: /^Ver foto/ })
                .first()
                .click();
            await page.getByRole('button', { name: /^Eliminar foto/ }).click();
            await page.getByRole('button', { name: 'Sí, eliminar' }).click();
            await expect(page.getByText('Foto eliminada.')).toBeVisible({
                timeout: 25_000,
            });
        }
    });
});
