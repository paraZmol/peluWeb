import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

// Salón Caja keeps a line of people without a booking; Cleo works there.
const cleo = { email: 'con-caja@example.com', password: 'password' };

test('fila sin cita: el equipo anota a quien llega, lo llama y cierra su lugar', async ({
    page,
}, testInfo) => {
    const assertNoErrors = watchForErrors(page);
    // Both projects share the database: one name each.
    const name = `Luis ${testInfo.project.name}`;

    await login(page, cleo.email, cleo.password);
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/fila');
    await expect(
        page.getByRole('heading', { name: 'Fila sin cita', level: 1 }),
    ).toBeVisible();

    await page.getByLabel('Nombre').fill(name);
    await page.getByRole('button', { name: 'Anotar en la fila' }).click();
    await expect(page.getByText(`${name} está en la fila.`)).toBeVisible();
    // Ready for the next person who walks in.
    await expect(page.getByLabel('Nombre')).toHaveValue('');

    await page.getByRole('button', { name: `Llamar a ${name}` }).click();
    await expect(
        page.getByRole('button', { name: `Devolver a ${name} a la espera` }),
    ).toBeVisible();

    await page.getByRole('button', { name: `${name}: se atendió` }).click();
    const closed = page.getByRole('region', { name: 'Hoy ya pasaron' });
    await expect(closed.getByText(name)).toBeVisible();
    await expect(
        page.getByRole('button', { name: `Llamar a ${name}` }),
    ).toHaveCount(0);

    // Closed by mistake: the same day, they go back to the line.
    await closed
        .getByRole('button', { name: `Volver a la fila: ${name}` })
        .click();
    await expect(
        page.getByRole('button', { name: `Llamar a ${name}` }),
    ).toBeVisible();
    await expect(page.getByText(`${name} volvió a la fila`)).toBeAttached();
    await page.getByRole('button', { name: `${name}: se fue` }).click();
    await expect(closed.getByText(name)).toBeVisible();

    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        ),
    ).toBe(false);
    assertNoErrors();
});
