import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

// Close-of-phase-6 gaps: a season campaign (named with tildes, ñ and an emoji)
// from creation to the public strip, in light and dark, and the ended link.
// Uses the "Salón Promos …" salon of E2eSeeder; it pauses what it publishes.
const OCCASION = 'Aniversario ñandú 🎉';

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

for (const colorScheme of ['light', 'dark'] as const) {
    test(`campaña de temporada: se crea, se ve en la página del salón y su enlace vencido no da 404 (${colorScheme})`, async ({
        page,
    }, testInfo) => {
        test.setTimeout(180_000);
        const assertNoErrors = watchForErrors(page);
        await page.emulateMedia({ colorScheme });
        const project = testInfo.project.name;

        await login(page, `promos-duena-${project}@example.com`, 'password');
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/promociones');

        const empty = page.getByRole('heading', {
            name: 'Crea tu primera promoción',
        });

        if (await empty.isVisible()) {
            await page
                .getByRole('button', { name: /^Campaña de temporada/ })
                .click();
        } else {
            await page.getByRole('button', { name: 'Nueva promoción' }).click();
            await page
                .getByRole('button', { name: /^Campaña de temporada/ })
                .click();
        }

        const sheet = page.getByRole('dialog', {
            name: 'Campaña de temporada',
        });
        await sheet.getByText('Otra', { exact: true }).click();
        await sheet.getByRole('checkbox', { name: /Corte de promo/ }).check();

        await sheet.getByLabel('Nombre de la campaña').fill(OCCASION);
        // «Otra» proposes its own dates; start today so it is live now.
        await sheet.getByLabel('Desde').fill(
            new Date().toLocaleDateString('en-CA', {
                timeZone: 'America/Lima',
            }),
        );
        await expect(
            sheet.getByRole('region', { name: 'Vista previa' }),
        ).toContainText('Aniversario ñandú');
        expect(await hasHorizontalScroll(page)).toBe(false);
        await sheet.getByRole('button', { name: 'Publicar' }).click();
        await expect(page.getByText('Promoción publicada.')).toBeVisible();

        const mine = page
            .locator('main')
            .getByRole('listitem')
            .filter({ hasText: 'Aniversario ñandú' })
            .first();
        await expect(mine).toContainText('Activa');

        // Visitors (a fresh context, no session) see it in the strip.
        const visitor = await page
            .context()
            .browser()!
            .newContext({
                colorScheme,
                viewport: page.viewportSize() ?? undefined,
                locale: 'es-PE',
            });
        const guest = await visitor.newPage();
        const assertGuestNoErrors = watchForErrors(guest);
        const salon = `/s/salon-promos-${project}`;
        await guest.goto(new URL(salon, page.url()).toString());
        const strip = guest.getByRole('region', { name: 'Promociones' });
        await expect(strip).toContainText('Aniversario ñandú');
        expect(await hasHorizontalScroll(guest)).toBe(false);

        // Pause it: the strip drops it and a stale link lands on the page.
        await mine.getByRole('button', { name: 'Pausar' }).click();
        await page.getByRole('button', { name: 'Sí, pausar' }).click();
        await expect(mine).toContainText('Pausada');

        await guest.reload();
        await expect(guest.getByText('Aniversario ñandú')).toHaveCount(0);
        const response = await guest.goto(
            new URL(`${salon}/promo/vencida123`, page.url()).toString(),
        );
        expect(response?.status()).toBe(200);
        await expect(guest.getByRole('main')).toBeVisible();
        expect(await hasHorizontalScroll(guest)).toBe(false);

        assertGuestNoErrors();
        await visitor.close();
        assertNoErrors();
    });
}
