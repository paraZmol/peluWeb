import { expect, test } from '@playwright/test';
import { login, watchForErrors } from './support';

const multi = { email: 'multi@example.com', password: 'password' };

test.describe('Profesionales y horarios', () => {
    test('la dueña agrega a alguien, arma su semana con la rueda y la copia', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'movil',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/profesionales');
        await page.getByRole('button', { name: 'Agregar profesional' }).click();
        await page.getByLabel('Nombre').fill('Carla Tijeras');
        await page
            .getByRole('button', { name: 'Agregar y definir horario' })
            .click();
        await expect(page).toHaveURL(/profesionales\/\d+\/horario$/);
        await expect(
            page.getByRole('heading', { name: 'Carla Tijeras' }),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Agregar horario el lunes' })
            .click();
        const dialog = page.getByRole('dialog');
        await expect(dialog.getByText('9:00 a. m.')).toBeVisible();

        // Alarm-style wheel with the keyboard: 9 → 10 a. m.
        await dialog.getByRole('spinbutton', { name: 'Hora' }).focus();
        await page.keyboard.press('ArrowDown');
        await expect(dialog.getByText('10:00 a. m.')).toBeVisible();
        await dialog.getByRole('button', { name: 'Listo' }).click();

        await page
            .getByRole('button', { name: 'Copiar el horario del lunes' })
            .click();
        await page
            .getByRole('menuitem', { name: 'De lunes a viernes' })
            .click();
        await expect(
            page.getByText('Tienes cambios sin guardar.'),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Guardar horario' }).click();
        await expect(page.getByText('Horario guardado.')).toBeVisible();
        await expect(
            page.getByText('Tienes cambios sin guardar.'),
        ).toBeHidden();

        await page.reload();
        await expect(
            page.getByRole('button', { name: /10:00 a\. m\. – 6:00 p\. m\./ }),
        ).toHaveCount(5);

        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(overflow).toBe(false);
        assertNoErrors();
    });

    test('no deja guardar un horario que se cruza con otro del mismo día', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'escritorio',
            'un solo flujo que modifica datos',
        );

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-horario');
        await expect(page).toHaveURL(/profesionales\/\d+\/horario$/);

        await page
            .getByRole('button', { name: 'Agregar horario el martes' })
            .click();
        await page.getByRole('button', { name: 'Listo' }).click();
        await page
            .getByRole('button', { name: 'Otro horario el martes' })
            .click();

        const dialog = page.getByRole('dialog');
        // Suggested 7–11 p. m.; move the start back into the first range.
        await dialog
            .getByRole('spinbutton', { name: 'Mañana o tarde' })
            .focus();
        await page.keyboard.press('ArrowUp');
        await dialog.getByRole('spinbutton', { name: 'Hora' }).focus();
        await page.keyboard.press('ArrowDown');
        await expect(dialog.getByRole('alert')).not.toBeEmpty();
        await expect(
            dialog.getByRole('button', { name: 'Listo' }),
        ).toBeDisabled();
    });

    test('registra una ausencia de todo el día y la muestra en la lista', async ({
        page,
    }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'escritorio',
            'un solo flujo que modifica datos',
        );
        const assertNoErrors = watchForErrors(page);

        await login(page, multi.email, multi.password);
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/mi-horario');
        await page.getByRole('tab', { name: 'Ausencias' }).click();
        await page.getByRole('button', { name: 'Agregar ausencia' }).click();
        await page.getByRole('button', { name: 'Mañana' }).click();
        await page.getByText('Permiso', { exact: true }).click();
        await page.getByLabel('Nota (opcional)').fill('Cita médica');
        await page.getByRole('button', { name: 'Guardar ausencia' }).click();

        await expect(page.getByText('Ausencia guardada.')).toBeVisible();
        await expect(page.getByText(/· todo el día/)).toBeVisible();
        await expect(page.getByText('Cita médica')).toBeVisible();
        assertNoErrors();
    });

    test('la página pública muestra el horario y que está abierto (claro y oscuro)', async ({
        page,
    }) => {
        for (const colorScheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme });
            const assertNoErrors = watchForErrors(page);

            await page.goto('/s/salon-uno');
            await expect(page.getByText(/Abierto ahora/)).toBeVisible();
            await expect(
                page.getByRole('heading', { name: 'Horario de atención' }),
            ).toBeVisible();
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBe(false);
            assertNoErrors();
        }
    });

    test('el personal entra a su propio horario pero no a la lista del equipo', async ({
        page,
    }) => {
        await login(page, 'personal@example.com', 'password');
        await expect(page).toHaveURL(/dashboard$/);

        await page.goto('/mi-horario');
        await expect(
            page.getByRole('heading', { name: 'Pedro Personal' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Profesionales' }),
        ).toHaveCount(0);

        const response = await page.goto('/profesionales');
        expect(response?.status()).toBe(403);
    });
});
