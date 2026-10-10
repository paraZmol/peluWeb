import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };
// A member of "Salón Dos Ñandú" without any permission.
const member = { email: 'personal@example.com', password: 'password' };

// A 1x1 PNG is enough to go through the real upload.
const RECEIPT = {
    name: 'boleta.png',
    mimeType: 'image/png',
    buffer: Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
    ),
};

async function openSidebar(page: Page) {
    if (page.viewportSize()!.width < 768) {
        await page.locator('[data-sidebar="trigger"]').first().click();
    }
}

/** "S/ 1200.50" → 120050. */
async function totalCents(page: Page): Promise<number> {
    const text = await page.getByTestId('expenses-total').innerText();
    const [soles, cents] = text.replace('S/ ', '').split('.');

    return Number(soles) * 100 + Number(cents);
}

test.describe('Gastos del salón', () => {
    test('se registra con foto, se ve en el total y el desglose, se edita sin foto y se elimina', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        // Both projects share the database: names of their own.
        const draft = `Volantes ${testInfo.project.name}`;
        const category = `Publicidad ${testInfo.project.name}`;

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        await page.getByRole('link', { name: 'Gastos' }).click();
        await expect(page).toHaveURL(/\/gastos$/);
        // On a phone the menu stays over the page after navigating.
        await page.keyboard.press('Escape');
        await expect(
            page.getByRole('button', { name: 'Mes siguiente' }),
        ).toBeDisabled();
        const before = await totalCents(page);

        // A category of their own, renamed on the way.
        await page.getByRole('button', { name: 'Categorías' }).click();
        const categories = page.getByRole('dialog', {
            name: 'Categorías de gastos',
        });
        await categories.getByLabel('Nueva categoría').fill(draft);
        await categories.getByRole('button', { name: 'Agregar' }).click();
        await expect(page.getByText('Categoría agregada.')).toBeVisible();
        await expect(categories.getByLabel('Nueva categoría')).toHaveValue('');
        await categories
            .getByRole('button', { name: `Renombrar ${draft}` })
            .click();
        await categories.getByLabel(`Nuevo nombre de ${draft}`).fill(category);
        await categories.getByRole('button', { name: 'Guardar' }).click();
        await expect(
            categories.getByRole('button', { name: `Eliminar ${category}` }),
        ).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(categories).toHaveCount(0);

        // The server's complaint shows next to the field.
        await page
            .getByRole('button', { name: 'Registrar gasto', exact: true })
            .click();
        const dialog = page.getByRole('dialog', { name: 'Registrar gasto' });
        await dialog.getByLabel('Monto').fill('0');
        await dialog.getByRole('button', { name: 'Guardar gasto' }).click();
        await expect(
            dialog.getByText('Escribe un monto mayor que cero'),
        ).toBeVisible();

        await dialog.getByLabel('Monto').fill('120,50');
        await dialog.getByLabel('Categoría').click();
        await page.getByRole('option', { name: category }).click();
        await dialog.getByText('Yape', { exact: true }).click();
        await dialog.getByLabel(/^Descripción/).fill('Volantes del barrio');
        await dialog
            .getByLabel('Foto del comprobante', { exact: true })
            .setInputFiles(RECEIPT);
        await expect(
            dialog.getByAltText('Vista previa del comprobante'),
        ).toBeVisible();
        await dialog.getByRole('button', { name: 'Guardar gasto' }).click();

        await expect(page.getByText('Gasto registrado.')).toBeVisible();
        await expect(dialog).toHaveCount(0);
        expect(await totalCents(page)).toBe(before + 12050);
        await expect(
            page
                .getByRole('region', { name: 'Resumen del mes' })
                .getByRole('listitem')
                .filter({ hasText: category }),
        ).toContainText('S/ 120.50');
        const row = page.getByRole('listitem', {
            name: new RegExp(`^${category} · S/ `),
        });
        await expect(row).toContainText('Volantes del barrio · Yape');
        await expect(row).toContainText('S/ 120.50');

        // The thumbnail opens the receipt big.
        await row.getByRole('button', { name: /^Ver comprobante/ }).click();
        const receipt = page.getByRole('dialog', { name: 'Comprobante' });
        await expect(
            receipt.getByAltText(/^Foto del comprobante/),
        ).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(receipt).toHaveCount(0);

        // Editing: another amount and no photo.
        await row.getByRole('button', { name: /^Editar gasto/ }).click();
        const editing = page.getByRole('dialog', { name: 'Editar gasto' });
        await expect(editing.getByLabel('Monto')).toHaveValue('120.50');
        await expect(
            editing.getByRole('radio', { name: 'Yape' }),
        ).toBeChecked();
        await editing.getByLabel('Monto').fill('80');
        await editing.getByRole('button', { name: 'Quitar foto' }).click();
        await expect(
            editing.getByAltText('Vista previa del comprobante'),
        ).toHaveCount(0);
        await editing.getByRole('button', { name: 'Guardar cambios' }).click();

        await expect(page.getByText('Gasto actualizado.')).toBeVisible();
        await expect(editing).toHaveCount(0);
        await expect(row).toContainText('S/ 80.00');
        await expect(
            row.getByRole('button', { name: /^Ver comprobante/ }),
        ).toHaveCount(0);
        expect(await totalCents(page)).toBe(before + 8000);
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);

        // Removing its category leaves the expense, uncategorized.
        await page.getByRole('button', { name: 'Categorías' }).click();
        await categories
            .getByRole('button', { name: `Eliminar ${category}` })
            .click();
        await page.getByRole('button', { name: 'Sí, eliminar' }).click();
        await expect(page.getByText(/^Categoría eliminada/)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(categories).toHaveCount(0);
        const orphan = page
            .getByRole('listitem', { name: /^Sin categoría · S\/ 80\.00/ })
            .filter({ hasText: 'Volantes del barrio' });
        await expect(orphan).toBeVisible();

        await orphan.getByRole('button', { name: /^Eliminar gasto/ }).click();
        await page.getByRole('button', { name: 'Sí, eliminar' }).click();
        await expect(page.getByText('Gasto eliminado.')).toBeVisible();
        await expect(orphan).toHaveCount(0);
        expect(await totalCents(page)).toBe(before);
        assertNoErrors();
    });

    test('quien no tiene el permiso de caja no ve "Gastos" y /gastos le responde 403', async ({
        page,
    }) => {
        await login(page, member.email, member.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);

        await expect(
            page.getByRole('link', { name: 'Mis reservas' }),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Gastos' })).toHaveCount(0);

        const response = await page.goto('/gastos');
        expect(response?.status()).toBe(403);
    });
});
