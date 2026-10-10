import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';
import { login, watchForErrors } from './support';

// Owner of "Salón Fichas" (E2eSeeder): one client per project, each with a
// missed visit, one charged yesterday and one coming tomorrow.
const fileOwner = { email: 'ficha-duena@example.com', password: 'password' };
const CLIENTS: Record<string, string> = {
    movil: 'Ficha Movil',
    escritorio: 'Ficha Escritorio',
};

const clientOf = (testInfo: TestInfo) => CLIENTS[testInfo.project.name];

// A 1x1 PNG is enough to go through the real upload.
const PHOTO = {
    name: 'trabajo.png',
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

async function hasHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
}

async function saveNotes(page: Page) {
    await page.getByRole('button', { name: 'Guardar notas' }).click();
}

test.describe('Ficha del cliente', () => {
    test('se abre desde Clientes, guarda preferencias y fórmula, y las alergias solo con la autorización firmada', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        const name = clientOf(testInfo);

        await login(page, fileOwner.email, fileOwner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await openSidebar(page);
        await page.getByRole('link', { name: 'Clientes' }).click();
        await expect(page).toHaveURL(/\/clientes$/);
        // On a phone the menu stays over the page after navigating.
        await page.keyboard.press('Escape');
        await page.getByRole('link', { name: `Ver ficha de ${name}` }).click();

        await expect(page).toHaveURL(/\/clientes\/\d+$/);
        await expect(page.getByRole('heading', { name })).toBeVisible();
        const summary = page.getByLabel('Resumen de visitas');
        await expect(summary).toContainText('Faltó 1 vez');
        await expect(summary).toContainText('S/ 35.00');
        await expect(
            page
                .getByRole('region', { name: 'Próximas citas' })
                .getByRole('listitem'),
        ).toHaveCount(1);
        const history = page.getByRole('region', { name: 'Historial' });
        await expect(history.getByRole('listitem')).toHaveCount(2);
        await expect(history).toContainText('Corte de ficha');
        await expect(history).toContainText('Cobrado S/ 35.00');
        await expect(history).toContainText('Como la última vez, sin máquina');

        // Without the authorization the care notes cannot even be typed.
        const care = page.getByRole('textbox', { name: 'Alergias y cuidado' });
        const consent = page.getByRole('checkbox', {
            name: `Declaro que ${name} firmó la autorización impresa y que el salón la conserva.`,
        });
        await expect(care).toBeDisabled();
        await expect(consent).not.toBeChecked();
        await expect(
            page.getByText(
                'Las alergias y otros datos de salud solo se guardan con la autorización escrita de la persona.',
            ),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Imprimir autorización' }),
        ).toBeVisible();

        await page.getByLabel('Preferencias').fill('Café sin azúcar');
        await page.getByLabel('Fórmula y técnica').fill('7.1 + 20 vol, 35 min');
        await saveNotes(page);
        await expect(page.getByText('Ficha actualizada.')).toBeVisible();

        await page.reload();
        await expect(page.getByLabel('Preferencias')).toHaveValue(
            'Café sin azúcar',
        );
        await expect(page.getByLabel('Fórmula y técnica')).toHaveValue(
            '7.1 + 20 vol, 35 min',
        );
        await expect(care).toBeDisabled();
        await expect(page.getByText(/^Autorización registrada/)).toHaveCount(0);

        // Declaring the signed authorization opens the field.
        await consent.check();
        await expect(care).toBeEnabled();
        await care.fill('Reacción al tinte con PPD');
        await saveNotes(page);
        await expect(page.getByText('Ficha actualizada.')).toBeVisible();

        await page.reload();
        await expect(
            page.getByText(
                /^Autorización registrada el \d+ \w+ \d{4} por Fabia Fichas$/,
            ),
        ).toBeVisible();
        await expect(consent).toBeChecked();
        await expect(care).toHaveValue('Reacción al tinte con PPD');
        expect(await hasHorizontalScroll(page)).toBe(false);

        // Withdrawing it erases what was written: asked before saving.
        await consent.uncheck();
        await expect(care).toBeDisabled();
        await saveNotes(page);
        const confirmation = page.getByRole('dialog', {
            name: '¿Retirar la autorización?',
        });
        await confirmation.getByRole('button', { name: 'Volver' }).click();
        await expect(confirmation).toHaveCount(0);
        await saveNotes(page);
        await confirmation
            .getByRole('button', { name: 'Sí, borrar y guardar' })
            .click();
        await expect(page.getByText('Ficha actualizada.')).toBeVisible();
        await expect(confirmation).toHaveCount(0);

        await page.reload();
        await expect(care).toBeDisabled();
        await expect(care).toHaveValue('');
        await expect(page.getByText(/^Autorización registrada/)).toHaveCount(0);
        assertNoErrors();
    });

    test('fotos antes y después: se sube con la autorización marcada, se ve grande y se elimina', async ({
        page,
    }, testInfo) => {
        const assertNoErrors = watchForErrors(page);
        const name = clientOf(testInfo);

        await login(page, fileOwner.email, fileOwner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/clientes');
        await page.getByRole('link', { name: `Ver ficha de ${name}` }).click();
        const section = page.getByRole('region', {
            name: 'Fotos antes y después',
        });
        await expect(section).toContainText(
            'Solo para el registro interno del salón. No se publican desde aquí.',
        );
        await expect(section).toContainText('Encuadra el trabajo, no la cara.');
        await expect(section).toContainText(
            'No subas fotos de menores de edad.',
        );
        await expect(section.getByText('0 de 40')).toBeVisible();
        await expect(section.getByText(/^Aún no hay fotos/)).toBeVisible();

        // Without the box the picture cannot be sent.
        const consent = section.getByRole('checkbox', {
            name: `${name} autorizó estas fotos para uso interno. Le avisé que no se publican y que puede pedir que se borren.`,
        });
        const save = section.getByRole('button', { name: 'Guardar foto' });
        await section.getByText('Después', { exact: true }).click();
        await section
            .getByLabel('Foto del trabajo', { exact: true })
            .setInputFiles(PHOTO);
        await expect(
            section.getByAltText('Vista previa de la foto'),
        ).toBeVisible();
        await expect(consent).not.toBeChecked();
        await expect(save).toBeDisabled();

        await consent.check();
        await save.click();
        await expect(page.getByText('Foto guardada.')).toBeVisible();
        await expect(section.getByText('1 de 40')).toBeVisible();
        // Every upload asks again.
        await expect(consent).not.toBeChecked();
        await expect(save).toBeDisabled();

        const thumbnail = section.getByRole('button', {
            name: /^Ver foto: Después · \d+ \w+ \d{4}$/,
        });
        await expect(thumbnail).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);
        await thumbnail.click();
        const viewer = page.getByRole('dialog', { name: 'Foto' });
        await expect(viewer.getByAltText(/^Después · /)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(viewer).toHaveCount(0);

        await section
            .getByRole('button', { name: /^Eliminar foto: Después/ })
            .click();
        await page.getByRole('button', { name: 'Sí, eliminar' }).click();
        await expect(page.getByText('Foto eliminada.')).toBeVisible();
        await expect(thumbnail).toHaveCount(0);
        await expect(section.getByText('0 de 40')).toBeVisible();
        assertNoErrors();
    });

    test('desde una cita de la agenda, "Ver ficha" lleva a la ficha', async ({
        page,
    }, testInfo) => {
        const name = clientOf(testInfo);

        await login(page, fileOwner.email, fileOwner.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/agenda');
        await page.getByRole('button', { name: 'Día siguiente' }).click();
        await page.getByRole('link', { name: `Ver ficha de ${name}` }).click();

        await expect(page).toHaveURL(/\/clientes\/\d+$/);
        await expect(page.getByRole('heading', { name })).toBeVisible();
        expect(await hasHorizontalScroll(page)).toBe(false);
    });
});
