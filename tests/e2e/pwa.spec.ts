import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login } from './support';

/*
| Step 6.4: the installable app. The worker keeps a copy of "Mis reservas"
| for when there is no connection and forgets it on sign-out; the install
| card uses the browser's own prompt, or written steps on an iPhone.
*/

const client = { email: 'cliente@example.com', password: 'password' };
const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

/** The page is served through the worker from here on. */
async function waitForWorker(page: Page) {
    await page.waitForFunction(
        () => navigator.serviceWorker.controller !== null,
    );
}

/** What Chrome fires when the site can be installed. */
async function offerInstall(page: Page) {
    await page.evaluate(() => {
        const event = Object.assign(new Event('beforeinstallprompt'), {
            prompt: async () => {
                (window as unknown as { prompted: boolean }).prompted = true;
            },
            userChoice: Promise.resolve({ outcome: 'accepted' }),
        });
        window.dispatchEvent(event);
    });
}

test('sin conexión: Mis reservas se abre de la copia guardada, lo demás avisa, y al salir se olvida', async ({
    page,
    context,
}) => {
    await login(page, client.email, client.password);
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/mis-reservas');
    await waitForWorker(page);
    // Loaded through the worker: this is the copy it keeps.
    await page.reload();
    await expect(
        page.getByRole('heading', { name: 'Mis reservas', level: 1 }),
    ).toBeVisible();
    await page.waitForFunction(async () =>
        Boolean(await (await caches.open('pages-v1')).match('/mis-reservas')),
    );

    await context.setOffline(true);
    await page.reload();
    await expect(
        page.getByRole('heading', { name: 'Mis reservas', level: 1 }),
    ).toBeVisible();

    await page.goto('/mis-datos');
    await expect(
        page.getByRole('heading', { name: 'No hay conexión' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Ver mis reservas' }),
    ).toBeVisible();

    await context.setOffline(false);
    await page.goto('/mis-reservas');
    await page.locator('[data-sidebar="trigger"]').first().click();
    await page.locator('[data-sidebar="footer"] button').first().click();
    await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click();
    await expect(page).not.toHaveURL(/mis-reservas/);

    // Nothing of the person stays on a shared phone.
    await expect
        .poll(() => page.evaluate(() => caches.has('pages-v1')))
        .toBe(false);
});

test('el aviso de instalar usa el diálogo del navegador y «Ahora no» lo guarda', async ({
    page,
}) => {
    await login(page, client.email, client.password);
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/mis-reservas');
    const card = page.getByRole('region', { name: /^Instalar / });
    await expect(card).toHaveCount(0);

    await offerInstall(page);
    await card.getByRole('button', { name: 'Instalar' }).click();
    expect(
        await page.evaluate(
            () => (window as unknown as { prompted?: boolean }).prompted,
        ),
    ).toBe(true);
    await expect(card).toHaveCount(0);

    await offerInstall(page);
    await card.getByRole('button', { name: 'Ahora no' }).click();
    await expect(card).toHaveCount(0);
    await page.reload();
    await offerInstall(page);
    await expect(card).toHaveCount(0);
});

test('en iPhone, la página del salón explica cómo añadirla a la pantalla de inicio', async ({
    browser,
}) => {
    const context = await browser.newContext({
        userAgent: IPHONE,
        viewport: { width: 390, height: 844 },
        hasTouch: true,
    });
    const page = await context.newPage();

    await page.goto('/s/salon-uno');
    const card = page.getByRole('region', { name: 'Instalar Salón Uno' });
    await expect(card.getByText('Añadir a pantalla de inicio')).toBeVisible();
    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute(
        'href',
        '/s/salon-uno/manifest.webmanifest',
    );

    await card.getByRole('button', { name: 'Entendido' }).click();
    await expect(card).toHaveCount(0);
    await context.close();
});

// A headless browser always reports notifications as blocked, even when
// granted: the "allowed" case (switching them on, receiving one) is a manual
// check on a real phone, noted in the plan for step 8.6.
test('avisos en este dispositivo: donde el navegador los bloquea, se explica y no se ofrece activarlos', async ({
    page,
}) => {
    const push = page.getByRole('region', {
        name: 'Avisos en este dispositivo',
    });
    await login(page, client.email, client.password);
    await expect(page).toHaveURL(/dashboard$/);

    await page.goto('/settings/profile');
    await waitForWorker(page);
    await page.reload();
    await expect(push.getByText(/bloqueadas para este sitio/)).toBeVisible();
    await expect(push.getByText(/Toca el candado/)).toBeVisible();
    // Only a way to look again once unblocked; nothing to turn on.
    await expect(push.getByRole('button')).toHaveCount(1);
    await push.getByRole('button', { name: 'Ya lo cambié' }).click();
    await expect(
        push.getByText('Siguen bloqueadas en este navegador.'),
    ).toBeVisible();

    // Nothing to turn on there: the list of bookings does not offer it.
    await page.goto('/mis-reservas');
    await expect(
        page.getByRole('heading', { name: 'Mis reservas', level: 1 }),
    ).toBeVisible();
    await expect(push).toHaveCount(0);
});
