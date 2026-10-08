import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
const client = { email: 'cliente@example.com', password: 'password' };
const vitrina = { email: 'vitrina@example.com', password: 'password' };
const TIME = /^\d{1,2}:\d{2} [ap]\. m\.$/;
const PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

/** A time of the afternoon that does not collide between the two projects. */
function slotLabel(testInfo: TestInfo, hour: number): string {
    return `${hour}:${testInfo.project.name === 'movil' ? '00' : '30'} p. m.`;
}

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

/** The team's wizard of Salón Uno up to "¿Para quién?", tomorrow at `time`. */
async function wizardToLastStep(page: Page, time: string, dayIndex = 1) {
    await page.goto('/agenda/nueva');
    await page.getByRole('button', { name: /Corte de prueba/ }).click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    await page.getByRole('button', { name: /Siempre Abierto/ }).click();
    await expect(page.getByRole('heading', { name: '¿Cuándo?' })).toBeVisible();
    await page.getByRole('radio').nth(dayIndex).click();
    await page.getByRole('button', { name: time, exact: true }).click();
    await expect(
        page.getByRole('heading', { name: '¿Para quién?' }),
    ).toBeVisible();
}

test.describe('Cierre de la Fase 4: nueva cita del personal', () => {
    test('cliente nuevo: los errores se ven en el formulario y se corrigen sin perder lo elegido', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        const name = 'Qa4 Ñandú Peña 💈';

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await wizardToLastStep(page, slotLabel(testInfo, 4));

        const save = page.getByRole('button', { name: 'Agendar cita' });
        // One letter is not a name: the button waits.
        await page.getByLabel('Nombre de tu cliente').fill('A');
        await expect(save).toBeDisabled();

        const suggestions = page.waitForResponse(/agenda\/clientes/);
        await page.getByLabel('Nombre de tu cliente').fill(name);
        await suggestions;
        await page.getByLabel('WhatsApp (opcional)').fill('nueve ocho');
        await expect(save).toBeEnabled();
        await save.click();

        await expect(
            page.getByText('Escribe el teléfono solo con números'),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/agenda\/nueva$/);
        await expect(page.getByLabel('Nombre de tu cliente')).toHaveValue(name);

        await page.getByLabel('WhatsApp (opcional)').fill('912340077');
        expect(await hasHorizontalScroll(page)).toBe(false);
        await save.click();

        await expect(page).toHaveURL(/\/agenda\?fecha=\d{4}-\d{2}-\d{2}$/);
        await expect(
            page.getByRole('article', {
                name: new RegExp(`Qa4 Ñandú.*${slotLabel(testInfo, 4)}`),
            }),
        ).toContainText('Confirmada');
        assertNoErrors();
    });

    test('con la búsqueda de clientes todavía en curso, el error de validación también se ve', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await wizardToLastStep(page, slotLabel(testInfo, 2));
        // A slow connection: the suggestions arrive while the form is sent.
        await page.route('**/agenda/clientes*', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 300));
            await route.continue();
        });

        await page.getByLabel('WhatsApp (opcional)').fill('nueve ocho');
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Rápido');
        await page.getByRole('button', { name: 'Agendar cita' }).click();

        await expect(
            page.getByText('Escribe el teléfono solo con números'),
        ).toBeVisible();
        assertNoErrors();
    });

    test('cliente elegido del buscador: se reutiliza la ficha y se puede cambiar de opinión', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        // A client to find later.
        await wizardToLastStep(page, slotLabel(testInfo, 3));
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Ñandú Peña 💈');
        await page.getByLabel('WhatsApp (opcional)').fill('912340077');
        await page.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(page).toHaveURL(/\/agenda\?fecha=/);

        await wizardToLastStep(page, slotLabel(testInfo, 5));
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Ñan');
        const list = page.getByRole('list', {
            name: 'Clientes que ya están en tu lista',
        });
        await expect(list).toBeVisible();
        await list.getByRole('button', { name: /Qa4 Ñandú Peña/ }).click();

        await expect(page.getByText('Qa4 Ñandú Peña 💈').first()).toBeVisible();
        await expect(page.getByText('912340077')).toBeVisible();
        // Change of mind: back to typing.
        await page.getByRole('button', { name: 'Cambiar' }).click();
        await expect(page.getByLabel('Nombre de tu cliente')).toHaveValue('');
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Ñan');
        await list.getByRole('button', { name: /Qa4 Ñandú Peña/ }).click();

        await page.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(
            page.getByRole('article', {
                name: new RegExp(`Qa4 Ñandú Peña.*${slotLabel(testInfo, 5)}`),
            }),
        ).toBeVisible();
        await expect(
            page
                .getByRole('article', {
                    name: /Qa4 Ñandú Peña.*5:\d0 p\. m\./,
                })
                .first(),
        ).toBeVisible();
        assertNoErrors();
    });

    test('si el horario se ocupa mientras se llena el último paso, vuelve a "¿Cuándo?" con aviso y sin ofrecer ese horario', async ({
        page,
        context,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        const time = slotLabel(testInfo, 6);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await wizardToLastStep(page, time);
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Primero');

        // Someone else of the team takes that very time meanwhile.
        const other = await context.newPage();
        await wizardToLastStep(other, time);
        await other.getByLabel('Nombre de tu cliente').fill('Qa4 Se adelantó');
        await other.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(other).toHaveURL(/\/agenda\?fecha=/);
        await other.close();

        await page.getByRole('button', { name: 'Agendar cita' }).click();

        await expect(
            page.getByRole('heading', { name: '¿Cuándo?' }),
        ).toBeVisible();
        // The taken time must not be offered again; the others still are.
        await expect(
            page.getByRole('button', { name: TIME }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: time, exact: true }),
        ).toHaveCount(0);
        assertNoErrors();
    });
});

test.describe('Cierre de la Fase 4: agenda', () => {
    test('las vistas conservan el equipo, la semana navega y lo cancelado no está en la rejilla', async ({
        page,
    }, testInfo) => {
        test.skip(testInfo.project.name !== 'movil', 'modifica datos una vez');
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);

        // A booking to cancel, then it must disappear from the grid only.
        await wizardToLastStep(page, '7:15 p. m.');
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Cancelada');
        await page.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(page).toHaveURL(/\/agenda\?fecha=/);
        const day = new URL(page.url()).searchParams.get('fecha')!;
        const card = page.getByRole('article', { name: /Qa4 Cancelada/ });
        // On a phone the toast of the booking just made covers the last card.
        await card
            .getByRole('button', { name: 'Cancelar' })
            .dispatchEvent('click');
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        await expect(card).toContainText('Cancelada');

        await page.goto(`/agenda?vista=tablero&fecha=${day}`);
        await expect(
            page.getByRole('group', { name: 'Marta Multi' }),
        ).toBeVisible();
        await expect(
            page.getByRole('group', { name: 'Siempre Abierto' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /Qa4 Cancelada/ }),
        ).toHaveCount(0);
        expect(await hasHorizontalScroll(page)).toBe(false);

        // Week: the arrows move by seven days and "Volver a hoy" returns.
        await page.goto('/agenda?vista=semana');
        await expect(
            page.getByRole('heading', { name: 'Esta semana' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Semana siguiente' }).click();
        await expect(page).toHaveURL(/fecha=/);
        await expect(
            page.getByRole('button', { name: 'Volver a hoy' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Semana anterior' }).click();
        await expect(
            page.getByRole('heading', { name: 'Esta semana' }),
        ).toBeVisible();

        // The week is one professional's; going back to the list is the whole team again.
        await page.getByRole('radio', { name: 'Lista' }).click();
        await expect(
            page.getByRole('combobox', { name: 'Profesional' }),
        ).toContainText('Todo el equipo');
        assertNoErrors();
    });

    test('Hoy: la cita que sigue muestra su cuenta regresiva', async ({
        page,
    }, testInfo) => {
        test.skip(testInfo.project.name !== 'movil', 'modifica datos una vez');
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/agenda/nueva');
        await page.getByRole('button', { name: /Corte de prueba/ }).click();
        await page.getByRole('button', { name: 'Continuar' }).click();
        await page.getByRole('button', { name: /Siempre Abierto/ }).click();
        // Today (first day of the strip): the latest time left, so it is still ahead.
        await page.getByRole('radio').nth(0).click();
        const times = page.getByRole('button', { name: TIME });
        test.skip((await times.count()) === 0, 'sin horarios hoy a esta hora');
        await times.last().click();
        await page.getByLabel('Nombre de tu cliente').fill('Qa4 Hoy');
        await page.getByRole('button', { name: 'Agendar cita' }).click();

        await expect(page).toHaveURL(/\/agenda\?fecha=/);
        const summary = page.getByRole('list', { name: 'Resumen de hoy' });
        await expect(summary).toContainText('Sigue');
        await expect(summary).toContainText(/en \d/);
        // Cleanup.
        await page
            .getByRole('article', { name: /Qa4 Hoy/ })
            .getByRole('button', { name: 'Cancelar' })
            .click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        assertNoErrors();
    });

    test('en escritorio, el tablero reparte una cita a otro profesional arrastrándola, y rechaza a quien no hace el servicio', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'escritorio',
            'arrastrar necesita ratón',
        );
        const assertNoErrors = watchForErrors(page);
        const name = 'Qa4 Tablero';

        await login(page, vitrina.email, vitrina.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/agenda/nueva');
        await page.getByRole('button', { name: /Corte clásico/ }).click();
        await page.getByRole('button', { name: 'Continuar' }).click();
        await page.getByRole('button', { name: /José Antonio/ }).click();
        await page.getByRole('radio').nth(1).click();
        await page
            .getByRole('button', { name: '12:00 p. m.', exact: true })
            .click();
        await page.getByLabel('Nombre de tu cliente').fill(name);
        await page.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(page).toHaveURL(/\/agenda\?fecha=/);
        const day = new URL(page.url()).searchParams.get('fecha')!;

        await page.goto(`/agenda?vista=tablero&fecha=${day}`);
        const jose = page.getByRole('group', {
            name: 'José Antonio Fernández',
        });
        const lucia = page.getByRole('group', { name: 'Lucía Ramírez' });
        const valeria = page.getByRole('group', { name: 'Valeria Vitrina' });
        const block = jose.getByRole('button', { name: new RegExp(name) });
        await block.scrollIntoViewIfNeeded();

        const drag = async (target: typeof lucia) => {
            const from = (await block.boundingBox())!;
            const to = (await target.boundingBox())!;
            await page.mouse.move(from.x + from.width / 2, from.y + 8);
            await page.mouse.down();
            await page.mouse.move(to.x + to.width / 2, from.y + 8, {
                steps: 10,
            });
            await page.mouse.up();
        };

        // Valeria does not perform the service: the server says no and nothing moves.
        await drag(valeria);
        await expect(
            page.getByText(/no puede atender esta cita/),
        ).toBeVisible();
        await expect(
            jose.getByRole('button', { name: new RegExp(name) }),
        ).toBeVisible();

        // Lucía does.
        await drag(lucia);
        await expect(
            page.getByText('Cita movida. Avísale a tu cliente.'),
        ).toBeVisible();
        await expect(
            lucia.getByRole('button', { name: new RegExp(name) }),
        ).toBeVisible();
        await expect(
            jose.getByRole('button', { name: new RegExp(name) }),
        ).toHaveCount(0);

        // Tap it: its options, then clean up.
        await lucia.getByRole('button', { name: new RegExp(name) }).click();
        const dialog = page.getByRole('dialog');
        await expect(
            dialog.getByRole('button', { name: 'Mover' }),
        ).toBeVisible();
        await dialog.getByRole('button', { name: 'Cancelar' }).click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        assertNoErrors();
    });
});

test.describe('Cierre de la Fase 4: Mis reservas', () => {
    test('cambiar día u hora: un rechazo deja el diálogo abierto con horarios nuevos; la foto se sube, cambia, se quita y el equipo la ve', async ({
        page,
        browser,
        baseURL,
    }, testInfo) => {
        test.skip(testInfo.project.name !== 'movil', 'modifica datos una vez');
        const assertNoErrors = watchForErrors(page);

        // The client books three days ahead (always inside the notice window).
        await login(page, client.email, client.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/salon-uno/reservar');
        await page.getByRole('button', { name: /Corte de prueba/ }).click();
        await page.getByRole('button', { name: 'Continuar' }).click();
        await page.getByRole('button', { name: /Siempre Abierto/ }).click();
        await page.getByRole('radio').nth(3).click();
        await page
            .getByRole('button', { name: '9:00 a. m.', exact: true })
            .click();
        await page.getByRole('button', { name: 'Confirmar reserva' }).click();
        await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
        const bookingUrl = page.url();

        // Photo: upload, change, remove, upload again.
        const upload = (label: string) =>
            page.getByLabel(label).setInputFiles({
                name: 'estilo.png',
                mimeType: 'image/png',
                buffer: PNG,
            });
        await upload('Subir foto');
        const photo = page.getByAltText('Foto de referencia de tu estilo');
        await expect(photo).toBeVisible();
        const firstSrc = await photo.getAttribute('src');
        await upload('Cambiar foto');
        await expect(photo).not.toHaveAttribute('src', firstSrc!);
        await page.getByRole('button', { name: 'Quitar foto' }).click();
        await expect(photo).toHaveCount(0);
        await expect(page.getByLabel('Subir foto')).toBeAttached();
        await upload('Subir foto');
        await expect(photo).toBeVisible();

        // Move: pick a time, let the team take it, press the button.
        await page.getByRole('button', { name: 'Cambiar día u hora' }).click();
        const dialog = page.getByRole('dialog');
        await dialog.getByRole('radio').nth(4).click();
        await dialog
            .getByRole('button', { name: '10:00 a. m.', exact: true })
            .click();

        const salon = await browser.newContext({
            baseURL,
            locale: 'es-PE',
            timezoneId: 'America/Lima',
        });
        const team = await salon.newPage();
        await login(team, multi.email, multi.password);
        await expect(team).toHaveURL(/dashboard$/);
        await wizardToLastStep(team, '10:00 a. m.', 4);
        await team
            .getByLabel('Nombre de tu cliente')
            .fill('Qa4 Ganó el horario');
        await team.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(team).toHaveURL(/agenda\?fecha=/);
        await expect(
            team.getByRole('article', { name: /Qa4 Ganó el horario/ }),
        ).toBeVisible();

        await dialog
            .getByRole('button', { name: /^(Mover|Cambiar) a las/ })
            .click();
        await expect.soft(page.getByText(/se acaba de ocupar/i)).toBeVisible();
        await expect(dialog).toBeVisible();
        await expect(
            dialog.getByRole('button', { name: '10:00 a. m.', exact: true }),
        ).toHaveCount(0);
        await expect(
            dialog.getByRole('button', { name: /^Elige un horario/ }),
        ).toBeVisible();

        // A free one works.
        await dialog.getByRole('button', { name: TIME }).first().click();
        await dialog
            .getByRole('button', { name: /^(Mover|Cambiar) a las/ })
            .click();
        await expect(
            page.getByText('Listo, cambiamos la hora de tu reserva.'),
        ).toBeVisible();
        await expect(dialog).toHaveCount(0);

        // The team sees the reference photo in its agenda, on the day it moved to.
        await team.goto(`/agenda?fecha=${limaDate(4)}`);
        await expect(team.getByText('Ver foto de referencia')).toBeVisible();
        await salon.close();

        // After cancelling, a photo can no longer be attached.
        await page.goto(bookingUrl);
        await page.getByRole('button', { name: 'Cancelar reserva' }).click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        await expect(
            page.getByText('Cancelada', { exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Cambiar foto')).toHaveCount(0);
        await expect(page.getByLabel('Subir foto')).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Cambiar día u hora' }),
        ).toHaveCount(0);
        assertNoErrors();
    });
});

/** A date of the salon's clock (Lima), `days` from today, as Y-m-d. */
function limaDate(days: number): string {
    return new Date(Date.now() + days * 86_400_000).toLocaleDateString(
        'en-CA',
        {
            timeZone: 'America/Lima',
        },
    );
}

for (const colorScheme of ['light', 'dark'] as const) {
    test(`pantallas nuevas sin scroll horizontal ni errores (${colorScheme})`, async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);
        await page.emulateMedia({ colorScheme });

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);

        for (const url of [
            '/agenda',
            '/agenda?vista=tablero',
            '/agenda?vista=semana',
            '/agenda/nueva',
        ]) {
            await page.goto(url);
            await expect(page.locator('h1').first()).toBeVisible();
            expect(
                await hasHorizontalScroll(page),
                `scroll horizontal en ${url} (${colorScheme})`,
            ).toBe(false);
        }

        assertNoErrors();
    });
}
