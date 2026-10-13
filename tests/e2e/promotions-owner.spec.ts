import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

// "Salón Promos …" (E2eSeeder): one salon per project, with a 30 % deposit,
// "Corte de promo" (S/ 35), "Barba de promo" (S/ 20) and the add-on
// "Lavado de promo" (S/ 10).
const PHONE = { width: 390, height: 844 };
const DISCOUNT = '20 % menos en Corte de promo';
const QUIET_HOURS = 'lunes, martes y miércoles, de 10:00 a 16:00';

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
        await expect(preview).toContainText('Aplica a: Corte de promo.');
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
        ).toHaveAttribute('href', /^https:\/\/wa\.me\/\?text=20/);
        await page.keyboard.press('Escape');
        await expect(share).toHaveCount(0);

        // Paused, then copied: the copy is a draft that can be thrown away.
        await card(page, DISCOUNT)
            .getByRole('button', { name: 'Pausar' })
            .click();
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
        ).toContainText(`Solo ${QUIET_HOURS}.`);
        await quiet.getByRole('button', { name: 'Guardar borrador' }).click();
        await expect(page.getByText('Promoción guardada.')).toBeVisible();

        const quietCard = card(page, '20 % menos en Barba de promo');
        await expect(quietCard).toContainText('Borrador');
        await expect(quietCard).toContainText(QUIET_HOURS);
        expect(await hasHorizontalScroll(page)).toBe(false);

        assertNoErrors();
    });
});
