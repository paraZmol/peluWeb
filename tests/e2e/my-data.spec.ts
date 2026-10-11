import { expect, test } from '@playwright/test';
import type { Browser, BrowserContext, Page, TestInfo } from '@playwright/test';
import { login, watchForErrors } from './support';

// "Salón Fichas" (E2eSeeder): one person per project with an account of
// their own and a file in the salon, and the owner who attends them.
const fileOwner = { email: 'ficha-duena@example.com', password: 'password' };
const PEOPLE: Record<string, { name: string; email: string }> = {
    movil: { name: 'Dato Movil', email: 'datos-movil@example.com' },
    escritorio: {
        name: 'Dato Escritorio',
        email: 'datos-escritorio@example.com',
    },
};
const SALON = 'Salón Fichas';
const ALLERGY = 'Reacción al tinte con PPD';
const WITHDRAWN =
    /retiró el \d+ \w+ \d{4} su autorización para guardar alergias\. Lo anotado se borró\. No lo anotes en otro campo\. Pregúntale por alergias antes de cada servicio\.$/;

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

/** The owner works in a session of their own, beside the person's. */
async function ownerSession(
    browser: Browser,
    testInfo: TestInfo,
): Promise<BrowserContext> {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { defaultBrowserType, ...use } = testInfo.project.use as Record<
        string,
        unknown
    >;

    return browser.newContext(use);
}

test.describe('Mis datos', () => {
    test('la persona autoriza sus alergias, ve lo que el salón anota y lo retira', async ({
        page,
        browser,
    }, testInfo) => {
        test.setTimeout(120_000);
        const assertNoErrors = watchForErrors(page);
        const person = PEOPLE[testInfo.project.name];

        await login(page, person.email, 'password');
        await expect(page).not.toHaveURL(/login/, { timeout: 30_000 });
        if (page.viewportSize()!.width < 768) {
            await page.locator('[data-sidebar="trigger"]').first().click();
        }
        await page
            .getByRole('link', { name: 'Mis datos', exact: true })
            .click();
        await expect(page).toHaveURL(/\/mis-datos$/);
        // On a phone the menu stays over the page after navigating.
        await page.keyboard.press('Escape');

        const card = page.getByRole('article', { name: SALON });
        await expect(
            card.getByRole('link', { name: `Ver la página de ${SALON}` }),
        ).toHaveAttribute('href', /\/salon-fichas$/);
        await expect(card).toContainText(
            `${SALON} solo puede anotar tus alergias si lo autorizas. Es voluntario: si no, te atienden igual.`,
        );
        await expect(card).toContainText(`${SALON} no guarda fotos tuyas.`);
        await expect(card).toContainText(
            `¿Quieres ver o corregir el resto de lo que ${SALON} guarda de ti? Pídelo en el salón.`,
        );
        expect(await hasHorizontalScroll(page)).toBe(false);

        // A salon that closed its account: nothing to authorize, no page to open.
        const closed = page.getByRole('article', { name: 'Salón Cerrado' });
        await expect(closed).toContainText('Este salón cerró su cuenta');
        await expect(closed).toContainText(
            'Este salón ya no usa peluWeb. Puedes retirar tus autorizaciones para que se borre lo que guardó.',
        );
        await expect(closed.getByRole('link')).toHaveCount(0);
        await expect(closed.getByRole('button')).toHaveCount(0);

        // The whole text, a box that starts unticked, and nothing sent without it.
        await card.getByRole('button', { name: 'Autorizar' }).click();
        const dialog = page.getByRole('dialog', {
            name: `Autorizar a ${SALON} a guardar mis alergias`,
        });
        await expect(dialog).toContainText(
            `Autorizo a ${SALON} a guardar mis alergias, sensibilidades y reacciones a productos (datos de salud)`,
        );
        await expect(dialog).toContainText('al retirarla, se borra.');
        const accept = dialog.getByRole('checkbox', {
            name: `Soy mayor de 18 años, leí lo anterior y autorizo a ${SALON} a guardar estos datos de salud.`,
        });
        await expect(accept).not.toBeChecked();
        let sent = 0;
        page.on('request', (request) => {
            if (request.url().includes('/autorizar/')) {
                sent += 1;
            }
        });
        await dialog.getByRole('button', { name: 'Autorizar' }).click();
        await expect(dialog.getByRole('alert')).toHaveText(
            'Marca la casilla para autorizar.',
        );
        expect(sent).toBe(0);
        expect(await hasHorizontalScroll(page)).toBe(false);
        await dialog.getByRole('button', { name: 'Ahora no' }).click();
        await expect(dialog).toHaveCount(0);
        await expect(card.getByText(/^Autorizaste el/)).toHaveCount(0);

        // Opened again it starts unticked; ticked, it goes through.
        await card.getByRole('button', { name: 'Autorizar' }).click();
        await expect(accept).not.toBeChecked();
        await accept.check();
        await dialog.getByRole('button', { name: 'Autorizar' }).click();
        await expect(
            page.getByText(
                'Autorización registrada. Te enviamos una constancia a tu correo.',
            ),
        ).toBeVisible();
        await expect(dialog).toHaveCount(0);
        expect(sent).toBe(1);
        await expect(
            card.getByText(/^Autorizaste el \d+ \w+ \d{4}$/),
        ).toBeVisible();
        await card.getByText('Ver lo anotado').click();
        await expect(
            card.getByText('El salón todavía no anotó nada.'),
        ).toBeVisible();

        // The salon finds the field open, with nothing to tick or to print.
        const ownerContext = await ownerSession(browser, testInfo);
        const ownerPage = await ownerContext.newPage();
        await login(ownerPage, fileOwner.email, fileOwner.password);
        await expect(ownerPage).toHaveURL(/dashboard$/, { timeout: 30_000 });
        await ownerPage.goto('/clientes');
        await ownerPage
            .getByRole('link', { name: `Ver ficha de ${person.name}` })
            .click();
        await expect(
            ownerPage.getByText(
                new RegExp(
                    `^${person.name} lo autorizó desde su cuenta el \\d+ \\w+ \\d{4}\\.$`,
                ),
            ),
        ).toBeVisible();
        await expect(
            ownerPage.getByText(
                `Solo ${person.name} puede retirarla, desde su cuenta (Mis datos).`,
            ),
        ).toBeVisible();
        await expect(ownerPage.getByRole('checkbox')).toHaveCount(0);
        await expect(
            ownerPage.getByRole('button', { name: /la hoja$/ }),
        ).toHaveCount(0);
        const care = ownerPage.getByRole('textbox', {
            name: 'Alergias y cuidado',
        });
        await expect(care).toBeEnabled();
        await care.fill(ALLERGY);
        await ownerPage.getByRole('button', { name: 'Guardar notas' }).click();
        await expect(ownerPage.getByText('Ficha actualizada.')).toBeVisible();

        // The person reads what was written, folded until they ask.
        await page.reload();
        await expect(card.getByText(ALLERGY)).toBeHidden();
        await card.getByText('Ver lo anotado').click();
        await expect(card.getByText(ALLERGY)).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);

        // Taking it back is asked first, then erases.
        await card
            .getByRole('button', { name: 'Retirar autorización' })
            .click();
        const confirmation = page.getByRole('dialog', {
            name: '¿Retirar la autorización?',
        });
        await expect(confirmation).toContainText(
            `${SALON} dejará de guardar tus alergias y se borrará lo anotado. Avísales en persona antes de tu próximo servicio.`,
        );
        await confirmation.getByRole('button', { name: 'Volver' }).click();
        await expect(confirmation).toHaveCount(0);
        await expect(card.getByText(/^Autorizaste el/)).toBeVisible();
        await card
            .getByRole('button', { name: 'Retirar autorización' })
            .click();
        await confirmation
            .getByRole('button', { name: 'Retirar y borrar' })
            .click();
        await expect(
            page.getByText('Autorización retirada. Se borró lo anotado.'),
        ).toBeVisible();
        await expect(confirmation).toHaveCount(0);
        await expect(
            card.getByText(/^Retiraste esta autorización el \d+ \w+ \d{4}\.$/),
        ).toBeVisible();
        await expect(
            card.getByRole('button', { name: 'Autorizar' }),
        ).toBeVisible();
        await expect(page.getByText(ALLERGY)).toHaveCount(0);
        expect(await hasHorizontalScroll(page)).toBe(false);

        // The salon's file says so, above and on the field, and holds nothing.
        await ownerPage.reload();
        const brief = ownerPage.getByRole('region', {
            name: 'Antes de atender',
        });
        await expect(brief).toContainText(WITHDRAWN);
        await expect(brief).toContainText(person.name);
        await expect(ownerPage.getByText(WITHDRAWN)).toHaveCount(2);
        // Only the person can allow it again: no sheet, no box.
        await expect(
            ownerPage.getByText(
                `Solo ${person.name} puede volver a autorizarlo, desde su cuenta (Mis datos).`,
            ),
        ).toBeVisible();
        await expect(ownerPage.getByRole('checkbox')).toHaveCount(0);
        await expect(
            ownerPage.getByRole('button', { name: 'Imprimir la hoja' }),
        ).toHaveCount(0);
        await expect(care).toBeDisabled();
        await expect(care).toHaveValue('');
        expect(await ownerPage.content()).not.toContain('PPD');
        expect(await hasHorizontalScroll(ownerPage)).toBe(false);
        await ownerContext.close();
        assertNoErrors();
    });

    test('sin ficha en ningún salón: lo dice y lleva a Mis reservas', async ({
        page,
    }) => {
        // Rosa owns no salon yet and never booked anywhere.
        await login(page, 'duena@example.com', 'password');
        await expect(page).not.toHaveURL(/login/, { timeout: 30_000 });
        await page.goto('/mis-datos');

        await expect(
            page.getByText(
                'Todavía no tienes ficha en ningún salón. Aparecerá aquí cuando reserves con tu cuenta.',
            ),
        ).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);
        await page.getByRole('link', { name: 'Ir a Mis reservas' }).click();
        await expect(page).toHaveURL(/\/mis-reservas$/);
        await expect(
            page.getByRole('link', { name: 'Ver mis datos en cada salón' }),
        ).toBeVisible();
    });
});
