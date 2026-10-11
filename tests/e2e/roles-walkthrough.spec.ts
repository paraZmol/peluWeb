import { expect, test, type Page } from '@playwright/test';
import { login, watchForErrors } from './support';

/*
| What a person meets in their first minute, by role: who the app says they
| are, the exact menu their permissions give them, every entry of it opening
| a page with something on it, and the doors that are not theirs staying
| shut. Feature specs prove each screen works; this one proves nobody lands
| somewhere empty, mislabelled or not theirs.
*/

type Role = {
    label: string;
    email: string;
    name: string;
    /** The sidebar, top to bottom, exactly. */
    menu: string[];
    /** Pages of the app this account must not get. */
    closed: string[];
    /** Something only this role's first screen says. */
    panel: string | RegExp;
};

const SALON_PAGES = [
    '/agenda',
    '/mi-salon',
    '/servicios',
    '/clientes',
    '/resenas',
    '/caja',
    '/gastos',
    '/comisiones',
    '/reportes',
    '/profesionales',
    '/equipo',
];
const PLATFORM = '/plataforma';
const CLIENT_MENU = ['Panel', 'Mis reservas', 'Mis datos'];

const roles: Role[] = [
    {
        label: 'dueña',
        email: 'caja-duena@example.com',
        name: 'Olga Obradora',
        menu: [
            'Panel',
            'Agenda',
            'Mi salón',
            'Servicios',
            'Clientes',
            'Reseñas',
            'Caja',
            'Gastos',
            'Comisiones',
            'Reportes',
            'Profesionales',
            'Equipo',
            'Mis reservas',
            'Mis datos',
        ],
        closed: [PLATFORM],
        panel: 'Tu equipo',
    },
    {
        label: 'profesional sin permisos extra',
        email: 'sin-caja@example.com',
        name: 'Sara Sin Caja',
        menu: ['Panel', 'Agenda', 'Mi horario', 'Mis reservas', 'Mis datos'],
        closed: [...SALON_PAGES.filter((path) => path !== '/agenda'), PLATFORM],
        panel: 'Agenda de hoy',
    },
    {
        label: 'profesional con caja y reportes',
        email: 'con-caja@example.com',
        name: 'Cleo Con Caja',
        menu: [
            'Panel',
            'Agenda',
            'Mi horario',
            'Caja',
            'Gastos',
            'Comisiones',
            'Reportes',
            'Mis reservas',
            'Mis datos',
        ],
        closed: [
            '/mi-salon',
            '/servicios',
            '/resenas',
            '/profesionales',
            '/equipo',
            PLATFORM,
        ],
        panel: 'Agenda de hoy',
    },
    {
        label: 'cliente',
        email: 'cliente@example.com',
        name: 'Carla Cliente',
        menu: CLIENT_MENU,
        closed: [...SALON_PAGES, PLATFORM],
        panel: 'Crear mi salón',
    },
    {
        label: 'super admin sin verificación en dos pasos',
        email: 'plataforma@example.com',
        name: 'Paula Plataforma',
        menu: CLIENT_MENU,
        closed: SALON_PAGES,
        panel: 'Administras la plataforma',
    },
];

const DRAWER = '[data-sidebar="sidebar"][data-mobile="true"]';

/** On a phone the menu lives in a drawer. */
async function openMenu(page: Page) {
    const trigger = page.locator('[data-sidebar="trigger"]').first();
    const isPhone = (page.viewportSize()?.width ?? 0) < 768;

    if (isPhone && (await page.locator(DRAWER).count()) === 0) {
        await trigger.click();
    }

    await expect(
        page.locator('[data-sidebar="menu-button"]').first(),
    ).toBeVisible();
}

/** Waits for the drawer to be gone: a click during its animation misses. */
async function closeMenu(page: Page) {
    if ((await page.locator(DRAWER).count()) > 0) {
        await page.keyboard.press('Escape');
        await expect(page.locator(DRAWER)).toHaveCount(0);
    }
}

async function menuTitles(page: Page): Promise<string[]> {
    await openMenu(page);
    const titles = await page
        .locator('[data-sidebar="content"] [data-sidebar="menu-button"]')
        .allInnerTexts();

    return titles.map((title) => title.trim());
}

async function expectSignedInAs(page: Page, name: string) {
    await openMenu(page);
    await expect(page.locator('[data-sidebar="footer"]')).toContainText(name);
    await closeMenu(page);
}

/** A page with a title and something under it, that fits the screen. */
async function expectRealPage(page: Page, entry: string) {
    const main = page.locator('main');

    await expect(
        main.getByRole('heading').first(),
        `«${entry}» tiene título`,
    ).toBeVisible();
    expect(
        ((await main.innerText()) ?? '').replace(/\s+/g, ' ').trim().length,
        `«${entry}» no está en blanco`,
    ).toBeGreaterThan(20);
    await expect(
        page.getByText(/^(403|404|419|500)$|Server Error|No encontrado/),
        `«${entry}» no es una página de error`,
    ).toHaveCount(0);
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        ),
        `«${entry}» no desborda a lo ancho`,
    ).toBe(false);
}

async function signOut(page: Page) {
    await openMenu(page);
    await page.locator('[data-sidebar="footer"] button').first().click();
    await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click();
    await expect(page).not.toHaveURL(/dashboard|agenda|mis-/);
}

for (const role of roles) {
    test(`${role.label}: se reconoce, ve su menú exacto y cada entrada abre una página`, async ({
        page,
    }) => {
        test.setTimeout(240_000);
        const assertNoErrors = watchForErrors(page);

        await login(page, role.email, 'password');
        await expect(page).toHaveURL(/dashboard$/);
        await expectSignedInAs(page, role.name);
        await expect(
            page.getByRole('heading', {
                name: `Hola, ${role.name.split(' ')[0]}`,
            }),
        ).toBeVisible();
        await expect(page.locator('main').getByText(role.panel)).toBeVisible();

        expect(await menuTitles(page), 'menú según sus permisos').toEqual(
            role.menu,
        );
        await closeMenu(page);

        for (const entry of role.menu) {
            await openMenu(page);
            await page
                .locator('[data-sidebar="content"]')
                .getByRole('link', { name: entry, exact: true })
                .click();
            await page.waitForLoadState('networkidle');
            await expectRealPage(page, entry);
            await expectSignedInAs(page, role.name);
        }

        assertNoErrors();
    });

    test(`${role.label}: lo que no le toca no se abre escribiendo la dirección`, async ({
        page,
    }) => {
        await login(page, role.email, 'password');
        await expect(page).toHaveURL(/dashboard$/);

        for (const path of role.closed) {
            const response = await page.request.get(path, { maxRedirects: 0 });

            expect(
                response.status(),
                `${path} cerrado para ${role.label}`,
            ).not.toBe(200);
        }
    });
}

test('super admin sin verificación en dos pasos: el panel de la plataforma le dice qué falta', async ({
    page,
}) => {
    await login(page, 'plataforma@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);
    await expect(
        page.getByRole('link', {
            name: 'Activar la verificación en dos pasos',
        }),
    ).toBeVisible();

    await page.goto(PLATFORM);
    // The panel asks for the password again first.
    await page.getByLabel('Contraseña', { exact: true }).fill('password');
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(/settings\/security$/);
    await expect(
        page.getByText(
            'Activa la verificación en dos pasos para entrar al panel de la plataforma.',
        ),
    ).toBeVisible();
});

test('cambiar de cuenta en el mismo navegador no deja nada de la anterior', async ({
    page,
}) => {
    const [owner, , , client] = roles;

    await login(page, owner.email, 'password');
    await expect(page).toHaveURL(/dashboard$/);
    await expectSignedInAs(page, owner.name);
    await signOut(page);

    await login(page, client.email, 'password');
    await expect(page).toHaveURL(/dashboard$/);
    await expectSignedInAs(page, client.name);
    expect(await menuTitles(page)).toEqual(client.menu);
    await closeMenu(page);

    // "Back" must not bring the owner's screens to the client.
    await page.goBack();
    await expect(page.getByText(owner.name)).toHaveCount(0);
    for (const path of SALON_PAGES) {
        const response = await page.request.get(path, { maxRedirects: 0 });
        expect(response.status(), `${path} tras cambiar de cuenta`).not.toBe(
            200,
        );
    }
});

test('visitante: las páginas con sesión piden entrar', async ({ page }) => {
    for (const path of [
        '/dashboard',
        '/mis-reservas',
        '/mis-datos',
        ...SALON_PAGES,
        PLATFORM,
    ]) {
        const response = await page.request.get(path, { maxRedirects: 0 });
        expect(response.status(), `${path} sin sesión`).not.toBe(200);
    }
});
