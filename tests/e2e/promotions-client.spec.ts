import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

// "Salón Ofertas" (E2eSeeder): Olivia works every day from 7 to 22, the
// salon asks a 30 % deposit and runs two promotions. A discount on "Corte de
// oferta" (S/ 40 → S/ 30) that holds from 9 to 18, and the add-on "Lavado
// de oferta" (S/ 10, 10 min) as a gift with "Barba de oferta" (S/ 20).
// A combo is not seeded here: a third promotion in "Salón Ofertas" would
// change what these tests count. What a visitor sees of one (its card, the
// "Combo" mark and the wizard with both services ticked) is checked at the
// end of the combo test in promotions-owner.spec.ts, right after publishing.
const SALON = '/s/salon-ofertas';
const DISCOUNT = '25 % menos en Corte de oferta';
const GIFT = 'Lavado de oferta gratis con tu Barba de oferta';
const PROMOTION_LINE = `Promoción ${DISCOUNT}: −S/ 10.00`;

const strip = (page: Page) => page.getByRole('region', { name: 'Promociones' });
const card = (page: Page, title: string) =>
    strip(page).getByRole('listitem', { name: title });
const option = (page: Page, service: string) =>
    page.getByRole('button', { name: new RegExp(service) });

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

test.describe('Promociones: lo que ve el cliente', () => {
    test('un visitante ve la franja con el precio tachado y la marca en los servicios', async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);

        await page.goto(SALON);

        const discount = card(page, DISCOUNT);
        await expect(discount).toContainText('Precio normal S/ 40.00');
        await expect(discount).toContainText('Con promoción S/ 30.00');
        await expect(discount).toContainText('de 09:00 a 18:00');
        // How long it runs is read without opening the conditions.
        await expect(discount).toContainText(/Válido del \d.* de \d{4}/);
        await expect(discount).not.toContainText('Aplica a:');
        await discount.getByRole('button', { name: 'Ver condiciones' }).click();
        await expect(discount).toContainText('Promoción de Salón Ofertas.');
        await expect(discount).toContainText('Aplica a: Corte de oferta.');
        await expect(discount).toContainText(
            'Solo en citas que empiecen entre las 09:00 y las 18:00.',
        );

        const gift = card(page, GIFT);
        await expect(gift).toContainText('Regreso a clases');
        await expect(gift).toContainText(
            'Lavado de oferta gratis · Ahorras S/ 10.00',
        );
        await expect(
            strip(page).getByRole('link', { name: /^Reservar con esta promo/ }),
        ).toHaveCount(2);
        await expect(strip(page).getByText('Tu promoción')).toHaveCount(0);

        // The list of services: the cheaper one is marked, the other is not.
        const services = page.getByRole('region', { name: 'Servicios' });
        const cut = services
            .getByRole('listitem')
            .filter({ hasText: 'Corte de oferta' });
        await expect(cut).toContainText('Promo');
        await expect(cut).toContainText('Precio normal S/ 40.00');
        await expect(cut).toContainText('Con promoción S/ 30.00');
        // It holds from 9 to 18 only: the price says not every time gets it.
        await expect(cut).toContainText('en horarios con promo');
        // With the whole team: neither the card nor the price names anyone.
        await expect(cut).not.toContainText('con Olivia');
        await expect(discount).not.toContainText('Con Olivia');
        await expect(
            services
                .getByRole('listitem')
                .filter({ hasText: 'Barba de oferta' }),
        ).not.toContainText('Promo');

        expect(await hasHorizontalScroll(page)).toBe(false);
        assertNoErrors();
    });

    test('el enlace de una promoción la muestra primero y destacada; uno que no existe dice que ya no está disponible', async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);

        await page.goto(`${SALON}/promo/ofertaregalo`);
        await expect(page).toHaveURL(/salon-ofertas\?promo=ofertaregalo$/);

        const first = strip(page).getByRole('listitem').first();
        await expect(first).toHaveAccessibleName(GIFT);
        await expect(first).toContainText('Tu promoción');
        await expect(strip(page).getByText('Tu promoción')).toHaveCount(1);
        await expect(first).toBeInViewport();
        await expect(
            page.getByText('Esta promoción ya no está disponible.'),
        ).toHaveCount(0);

        // A code that never was (or one that is over): never an error page.
        await page.goto(`${SALON}/promo/noexiste99`);
        const ended = strip(page).getByRole('status');
        await expect(ended).toContainText(
            'Esta promoción ya no está disponible.',
        );
        await expect(ended).toContainText('Puedes reservar al precio normal.');
        await expect(
            ended.getByRole('link', { name: 'Reservar cita' }),
        ).toHaveAttribute('href', /\/s\/salon-ofertas\/reservar$/);
        // What is running today is still on offer, none of it pointed at.
        await expect(card(page, DISCOUNT)).toBeVisible();
        await expect(strip(page).getByText('Tu promoción')).toHaveCount(0);

        expect(await hasHorizontalScroll(page)).toBe(false);
        assertNoErrors();
    });

    test('desde la promoción de regalo el asistente abre con los dos servicios, y el regalo se quita y se vuelve a ofrecer', async ({
        page,
    }) => {
        const assertNoErrors = watchForErrors(page);

        await page.goto(SALON);
        await card(page, GIFT)
            .getByRole('link', { name: /^Reservar con esta promo/ })
            .click();
        await expect(page).toHaveURL(/\/reservar\?promo=\d+$/);

        const beard = option(page, 'Barba de oferta');
        const wash = option(page, 'Lavado de oferta');
        await expect(beard).toHaveAttribute('aria-pressed', 'true');
        await expect(wash).toHaveAttribute('aria-pressed', 'true');
        await expect(wash).toContainText('Gratis');
        await expect(
            page.getByText('Tu regalo: Lavado de oferta (+10 min)'),
        ).toBeVisible();
        // The cut is cheaper with the other promotion, and says so.
        await expect(option(page, 'Corte de oferta')).toContainText(
            'Con promoción S/ 30.00en horarios con promo',
        );
        await expect(wash).not.toContainText('en horarios con promo');
        await expect(page.getByText('desde S/ 20.00')).toBeVisible();

        // Without what earns it, the gift costs what it costs.
        await beard.click();
        await expect(wash).not.toContainText('Gratis');
        await expect(wash).toContainText('S/ 10.00');

        // Taken off and earned again: offered, one tap adds it.
        await wash.click();
        await beard.click();
        await expect(
            page.getByText('Te regalamos Lavado de oferta (+10 min)'),
        ).toBeVisible();
        await expect(wash).toHaveAttribute('aria-pressed', 'false');
        await page.getByRole('button', { name: 'Agregar gratis' }).click();
        await expect(wash).toHaveAttribute('aria-pressed', 'true');
        await expect(wash).toContainText('Gratis');

        expect(await hasHorizontalScroll(page)).toBe(false);
        assertNoErrors();
    });

    test('una persona reserva desde la franja: horarios marcados, línea de la promoción, total rebajado y Mis reservas', async ({
        page,
    }, testInfo) => {
        test.setTimeout(120_000);
        const assertNoErrors = watchForErrors(page);

        await login(
            page,
            `ofertas-${testInfo.project.name}@example.com`,
            'password',
        );
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto(SALON);
        await card(page, DISCOUNT)
            .getByRole('link', { name: /^Reservar con esta promo/ })
            .click();
        await expect(page).toHaveURL(/\/reservar\?promo=\d+$/);

        // Its only service arrives ticked.
        await expect(option(page, 'Corte de oferta')).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        await expect(option(page, 'Barba de oferta')).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        await page.getByRole('button', { name: 'Continuar' }).click();
        // It holds with anyone of the team: no one is marked for it.
        await expect(
            page.getByRole('button', { name: /Olivia Ofertas/ }),
        ).not.toContainText('con promo');
        await expect(page.getByText(/vale solo con/)).toHaveCount(0);
        await page.getByRole('button', { name: /Olivia Ofertas/ }).click();

        // Tomorrow: from 7 to 22, so some times have it and some do not.
        await expect(
            page.getByRole('heading', { name: '¿Cuándo?' }),
        ).toBeVisible();
        await expect(
            page.getByText(
                `${DISCOUNT} vale solo de 09:00 a 18:00. Los horarios que la tienen dicen «con promo».`,
            ),
        ).toBeVisible();
        await page.getByRole('radio').nth(1).click();
        await expect(
            page.getByText(
                `Los horarios marcados tienen ${DISCOUNT}: ahorras S/ 10.00.`,
            ),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /^7:00 a\. m\.$/ }),
        ).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);
        await page
            .getByRole('button', {
                name: /^\d{1,2}:\d{2} [ap]\. m\. con promo$/,
            })
            .first()
            .click();

        await expect(
            page.getByRole('heading', { name: 'Revisa y confirma' }),
        ).toBeVisible();
        await expect(page.getByText(PROMOTION_LINE)).toBeVisible();
        const summary = page.locator('dl').filter({ hasText: 'Total' });
        await expect(summary).toContainText('Precio normal S/ 40.00');
        await expect(summary).toContainText('S/ 30.00');
        // The deposit is the salon's 30 % of what is left to pay.
        await expect(
            page.getByText(/seña de\s+S\/ 9\.00\s+\(30%\)/),
        ).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);

        await page.getByRole('button', { name: 'Confirmar reserva' }).click();
        await expect(page).toHaveURL(/\/mis-reservas\/[A-Z0-9]{8}$/);
        await expect(page.getByText(PROMOTION_LINE)).toBeVisible();
        const booked = page.locator('dl').filter({ hasText: 'Total' });
        await expect(
            booked.getByRole('listitem').filter({ hasText: 'Corte de oferta' }),
        ).toContainText('Precio normal S/ 40.00S/ 30.00');
        await expect(booked).toContainText('S/ 30.00');
        expect(await hasHorizontalScroll(page)).toBe(false);

        await page.goto('/mis-reservas');
        await expect(
            page.getByRole('link', { name: /Salón Ofertas/ }).first(),
        ).toContainText(PROMOTION_LINE);
        expect(await hasHorizontalScroll(page)).toBe(false);
        assertNoErrors();
    });

    test('el personal agenda con la promoción marcada y puede quitarla', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        test.setTimeout(120_000);
        const assertNoErrors = watchForErrors(page);
        const stamp = Date.now() % 100000;
        const toggle = page.getByRole('checkbox', {
            name: `Aplicar promoción ${DISCOUNT} (−S/ 10.00)`,
        });
        const summary = page.locator('dl').filter({ hasText: 'Total' });

        /** Up to "¿Para quién?", on tomorrow's first time with the promotion. */
        const chooseUntilClient = async () => {
            await page.goto('/agenda/nueva');
            await option(page, 'Corte de oferta').click();
            await page.getByRole('button', { name: 'Continuar' }).click();
            await page.getByRole('button', { name: /Olivia Ofertas/ }).click();
            await page.getByRole('radio').nth(1).click();
            await page
                .getByRole('button', { name: /con promo$/ })
                .first()
                .click();
            await expect(
                page.getByRole('heading', { name: '¿Para quién?' }),
            ).toBeVisible();
        };

        await login(page, 'ofertas-duena@example.com', 'password');
        await expect(page).toHaveURL(/dashboard$/);

        // Ticked by default: booked at the promotion's price.
        await chooseUntilClient();
        await expect(toggle).toBeChecked();
        await expect(toggle).toHaveAccessibleDescription(
            'Si la desmarcas, la cita se cobra al precio normal. Díselo a la persona antes de guardar.',
        );
        await expect(summary).toContainText(PROMOTION_LINE);
        await expect(summary).toContainText('Precio normal S/ 40.00S/ 30.00');
        await page
            .getByLabel('Nombre de tu cliente')
            .fill(`Con Promo ${stamp}`);
        expect(await hasHorizontalScroll(page)).toBe(false);
        await page.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(page).toHaveURL(/\/agenda\?fecha=\d{4}-\d{2}-\d{2}$/);
        const withPromo = page.getByRole('article', {
            name: new RegExp(`Con Promo ${stamp}`),
        });
        await expect(withPromo).toContainText(PROMOTION_LINE);
        await expect(withPromo).toContainText('S/ 30.00');

        // Taken off: the normal price, and no promotion on the visit.
        await chooseUntilClient();
        await toggle.uncheck();
        await expect(summary).not.toContainText('Promoción');
        await expect(summary).toContainText('S/ 40.00');
        await page
            .getByLabel('Nombre de tu cliente')
            .fill(`Sin Promo ${stamp}`);
        await page.getByRole('button', { name: 'Agendar cita' }).click();
        await expect(page).toHaveURL(/\/agenda\?fecha=\d{4}-\d{2}-\d{2}$/);
        const without = page.getByRole('article', {
            name: new RegExp(`Sin Promo ${stamp}`),
        });
        await expect(without).toContainText('S/ 40.00');
        await expect(without).not.toContainText('Promoción');
        assertNoErrors();
    });
});
