import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

const showcase = { email: 'vitrina@example.com', password: 'password' };

test.describe('Diseño de tu página (Mi salón)', () => {
    test('la dueña elige otra plantilla, la guarda y vuelve a la de antes', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, showcase.email, showcase.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-salon');

        const section = page.locator('#diseno');
        await expect(
            section.getByRole('heading', { name: 'Diseño de tu página' }),
        ).toBeVisible();
        await expect(section.getByRole('radio')).toHaveCount(4);
        const original = await section
            .locator('input[name="theme"]:checked')
            .getAttribute('value');
        const other = original === 'vibrante' ? 'luminosa' : 'vibrante';
        const labelOf = (value: string | null) =>
            `${value?.charAt(0).toUpperCase()}${value?.slice(1)}`;
        const otherLabel = labelOf(other);

        await expect(
            section.getByRole('link', {
                name: `Ver cómo quedaría con ${otherLabel}`,
            }),
        ).toHaveAttribute('href', new RegExp(`diseno=${other}`));

        // The radio is visually hidden: people tap the card, so does the test.
        await section.getByText(otherLabel, { exact: true }).click();
        await expect(section.getByRole('status')).toContainText(
            'Aún no lo guardas',
        );
        await section.getByRole('button', { name: 'Guardar diseño' }).click();
        await expect(page.getByText('Diseño guardado.')).toBeVisible();

        await page.reload();
        await expect(
            section.getByRole('radio', { name: new RegExp(`^${otherLabel}`) }),
        ).toBeChecked();

        await section.getByText(labelOf(original), { exact: true }).click();
        await section.getByRole('button', { name: 'Guardar diseño' }).click();
        await expect(page.getByText('Diseño guardado.')).toBeVisible();
        assertNoErrors();
    });

    test('en escritorio, "Ver cómo quedaría" abre la página real en un celular', async ({
        page,
    }, testInfo) => {
        test.skip(testInfo.project.name !== 'escritorio', 'solo escritorio');
        const assertNoErrors = watchForErrors(page);

        await login(page, showcase.email, showcase.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-salon');

        await page
            .getByRole('button', { name: 'Ver cómo quedaría con Luminosa' })
            .click();
        const dialog = page.getByRole('dialog', { name: 'Luminosa' });
        await expect(dialog).toBeVisible();
        // Focus starts on the choice, not inside the framed page (Escape must keep closing).
        await expect(
            dialog.getByRole('button', { name: 'Elegir este diseño' }),
        ).toBeFocused();
        await expect(
            dialog.getByRole('link', { name: 'Abrir la página completa' }),
        ).toHaveAttribute('href', /diseno=luminosa/);

        // The preview is the real public page, framed by this same site.
        const preview = dialog.frameLocator(
            'iframe[title="Vista previa de la plantilla Luminosa"]',
        );
        await expect(
            preview.locator('[data-theme="luminosa"]').first(),
        ).toBeAttached();
        await expect(preview.getByRole('heading', { level: 1 })).toContainText(
            'Estudio Ñusta',
        );
        await expect(dialog.getByText('Cargando vista previa…')).toBeHidden();

        await dialog
            .getByRole('button', { name: 'Elegir este diseño' })
            .click();

        await expect(dialog).toBeHidden();
        await expect(
            page.getByRole('radio', { name: /^Luminosa/ }),
        ).toBeChecked();
        assertNoErrors();
    });
});
