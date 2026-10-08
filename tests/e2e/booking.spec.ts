import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

const client = { email: 'cliente@example.com', password: 'password' };
const multi = { email: 'multi@example.com', password: 'password' };

/** Steps 1 to 3 of the wizard: service, professional, tomorrow's first time. */
async function chooseUntilConfirm(page: Page) {
    await page.getByRole('button', { name: /Corte de prueba/ }).click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    await page.getByRole('button', { name: /Siempre Abierto/ }).click();

    await expect(page.getByRole('heading', { name: '¿Cuándo?' })).toBeVisible();
    // Tomorrow: always has free times, whatever the hour the suite runs at.
    await page.getByRole('radio').nth(1).click();
    await page
        .getByRole('button', { name: /^\d{1,2}:\d{2} [ap]\. m\.$/ })
        .first()
        .click();
    await expect(
        page.getByRole('heading', { name: 'Revisa y confirma' }),
    ).toBeVisible();
}

test.describe('Reservar en línea', () => {
    test('una clienta reserva desde la página del salón y el salón la ve en su agenda', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, client.email, client.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/salon-uno');
        await page.getByRole('link', { name: 'Reservar', exact: true }).click();
        await expect(page).toHaveURL(/\/s\/salon-uno\/reservar/);

        await chooseUntilConfirm(page);
        await expect(page.getByText('S/ 35.00')).toBeVisible();
        await page
            .getByLabel('¿Algo que debamos saber? (opcional)')
            .fill('Degradado bajo');
        await page.getByRole('button', { name: 'Confirmar reserva' }).click();

        await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
        await expect(
            page.getByText('Confirmada', { exact: true }),
        ).toBeVisible();
        await expect(page.getByText('Corte de prueba')).toBeVisible();
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);
        assertNoErrors();

        // The salon's side: tomorrow's agenda shows it and can cancel it.
        await page.context().clearCookies();
        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/agenda');
        await page.getByRole('button', { name: 'Día siguiente' }).click();
        const appointment = page.getByRole('article', {
            name: /Carla Cliente/,
        });
        await expect(appointment).toContainText('Degradado bajo');
        await appointment.getByRole('button', { name: 'Cancelar' }).click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        await expect(page.getByText('Reserva cancelada.')).toBeVisible();
        await expect(appointment).toContainText('Cancelada');
        assertNoErrors();
    });

    test('repetir una cita abre el asistente ya en "¿Cuándo?", y se puede adjuntar una foto', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, client.email, client.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/salon-uno/reservar');
        await chooseUntilConfirm(page);
        await page.getByRole('button', { name: 'Confirmar reserva' }).click();
        await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
        const code = page.url().split('/').pop();

        // "Mi estilo": a 1x1 PNG is enough to go through the real upload.
        await page.getByLabel('Subir foto').setInputFiles({
            name: 'estilo.png',
            mimeType: 'image/png',
            buffer: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                'base64',
            ),
        });
        await expect(
            page.getByAltText('Foto de referencia de tu estilo'),
        ).toBeVisible();

        await page.goto(`/s/salon-uno/reservar?repetir=${code}`);
        await expect(
            page.getByRole('heading', { name: '¿Cuándo?' }),
        ).toBeVisible();
        await expect(page.getByText('Con Siempre Abierto')).toBeVisible();

        await page.goto(`/mis-reservas/${code}`);
        await page.getByRole('button', { name: 'Cancelar reserva' }).click();
        await page.getByRole('button', { name: 'Sí, cancelar' }).click();
        await expect(
            page.getByText('Cancelada', { exact: true }),
        ).toBeVisible();
        assertNoErrors();
    });

    test('un visitante elige todo y se le pide entrar solo para confirmar', async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);

        await page.goto('/s/salon-uno/reservar');
        await chooseUntilConfirm(page);

        const signIn = page.getByRole('link', {
            name: 'Entrar para confirmar',
        });
        await expect(signIn).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Confirmar reserva' }),
        ).toHaveCount(0);
        assertNoErrors();

        await signIn.click();
        await expect(page).toHaveURL(/\/login$/);
        await login(page, client.email, client.password);

        // Back in the wizard with the selection restored, ready to confirm.
        await expect(page).toHaveURL(/\/s\/salon-uno\/reservar\?continuar=1$/);
        await expect(
            page.getByRole('button', { name: 'Confirmar reserva' }),
        ).toBeVisible();
        await expect(page.getByText('Corte de prueba')).toBeVisible();
    });

    test('el asistente no tiene scroll horizontal ni errores (claro y oscuro)', async ({
        page,
    }) => {
        for (const colorScheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);

            await page.goto('/s/salon-uno/reservar');
            // A wizard left half-way is picked up again: start clean each time.
            await page.evaluate(() => window.sessionStorage.clear());
            await page.goto('/s/salon-uno/reservar');
            await chooseUntilConfirm(page);
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBe(false);
            assertNoErrors();
        }
    });

    test('sin sesión, "Mis reservas" y la agenda piden entrar', async ({
        page,
    }) => {
        await page.goto('/mis-reservas');
        await expect(page).toHaveURL(/\/login$/);
        await page.goto('/agenda');
        await expect(page).toHaveURL(/\/login$/);
    });
});
