import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

/*
 * "Salón Espera" (E2eSeeder): its only professional attends one cut a week,
 * already taken every week. The first of those days is three days from now;
 * on the second one each account was already told that a time opened up.
 */
const FIRST_FULL_DAY = 3;
const SECOND_FULL_DAY = 10;
const DAY_MS = 24 * 60 * 60 * 1000;

/** One account per project: both run against the same database. */
const accounts: Record<string, string> = {
    movil: 'espera-movil@example.com',
    escritorio: 'espera-escritorio@example.com',
};

const isoFormat = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Lima',
});
const longFormat = new Intl.DateTimeFormat('es-PE', {
    timeZone: 'America/Lima',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

function daysFromNow(offset: number): Date {
    return new Date(Date.now() + offset * DAY_MS);
}

/** "jueves 15 de octubre", as the cards of "Mis reservas" write it. */
function longDay(offset: number): string {
    return longFormat.format(daysFromNow(offset)).replace(',', '');
}

/** Service and professional: the wizard is left choosing day and time. */
async function chooseUntilTimes(page: Page, isServiceChosen = false) {
    // The link of a freed time brings the service already picked.
    if (!isServiceChosen) {
        await page.getByRole('button', { name: /Corte de espera/ }).click();
    }

    await page.getByRole('button', { name: 'Continuar' }).click();
    await page.getByRole('button', { name: /Elsa Espera/ }).click();
    await expect(page.getByRole('heading', { name: '¿Cuándo?' })).toBeVisible();
}

/** The days that can be picked: the salon opens one day a week. */
function openDays(page: Page) {
    return page.getByRole('radio', { disabled: false });
}

test.describe('Lista de espera', () => {
    test('quien no encuentra hora se anota, lo ve en Mis reservas y sale de la lista', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);

        await login(page, accounts[testInfo.project.name], 'password');
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/s/salon-espera/reservar');
        await chooseUntilTimes(page);

        // The first open day is the full one.
        await expect(openDays(page).first()).toBeChecked();
        await expect(
            page.getByText(/No quedan horarios este día/),
        ).toBeVisible();
        await expect(
            page.getByText(
                '¿Quieres que te avisemos si se libera un horario este día?',
            ),
        ).toBeVisible();
        await expect(
            page.getByText(
                /Avisamos a una persona a la vez\. Tendrás 30 minutos/,
            ),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Avísame si se libera' })
            .click();
        await expect(
            page.getByText(
                'Listo: te avisaremos si se libera un horario ese día.',
            ),
        ).toBeVisible();
        await expect(
            page.getByRole('button', {
                name: 'Ya estás en la lista de ese día',
            }),
        ).toBeDisabled();
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);

        await page.goto('/mis-reservas');
        await expect(
            page.getByRole('heading', { name: 'Lista de espera' }),
        ).toBeVisible();
        const place = page.getByRole('article', {
            name: `Salón Espera, ${longDay(FIRST_FULL_DAY)}`,
        });
        await expect(place).toContainText('Corte de espera');
        await expect(place).toContainText(
            'Te avisaremos si se libera un horario.',
        );
        await expect(
            place.getByRole('link', { name: 'Reservar ahora' }),
        ).toHaveCount(0);

        await place.getByRole('button', { name: 'Salir de la lista' }).click();
        await page
            .getByRole('button', { name: 'Sí, salir de la lista' })
            .click();
        await expect(
            page.getByText('Saliste de la lista de espera.'),
        ).toBeVisible();
        await expect(place).toHaveCount(0);
        assertNoErrors();
    });

    test('a quien le toca el turno se le dice hasta cuándo, y el enlace abre el asistente en ese día', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);

        await login(page, accounts[testInfo.project.name], 'password');
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mis-reservas');

        const place = page.getByRole('article', {
            name: `Salón Espera, ${longDay(SECOND_FULL_DAY)}`,
        });
        await expect(place).toContainText(
            /Se liberó un horario\. Es tu turno hasta las \d{1,2}:\d{2} [ap]\. m\./,
        );
        await place.getByRole('link', { name: 'Reservar ahora' }).click();
        await expect(page).toHaveURL(
            new RegExp(
                `/s/salon-espera/reservar\\?fecha=${isoFormat.format(daysFromNow(SECOND_FULL_DAY))}&servicio=\\d+$`,
            ),
        );

        // Without the link the wizard would start on the first open day.
        await chooseUntilTimes(page, true);
        await expect(openDays(page).nth(1)).toBeChecked();
        await expect(openDays(page).first()).not.toBeChecked();
        assertNoErrors();
    });

    test('un visitante entra para anotarse y vuelve al mismo día', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);

        await page.goto('/s/salon-espera/reservar');
        await chooseUntilTimes(page);

        const signIn = page.getByRole('link', { name: 'Avísame si se libera' });
        await expect(signIn).toBeVisible();
        assertNoErrors();

        await signIn.click();
        await expect(page).toHaveURL(/\/login$/);
        await login(page, accounts[testInfo.project.name], 'password');

        await expect(page).toHaveURL(
            /\/s\/salon-espera\/reservar\?continuar=1$/,
        );
        await expect(
            page.getByRole('heading', { name: '¿Cuándo?' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Avísame si se libera' }),
        ).toBeVisible();
    });
});
