import { expect, test } from '@playwright/test';
import type { Browser, BrowserContext, Page, TestInfo } from '@playwright/test';
import { login, watchForErrors } from './support';

// "Salón Reseñas …" (E2eSeeder): one salon per project, with its owner and a
// person who has an account and a visit charged yesterday.
const COMMENT = 'Muy buen corte y puntuales. Volveré.';
const REPLY = 'Gracias por venir. Te esperamos pronto.';
const PHONE = { width: 390, height: 844 };

function cast(project: string) {
    const label = project === 'movil' ? 'Movil' : 'Escritorio';

    return {
        salon: `Salón Reseñas ${label}`,
        slug: `salon-resenas-${project}`,
        owner: `resenas-duena-${project}@example.com`,
        client: `resenas-${project}@example.com`,
        // Reviews are signed with the first name only.
        author: project === 'movil' ? 'Milan' : 'Eli',
    };
}

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

/** Someone else's session (the owner, a visitor), beside the client's. */
async function otherSession(
    browser: Browser,
    testInfo: TestInfo,
): Promise<BrowserContext> {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { defaultBrowserType, ...use } = testInfo.project.use as Record<
        string,
        unknown
    >;

    return browser.newContext(
        testInfo.project.name === 'movil' ? { ...use, viewport: PHONE } : use,
    );
}

test.describe('Reseñas', () => {
    test('el salón las hace públicas, el cliente opina, el salón responde y el cliente la retira', async ({
        page,
        browser,
    }, testInfo) => {
        test.setTimeout(240_000);
        const isPhone = testInfo.project.name === 'movil';
        const people = cast(testInfo.project.name);
        const assertNoErrors = watchForErrors(page);

        if (isPhone) {
            await page.setViewportSize(PHONE);
        }

        // Private by default: a visitor finds no reviews on the salon's page.
        const visitorContext = await otherSession(browser, testInfo);
        const visitor = await visitorContext.newPage();
        const assertNoVisitorErrors = watchForErrors(visitor);
        const salonTitle = visitor.getByRole('heading', {
            name: people.salon,
            level: 1,
        });
        const publicHeading = visitor.getByRole('heading', {
            name: 'Reseñas',
            exact: true,
        });
        await visitor.goto(`/s/${people.slug}`);
        await expect(salonTitle).toBeVisible();
        await expect(publicHeading).toHaveCount(0);

        // The owner makes them public first: a review written while they
        // are private never goes out.
        const ownerContext = await otherSession(browser, testInfo);
        const owner = await ownerContext.newPage();
        const assertNoOwnerErrors = watchForErrors(owner);
        await login(owner, people.owner, 'password');
        await expect(owner).toHaveURL(/dashboard$/, { timeout: 30_000 });
        if (isPhone) {
            await owner.locator('[data-sidebar="trigger"]').first().click();
        }
        await owner.getByRole('link', { name: 'Reseñas', exact: true }).click();
        await expect(owner).toHaveURL(/\/resenas$/);
        // On a phone the menu stays over the page after navigating.
        await owner.keyboard.press('Escape');
        const privateMode = owner.getByRole('radio', { name: 'Privadas' });
        const publicMode = owner.getByRole('radio', { name: 'Públicas' });
        await expect(privateMode).toBeChecked();
        await expect(owner.getByRole('main')).toContainText(
            'Todavía no hay reseñas. Se piden por correo después de cada cita atendida.',
        );
        expect(await hasHorizontalScroll(owner)).toBe(false);

        // Going public asks first; backing out changes nothing.
        await publicMode.click();
        const confirm = owner.getByRole('dialog', {
            name: '¿Hacer públicas tus reseñas?',
        });
        await expect(confirm).toContainText(
            'En modo público se muestran todas las reseñas que recibas desde ahora, también las negativas. No se pueden ocultar una por una. Las que ya recibiste en privado siguen siendo privadas. Puedes volver a Privadas cuando quieras.',
        );
        await confirm.getByRole('button', { name: 'Volver' }).click();
        await expect(confirm).toHaveCount(0);
        await expect(privateMode).toBeChecked();
        await publicMode.click();
        await confirm.getByRole('button', { name: 'Hacer públicas' }).click();
        await expect(publicMode).toBeChecked();

        await visitor.reload();
        const section = visitor.getByRole('region', { name: 'Reseñas' });
        await expect(section).toContainText(
            'Todavía no hay reseñas. Aparecen cuando alguien opina de una cita atendida.',
        );

        // The client: from the list to the visit, and the review.
        await login(page, people.client, 'password');
        await expect(page).not.toHaveURL(/login/, { timeout: 30_000 });
        await page.goto('/mis-reservas');
        await page.getByRole('link', { name: /Deja tu reseña/ }).click();
        const question = page.getByRole('heading', { name: '¿Cómo te fue?' });
        await expect(question).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);

        const form = page.getByRole('region', { name: '¿Cómo te fue?' });
        const send = form.getByRole('button', { name: 'Publicar reseña' });
        await expect(form).toContainText(
            `Tu reseña se publicará con tu nombre de pila en la página de ${people.salon}, y el salón puede responderla ahí. No incluyas datos personales de otras personas. Puedes retirarla cuando quieras desde esta reserva.`,
        );
        // Nothing goes out without stars: it says so and goes back to them.
        const stars = form.getByRole('radiogroup', { name: 'Calificación' });
        let sent = 0;
        page.on('request', (request) => {
            if (request.method() === 'POST' && /resena/.test(request.url())) {
                sent += 1;
            }
        });
        await expect(send).toBeEnabled();
        await send.click();
        await expect(form.getByRole('alert')).toHaveText(
            'Elige de 1 a 5 estrellas.',
        );
        await expect(
            stars.getByRole('radio', { name: '1 estrella' }),
        ).toBeFocused();
        expect(sent).toBe(0);
        await expect(stars.getByRole('radio')).toHaveCount(5);
        if (isPhone) {
            const box = await stars
                .getByRole('radio', { name: '1 estrella' })
                .boundingBox();
            expect(box!.width).toBeGreaterThanOrEqual(44);
            expect(box!.height).toBeGreaterThanOrEqual(44);
        }
        await stars.getByRole('radio', { name: '4 estrellas' }).check();
        await expect(form.getByRole('alert')).toHaveCount(0);
        await form.getByLabel(/Comentario/).fill(COMMENT);
        await expect(form).toContainText(`${COMMENT.length} / 600`);
        await send.click();

        const mine = page.getByRole('region', { name: 'Tu reseña' });
        await expect(mine).toContainText(COMMENT);
        // The rating is read once: as text, not also as an image.
        await expect(mine).toContainText('4 estrellas');
        await expect(mine.getByRole('img')).toHaveCount(0);
        await expect(mine).toContainText(
            `Publicada en la página de ${people.salon} con tu nombre de pila.`,
        );
        expect(sent).toBe(1);
        await expect(page.getByText('Gracias por tu reseña.')).toBeVisible();
        await expect(question).toHaveCount(0);

        // The owner reads it and answers.
        await owner.reload();
        const card = owner.getByRole('article', {
            name: `Reseña de ${people.author}`,
        });
        await expect(card).toContainText(COMMENT);
        await expect(card).toContainText('Atendió Rene');
        await expect(card).not.toContainText('Privada');
        await expect(
            card.getByRole('img', { name: '4 de 5 estrellas' }),
        ).toBeVisible();
        await expect(
            owner.getByRole('region', { name: 'Resumen' }),
        ).toContainText('1 reseña de una cita atendida');
        await card.getByRole('button', { name: 'Responder' }).click();
        const replyDialog = owner.getByRole('dialog', { name: 'Responder' });
        await replyDialog.getByLabel('Tu respuesta').fill(REPLY);
        await replyDialog
            .getByRole('button', { name: 'Guardar respuesta' })
            .click();
        await expect(owner.getByRole('dialog')).toHaveCount(0);
        await expect(card).toContainText(REPLY);
        await expect(
            card.getByRole('button', { name: 'Editar respuesta' }),
        ).toBeVisible();
        expect(await hasHorizontalScroll(owner)).toBe(false);

        // The visitor reads the review, the average and the answer.
        await visitor.reload();
        await expect(section).toContainText('4.0');
        await expect(section).toContainText('1 reseña de una cita atendida');
        const published = section.getByRole('article', {
            name: `Reseña de ${people.author}`,
        });
        await expect(published).toContainText(COMMENT);
        await expect(published).toContainText(`Respuesta de ${people.salon}`);
        await expect(published).toContainText(REPLY);
        expect(await hasHorizontalScroll(visitor)).toBe(false);

        // And the client sees the salon's answer on their booking.
        await page.reload();
        await expect(mine).toContainText(`Respuesta de ${people.salon}`);
        await expect(mine).toContainText(REPLY);
        expect(await hasHorizontalScroll(page)).toBe(false);

        // Back to private: gone from the public page at once.
        await privateMode.click();
        await expect(privateMode).toBeChecked();
        await visitor.reload();
        await expect(salonTitle).toBeVisible();
        await expect(publicHeading).toHaveCount(0);
        await expect(visitor.getByText(COMMENT)).toHaveCount(0);

        // Taking it back is for good: no form comes back.
        await mine.getByRole('button', { name: 'Retirar mi reseña' }).click();
        const withdraw = page.getByRole('dialog', {
            name: '¿Retirar tu reseña?',
        });
        await expect(withdraw).toContainText(
            'Deja de mostrarse y se borra tu comentario. No podrás volver a opinar de esta cita.',
        );
        await withdraw.getByRole('button', { name: 'Retirar' }).click();
        await expect(
            page.getByText('Retiraste tu reseña de esta cita.'),
        ).toBeVisible();
        await expect(mine).toHaveCount(0);
        await expect(question).toHaveCount(0);
        await owner.reload();
        await expect(card).toHaveCount(0);

        assertNoErrors();
        assertNoOwnerErrors();
        assertNoVisitorErrors();
        await ownerContext.close();
        await visitorContext.close();
    });
});
