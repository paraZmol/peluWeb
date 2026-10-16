import { expect, test, type Page } from '@playwright/test';
import { login, watchForErrors } from './support';

/*
| "Mi plan" (PLAN 7.2 and 7.3): the owner finds it in the menu and reads
| the trial, the plans and how to pay; the rest of the team neither sees
| it nor opens it; a paused salon says so over every screen.
*/

/** On a phone the menu lives in a drawer. */
async function openMenu(page: Page) {
    if ((page.viewportSize()?.width ?? 0) < 768) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }

    await expect(
        page.locator('[data-sidebar="menu-button"]').first(),
    ).toBeVisible();
}

test('quien es titular abre «Mi plan» desde el menú y ve su prueba y los planes', async ({
    page,
}) => {
    const assertNoErrors = watchForErrors(page);
    await login(page, 'caja-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);

    await openMenu(page);
    await page
        .locator('[data-sidebar="content"]')
        .getByRole('link', { name: 'Mi plan', exact: true })
        .click();

    await expect(page).toHaveURL(/mi-plan$/);
    await expect(page.getByRole('heading', { name: 'Mi plan' })).toBeVisible();
    await expect(
        page.getByRole('heading', {
            name: /^Tu prueba gratis termina el .+ \(faltan 30 días\)\.$/,
        }),
    ).toBeVisible();
    await expect(page.getByText('En prueba', { exact: true })).toBeVisible();

    const plans = page.getByRole('region', { name: 'Planes' });
    await expect(plans.getByRole('heading', { name: 'Solo' })).toBeVisible();
    await expect(plans.getByRole('heading', { name: 'Equipo' })).toBeVisible();
    await expect(plans.getByText('Tu plan', { exact: true })).toBeVisible();
    await expect(
        plans.getByText('Hasta 6 profesionales con agenda'),
    ).toBeVisible();
    await expect(plans.getByText(/recepción y caja van gratis/)).toBeVisible();
    // A year is offered only from the second payment on.
    await expect(plans.getByText(/al año/)).toHaveCount(0);

    await expect(
        page.getByRole('heading', { name: 'Cómo pagar' }),
    ).toBeVisible();
    await expect(page.getByText(/Aún no hay pagos/)).toBeVisible();
    // The page itself explains; the banner does not repeat it there.
    await expect(page.locator('[data-test="subscription-notice"]')).toHaveCount(
        0,
    );

    assertNoErrors();
});

test('en Profesionales se ve cuántos tienen agenda y el plan', async ({
    page,
}) => {
    await login(page, 'caja-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);

    await page.goto('/profesionales');

    await expect(page.locator('[data-test="agenda-limit"]')).toHaveText(
        /^\d+ de 6 con agenda · Plan Equipo$/,
    );
});

test('el personal no ve «Mi plan» ni puede abrirlo', async ({ page }) => {
    await login(page, 'sin-caja@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);

    await openMenu(page);
    await expect(
        page
            .locator('[data-sidebar="content"]')
            .getByRole('link', { name: 'Mi plan', exact: true }),
    ).toHaveCount(0);

    const response = await page.request.get('/mi-plan', { maxRedirects: 0 });
    expect(response.status()).toBe(403);
    // A trial with a month to go says nothing to the team.
    await expect(page.locator('[data-test="subscription-notice"]')).toHaveCount(
        0,
    );
});

/*
| Needs a paused salon in E2eSeeder (not there yet): "Salón En Pausa"
| (slug salon-en-pausa) with trial_ends_at 20 days ago and no payments,
| owned by pausa-duena@example.com, with pausa-personal@example.com as staff.
*/
test.fixme('un salón en pausa lo dice en cada pantalla, a quien es titular y al personal', async ({
    page,
}) => {
    await login(page, 'pausa-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);

    const ownerNotice = page.getByRole('alert').filter({
        hasText: 'Tu salón está en pausa',
    });
    await expect(ownerNotice).toBeVisible();
    await expect(
        ownerNotice.getByRole('link', { name: 'Ver mi plan' }),
    ).toBeVisible();
    await expect(
        ownerNotice.getByRole('button', { name: 'Ocultar aviso' }),
    ).toHaveCount(0);

    await page.goto('/mi-plan');
    await expect(page.getByText(/No se borró nada/)).toBeVisible();

    await page.context().clearCookies();
    await login(page, 'pausa-personal@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);
    await expect(
        page.getByRole('alert').filter({
            hasText:
                'El salón está en pausa: puedes ver todo, pero no hacer cambios. Habla con quien administra el salón.',
        }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ver mi plan' })).toHaveCount(
        0,
    );
});
