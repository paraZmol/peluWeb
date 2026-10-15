import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

// Nobody else's spec looks at this account's preferences.
const rita = { email: 'rita-ficha@example.com', password: 'password' };

test('el recordatorio por correo se apaga y se vuelve a encender desde el perfil', async ({
    page,
}) => {
    const assertNoErrors = watchForErrors(page);
    const reminders = page.getByRole('checkbox', {
        name: 'Enviarme por correo el recordatorio de mis citas y el pedido de reseña',
    });
    const save = page.getByRole('button', { name: 'Guardar', exact: true });

    await login(page, rita.email, rita.password);
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/settings/profile');

    // Both projects share the database: start from whatever the other left.
    if (!(await reminders.isChecked())) {
        await reminders.check();
        await save.click();
        await expect(page.getByText('Perfil actualizado.')).toBeVisible();
    }

    await reminders.uncheck();
    await save.click();
    await page.reload();
    await expect(reminders).not.toBeChecked();

    await reminders.check();
    await save.click();
    await page.reload();
    await expect(reminders).toBeChecked();

    const overflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
    expect(overflow).toBe(false);
    assertNoErrors();
});
