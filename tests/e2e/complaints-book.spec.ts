import { expect, test } from '@playwright/test';

/*
| The platform's Libro de Reclamaciones (requirement P6): anyone fills the
| sheet without an account and gets the receipt with its number.
*/

test('files a sheet without an account and shows its receipt', async ({
    page,
}, testInfo) => {
    await page.goto('/libro-de-reclamaciones');
    await expect(
        page.getByRole('heading', { name: 'Libro de Reclamaciones' }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('libro-formulario.png'),
        fullPage: true,
    });

    await page.getByLabel('Nombre completo').fill('Persona de Prueba');
    await page.getByLabel('Domicilio').fill('Jr. Las Flores 456, Lima');
    await page.getByLabel('Número de documento').fill('12345678');
    await page.getByLabel('Teléfono').fill('999888777');
    await page.getByLabel('Correo').fill('persona@example.test');
    await page.getByText('Reclamo', { exact: true }).first().click();
    await page
        .getByLabel('Descripción del producto o servicio')
        .fill('Reservas en línea');
    await page
        .getByLabel('Detalle: ¿qué pasó?')
        .fill('La reserva no aparecía en mi cuenta.');
    await page
        .getByLabel('Pedido: ¿qué solicitas?')
        .fill('Que revisen mi cuenta.');

    await page
        .getByRole('button', { name: 'Enviar hoja de reclamación' })
        .click();
    await expect(page.getByText('Marca la casilla')).toBeVisible();

    await page.getByLabel('Declaro que los datos son verdaderos').check();
    await page
        .getByRole('button', { name: 'Enviar hoja de reclamación' })
        .click();

    await expect(
        page.getByRole('heading', {
            name: /Recibimos tu reclamo N\.º \d{6}-\d{4}/,
        }),
    ).toBeVisible();
    await expect(page.getByText('persona@example.test')).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('libro-constancia.png'),
        fullPage: true,
    });
});
