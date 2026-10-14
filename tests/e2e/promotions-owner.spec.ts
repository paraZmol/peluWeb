import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

// "Salón Promos …" (E2eSeeder): one salon per project, with a 30 % deposit,
// "Corte de promo" (S/ 35), "Barba de promo" (S/ 20) and the add-on
// "Lavado de promo" (S/ 10).
const PHONE = { width: 390, height: 844 };
const DISCOUNT = '20 % menos en Corte de promo';
const QUIET_HOURS = 'lunes, martes y miércoles, de 10:00 a 16:00';
const QUIET_STARTS =
    'lunes, martes y miércoles, en citas que empiecen entre las 10:00 y las 16:00';

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

/** The promotion's card in the list, by its title. */
const card = (page: Page, title: string) =>
    page.locator('main').getByRole('listitem', { name: title });

test.describe('Promociones del salón', () => {
    test('la dueña crea un descuento, lo publica, lo pausa, lo duplica y crea horas flojas', async ({
        page,
        context,
    }, testInfo) => {
        test.setTimeout(240_000);
        const isPhone = testInfo.project.name === 'movil';
        const assertNoErrors = watchForErrors(page);
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);

        if (isPhone) {
            await page.setViewportSize(PHONE);
        }

        await login(
            page,
            `promos-duena-${testInfo.project.name}@example.com`,
            'password',
        );
        await expect(page).toHaveURL(/dashboard$/);

        // The menu takes her there.
        if (isPhone) {
            await page.locator('[data-sidebar="trigger"]').first().click();
        }

        await page
            .locator('[data-sidebar="content"]')
            .getByRole('link', { name: 'Promociones', exact: true })
            .click();
        await expect(page).toHaveURL(/promociones$/);

        // Nothing yet: the four cards are the invitation, written with her services.
        await expect(
            page.getByRole('heading', { name: 'Crea tu primera promoción' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /^Servicio de regalo/ }),
        ).toContainText('Lavado de promo gratis con tu Corte de promo');
        await expect(
            page.getByRole('button', { name: /^Campaña de temporada/ }),
        ).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);

        // A discount: one service, and the preview says the rest.
        await page.getByRole('button', { name: /^Descuento/ }).click();
        const sheet = page.getByRole('dialog', { name: 'Descuento' });
        await expect(
            sheet.getByRole('checkbox', { name: /Corte de promo/ }),
        ).not.toBeChecked();
        await expect(
            sheet.getByRole('textbox', { name: 'Porcentaje' }),
        ).toHaveValue('20');
        await sheet.getByRole('checkbox', { name: /Corte de promo/ }).check();

        const preview = sheet.getByRole('region', { name: 'Vista previa' });
        await expect(preview).toContainText(DISCOUNT);
        await expect(preview.locator('s')).toContainText('S/ 35.00');
        await expect(preview).toContainText('S/ 28.00');
        await expect(preview).toContainText(
            'El cliente paga S/ 28 en vez de S/ 35.',
        );
        await expect(preview).toContainText(
            'Quien atiende comisiona sobre S/ 28.',
        );
        await expect(preview).toContainText('Seña: S/ 8.40.');
        // The fine print says whose it is, and that the salon asks a deposit.
        await expect(preview).toContainText(
            `Promoción de Salón Promos ${isPhone ? 'Movil' : 'Escritorio'}.`,
        );
        await expect(preview).toContainText('Aplica a: Corte de promo.');
        await expect(preview).toContainText(
            'El precio con promoción se reserva hasta el plazo para enviar la seña.',
        );
        expect(await hasHorizontalScroll(page)).toBe(false);

        await sheet.getByRole('button', { name: 'Publicar' }).click();
        await expect(page.getByText('Promoción publicada.')).toBeVisible();
        await expect(sheet).toHaveCount(0);
        await expect(card(page, DISCOUNT)).toContainText('Activa');

        // Sharing: the link is copied and says so.
        await card(page, DISCOUNT)
            .getByRole('button', { name: 'Compartir' })
            .click();
        const share = page.getByRole('dialog', { name: 'Compartir promoción' });
        await share.getByRole('button', { name: 'Copiar enlace' }).click();
        await expect(
            share.getByRole('button', { name: 'Enlace copiado' }),
        ).toBeVisible();
        await expect(share.getByRole('status')).toContainText('Copiado');
        expect(
            await page.evaluate(() => navigator.clipboard.readText()),
        ).toContain(`/salon-promos-${testInfo.project.name}/`);
        await expect(
            share.getByRole('link', { name: 'Enviar por WhatsApp' }),
        ).toHaveAttribute(
            'href',
            /^https:\/\/wa\.me\/\?text=20.*V%C3%A1lido%20del.*Condiciones%20y%20reserva/,
        );
        await page.keyboard.press('Escape');
        await expect(share).toHaveCount(0);

        // Pausing asks first, saying what it means; "Volver" leaves it running.
        await card(page, DISCOUNT)
            .getByRole('button', { name: 'Pausar' })
            .click();
        const pausing = page.getByRole('dialog', {
            name: '¿Pausar esta promoción?',
        });
        await expect(pausing).toContainText('Deja de ofrecerse desde ahora.');
        await expect(pausing).toContainText(
            'Las reservas ya hechas conservan su precio',
        );
        await expect(pausing).toContainText(
            'quien tenga el enlace verá que ya no está disponible',
        );
        expect(await hasHorizontalScroll(page)).toBe(false);
        await pausing.getByRole('button', { name: 'Volver' }).click();
        await expect(pausing).toHaveCount(0);
        await expect(card(page, DISCOUNT)).toContainText('Activa');

        // Paused, then copied: the copy is a draft that can be thrown away.
        await card(page, DISCOUNT)
            .getByRole('button', { name: 'Pausar' })
            .click();
        await pausing.getByRole('button', { name: 'Sí, pausar' }).click();
        await expect(page.getByText(/^Promoción en pausa\./)).toBeVisible();
        await expect(pausing).toHaveCount(0);
        await expect(card(page, DISCOUNT)).toContainText('Pausada');

        await card(page, DISCOUNT)
            .getByRole('button', { name: 'Duplicar' })
            .click();
        await expect(card(page, DISCOUNT)).toHaveCount(2);
        const draft = card(page, DISCOUNT).filter({ hasText: 'Borrador' });
        await expect(draft).toHaveCount(1);
        await draft.getByRole('button', { name: 'Eliminar' }).click();
        await page.getByRole('button', { name: 'Sí, eliminar' }).click();
        await expect(page.getByText('Borrador eliminado.')).toBeVisible();
        await expect(card(page, DISCOUNT)).toHaveCount(1);

        // Quiet hours: days and hours come filled in and show on the card.
        await page.getByRole('button', { name: 'Nueva promoción' }).click();
        await page.getByRole('button', { name: /^Horas flojas/ }).click();
        const quiet = page.getByRole('dialog', { name: 'Horas flojas' });
        await expect(
            quiet.getByRole('checkbox', { name: 'Lunes' }),
        ).toBeChecked();
        await expect(
            quiet.getByRole('checkbox', { name: 'Jueves' }),
        ).not.toBeChecked();
        await expect(quiet.getByLabel('Desde las')).toHaveValue('10:00');
        await expect(quiet.getByLabel('Hasta las')).toHaveValue('16:00');
        await quiet.getByRole('checkbox', { name: /Barba de promo/ }).check();
        await expect(
            quiet.getByRole('region', { name: 'Vista previa' }),
        ).toContainText(`Solo ${QUIET_STARTS}.`);
        await quiet.getByRole('button', { name: 'Guardar borrador' }).click();
        await expect(page.getByText('Promoción guardada.')).toBeVisible();

        const quietCard = card(page, '20 % menos en Barba de promo');
        await expect(quietCard).toContainText('Borrador');
        await expect(quietCard).toContainText(QUIET_HOURS);
        expect(await hasHorizontalScroll(page)).toBe(false);

        assertNoErrors();
    });

    test('la dueña publica una «Primera visita» para clientes nuevos', async ({
        page,
    }, testInfo) => {
        const isPhone = testInfo.project.name === 'movil';
        const assertNoErrors = watchForErrors(page);
        const title = '15 % menos en Barba de promo en tu primera visita';

        if (isPhone) {
            await page.setViewportSize(PHONE);
        }

        await login(
            page,
            `promos-duena-${testInfo.project.name}@example.com`,
            'password',
        );
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/promociones');
        await expect(
            page.getByRole('heading', { name: 'Promociones', level: 1 }),
        ).toBeVisible();

        // Run on its own the list is empty and the cards are on the page.
        const startNew = page.getByRole('button', { name: 'Nueva promoción' });

        if (await startNew.isVisible()) {
            await startNew.click();
        }

        await page.getByRole('button', { name: /^Primera visita/ }).click();
        const sheet = page.getByRole('dialog', { name: 'Primera visita' });
        await sheet.getByRole('checkbox', { name: /Barba de promo/ }).check();
        await sheet.getByRole('textbox', { name: 'Porcentaje' }).fill('15');

        // Once per client by definition: no box to tick, a line that says so.
        await sheet.getByRole('button', { name: 'Más opciones' }).click();
        await expect(
            sheet.getByRole('checkbox', { name: 'Una por cliente' }),
        ).toHaveCount(0);
        await expect(sheet).toContainText('Ya es una por cliente');

        const preview = sheet.getByRole('region', { name: 'Vista previa' });
        await expect(preview).toContainText(title);
        await expect(preview).toContainText(
            `Solo para la primera visita: vale si nunca tuviste una cita en Salón Promos ${isPhone ? 'Movil' : 'Escritorio'}, ni tienes otra reservada.`,
        );
        await expect(preview).not.toContainText('Una por cliente.');
        expect(await hasHorizontalScroll(page)).toBe(false);

        await sheet.getByRole('button', { name: 'Publicar' }).click();
        await expect(page.getByText('Promoción publicada.')).toBeVisible();
        await expect(card(page, title)).toContainText('Activa');

        assertNoErrors();
    });
});
