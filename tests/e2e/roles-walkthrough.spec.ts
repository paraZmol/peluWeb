import { execFileSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { expect, test, type Page } from '@playwright/test';
import { testEnv } from '../../playwright.config';
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
    /** What the salon switcher says: the salon and the role in it. */
    salon?: [name: string, role: 'Titular' | 'Personal'];
};

const SALON_PAGES = [
    '/agenda',
    // The day's line of people without a booking: only where the salon keeps one.
    '/fila',
    '/mi-salon',
    '/servicios',
    '/clientes',
    '/resenas',
    '/promociones',
    '/caja',
    '/gastos',
    '/comisiones',
    '/reportes',
    '/profesionales',
    '/equipo',
    '/mi-plan',
];
const PLATFORM = '/plataforma';
/** Every salon page, plus the professional's own hours. */
const ALL_SALON_PAGES = [...SALON_PAGES, '/mi-horario'];
const CLIENT_MENU = ['Panel', 'Mis reservas', 'Mis datos'];

/** Everything a team member cannot open, given what they can. */
const closedExcept = (open: string[]) => [
    ...ALL_SALON_PAGES.filter((path) => !open.includes(path)),
    PLATFORM,
];

/** A professional with one single extra permission: the menu it adds, the pages it opens. */
function withOnly(
    email: string,
    name: string,
    adds: string[],
    pages: string[],
): Role {
    return {
        label: `profesional solo con «${adds[0] ?? 'ver toda la agenda'}» (${email.split('@')[0]})`,
        email,
        name,
        menu: [
            'Panel',
            'Agenda',
            'Mi horario',
            ...adds,
            'Mis reservas',
            'Mis datos',
        ],
        closed: closedExcept(['/agenda', '/mi-horario', ...pages]),
        panel: 'Agenda de hoy',
        salon: ['Salón Permisos', 'Personal'],
    };
}

const roles: Role[] = [
    {
        label: 'dueña',
        email: 'caja-duena@example.com',
        name: 'Olga Obradora',
        menu: [
            'Panel',
            'Agenda',
            'Fila sin cita',
            'Mi salón',
            'Servicios',
            'Clientes',
            'Reseñas',
            'Promociones',
            'Caja',
            'Gastos',
            'Comisiones',
            'Reportes',
            'Profesionales',
            'Equipo',
            'Mi plan',
            'Mis reservas',
            'Mis datos',
        ],
        closed: [PLATFORM],
        panel: 'Tu equipo',
        salon: ['Salón Caja', 'Titular'],
    },
    {
        label: 'profesional sin permisos extra',
        email: 'sin-caja@example.com',
        name: 'Sara Sin Caja',
        menu: [
            'Panel',
            'Agenda',
            'Fila sin cita',
            'Mi horario',
            'Mis reservas',
            'Mis datos',
        ],
        closed: [
            ...SALON_PAGES.filter(
                (path) => path !== '/agenda' && path !== '/fila',
            ),
            PLATFORM,
        ],
        panel: 'Agenda de hoy',
        salon: ['Salón Caja', 'Personal'],
    },
    {
        label: 'profesional con caja y reportes',
        email: 'con-caja@example.com',
        name: 'Cleo Con Caja',
        menu: [
            'Panel',
            'Agenda',
            'Fila sin cita',
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
            '/promociones',
            '/profesionales',
            '/equipo',
            '/mi-plan',
            PLATFORM,
        ],
        panel: 'Agenda de hoy',
        salon: ['Salón Caja', 'Personal'],
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
    {
        label: 'dueña de dos salones (el primero)',
        email: 'multi@example.com',
        name: 'Marta Multi',
        // Also a professional in the first one: "Mi horario".
        menu: [
            'Panel',
            'Agenda',
            'Mi horario',
            'Mi salón',
            'Servicios',
            'Clientes',
            'Reseñas',
            'Promociones',
            'Caja',
            'Gastos',
            'Comisiones',
            'Reportes',
            'Profesionales',
            'Equipo',
            'Mi plan',
            'Mis reservas',
            'Mis datos',
        ],
        closed: [PLATFORM],
        panel: 'Tu equipo',
        salon: ['Salón Uno', 'Titular'],
    },
    {
        label: 'dueña de otro salón',
        email: 'permisos-duena@example.com',
        name: 'Perla Permisos',
        menu: [
            'Panel',
            'Agenda',
            'Mi salón',
            'Servicios',
            'Clientes',
            'Reseñas',
            'Promociones',
            'Caja',
            'Gastos',
            'Comisiones',
            'Reportes',
            'Profesionales',
            'Equipo',
            'Mi plan',
            'Mis reservas',
            'Mis datos',
        ],
        closed: [PLATFORM, '/mi-horario'],
        panel: 'Tu equipo',
        salon: ['Salón Permisos', 'Titular'],
    },
    {
        label: 'personal de recepción sin perfil de profesional',
        email: 'rita-ficha@example.com',
        name: 'Rita Recepcion',
        // Sees everyone's agenda, so the client list too (not the VIP switch).
        menu: ['Panel', 'Agenda', 'Clientes', 'Mis reservas', 'Mis datos'],
        closed: closedExcept(['/agenda', '/clientes']),
        panel: 'Agenda de hoy',
        salon: ['Salón Fichas', 'Personal'],
    },
    withOnly(
        'perm-perfil@example.com',
        'Pía Perfil',
        ['Mi salón', 'Reseñas'],
        ['/mi-salon', '/resenas'],
    ),
    withOnly(
        'perm-servicios@example.com',
        'Sol Servicios',
        ['Servicios'],
        ['/servicios'],
    ),
    withOnly(
        'perm-caja@example.com',
        'Cata Caja',
        ['Caja', 'Gastos', 'Comisiones'],
        ['/caja', '/gastos', '/comisiones'],
    ),
    withOnly(
        'perm-reportes@example.com',
        'Rubén Reportes',
        ['Reportes'],
        ['/reportes'],
    ),
    withOnly('perm-vip@example.com', 'Vico Vip', ['Clientes'], ['/clientes']),
    withOnly(
        'perm-agenda@example.com',
        'Ági Agenda',
        ['Clientes'],
        ['/clientes'],
    ),
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

/** The header of the menu says which salon this is and the role in it; clients have none. */
async function expectSalonAndRole(page: Page, salon: Role['salon']) {
    await openMenu(page);
    const switcher = page.locator('[data-test="salon-switcher"]');

    if (salon === undefined) {
        await expect(switcher).toHaveCount(0);
    } else {
        await expect(switcher).toContainText(salon[0]);
        await expect(switcher).toContainText(salon[1]);
    }

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
        await expectSalonAndRole(page, role.salon);
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

/** RFC 6238 code (SHA-1, 30 s, 6 digits) for a base32 secret. */
function totp(secret: string): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const key = Buffer.from(
        (bits.match(/.{8}/g) ?? []).map((byte) => parseInt(byte, 2)),
    );
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000)));
    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0xf;
    const value =
        ((hmac[offset] & 0x7f) << 24) |
        (hmac[offset + 1] << 16) |
        (hmac[offset + 2] << 8) |
        hmac[offset + 3];

    return String(value % 1_000_000).padStart(6, '0');
}

test('super admin con verificación en dos pasos: entra con su código y llega al panel de la plataforma desde su panel', async ({
    page,
}) => {
    test.setTimeout(120_000);
    const assertNoErrors = watchForErrors(page);

    await login(page, 'plataforma-2fa@example.com', 'password');
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page.locator('input').first().click();
    await page.keyboard.type(totp('JBSWY3DPEHPK3PXP'));
    if (/two-factor-challenge/.test(page.url())) {
        await page.getByRole('button', { name: /Continuar|Verificar/ }).click();
    }

    await expect(page).toHaveURL(/dashboard$/);
    await expectSignedInAs(page, 'Pablo Plataforma');
    expect(await menuTitles(page)).toEqual(CLIENT_MENU);
    await closeMenu(page);
    await expect(
        page.getByRole('link', {
            name: 'Activar la verificación en dos pasos',
        }),
    ).toHaveCount(0);

    await page
        .getByRole('link', { name: 'Abrir el panel de la plataforma' })
        .click();
    // The panel asks for the password again once before opening.
    if (/confirm-password/.test(page.url())) {
        await page.getByLabel('Contraseña', { exact: true }).fill('password');
        await page.keyboard.press('Enter');
    }
    await expect(page).toHaveURL(/\/plataforma/);
    await expect(page.getByText('Salones').first()).toBeVisible();
    await page.screenshot({
        path: 'storage/app/shots/recorrido/plataforma-2fa-panel.png',
    });
    assertNoErrors();
});

test('dueña de dos salones: el selector cambia el menú y el contenido, y lo de uno no aparece en el otro', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await login(page, 'multi@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);

    // Salón Uno has her professional profile and the "Corte de prueba".
    expect(await menuTitles(page)).toContain('Mi horario');
    await closeMenu(page);
    await page.goto('/servicios');
    await expect(page.getByText('Corte de prueba').first()).toBeVisible();

    await openMenu(page);
    await page.locator('[data-test="salon-switcher"]').click();
    await page.getByRole('menuitem', { name: 'Salón Dos Ñandú' }).click();
    await expect(page.locator('[data-test="salon-switcher"]')).toContainText(
        'Salón Dos Ñandú',
    );
    await expectSalonAndRole(page, ['Salón Dos Ñandú', 'Titular']);

    // She is only the owner here: no profile, so no "Mi horario".
    const menu = await menuTitles(page);
    expect(menu).not.toContain('Mi horario');
    expect(menu).toContain('Equipo');
    await closeMenu(page);

    await page.goto('/servicios');
    await expect(page.getByText('Corte de prueba')).toHaveCount(0);
    await page.goto('/equipo');
    await expect(page.getByText('Pedro Personal').first()).toBeVisible();

    // Back to the first one: Pedro is not in it.
    await openMenu(page);
    await page.locator('[data-test="salon-switcher"]').click();
    await page.getByRole('menuitem', { name: 'Salón Uno' }).click();
    await page.goto('/equipo');
    await expect(page.getByText('Pedro Personal')).toHaveCount(0);
});

test('dueña de otro salón: los ids de un salón ajeno dan 404 y su lista no los trae', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await login(page, 'ficha-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/clientes');
    await page.waitForLoadState('networkidle');
    const foreign = await page
        .locator('a[href*="/clientes/"]')
        .evaluateAll((links) =>
            links.map((link) => (link as HTMLAnchorElement).pathname),
        );
    expect(foreign.length, 'ids de Salón Fichas para atacar').toBeGreaterThan(
        0,
    );

    await page.context().clearCookies();
    await login(page, 'caja-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);

    for (const path of foreign.slice(0, 5)) {
        const response = await page.request.get(path, { maxRedirects: 0 });
        expect(response.status(), `${path} de otro salón`).toBe(404);
    }

    await page.goto('/clientes');
    await expect(page.getByText('Ficha Movil')).toHaveCount(0);
    await expect(page.getByText('Atendida Paco')).toHaveCount(0);
});

test('cuenta recién registrada sin correo verificado: solo ve la verificación y puede salir', async ({
    page,
}) => {
    await login(page, 'sin-verificar@example.com', 'password');
    await expect(page).toHaveURL(/email\/verify$/);
    await expect(
        page.getByRole('button', { name: 'Reenviar correo de verificación' }),
    ).toBeVisible();

    for (const path of [
        '/dashboard',
        '/mis-reservas',
        '/mis-datos',
        ...ALL_SALON_PAGES,
    ]) {
        const response = await page.request.get(path, { maxRedirects: 0 });
        expect(response.status(), `${path} sin verificar`).not.toBe(200);
    }

    await page.getByText('Cerrar sesión', { exact: true }).click();
    await expect(page).not.toHaveURL(/email\/verify|dashboard/);
});

test('menú de usuario: Ajustes abre con Perfil, Seguridad y Apariencia y se puede volver al Panel', async ({
    page,
}) => {
    test.setTimeout(120_000);
    const assertNoErrors = watchForErrors(page);

    for (const account of ['sin-caja@example.com', 'cliente@example.com']) {
        await page.context().clearCookies();
        await login(page, account, 'password');
        await expect(page).toHaveURL(/dashboard$/);
        await openMenu(page);
        await page.locator('[data-sidebar="footer"] button').first().click();
        await expect(
            page.getByRole('menuitem', { name: 'Cerrar sesión' }),
        ).toBeVisible();
        await page.getByRole('menuitem', { name: 'Ajustes' }).click();
        await expect(page).toHaveURL(/settings\/profile$/);
        await expect(
            page.getByText('Administra tu perfil y los ajustes de tu cuenta'),
        ).toBeVisible();
        for (const tab of ['Perfil', 'Seguridad', 'Apariencia']) {
            await expect(
                page
                    .getByRole('navigation', { name: 'Ajustes' })
                    .getByRole('link', { name: tab }),
            ).toBeVisible();
        }
        await expectRealPage(page, 'Ajustes');

        await openMenu(page);
        await page
            .locator('[data-sidebar="content"]')
            .getByRole('link', { name: 'Panel', exact: true })
            .click();
        await expect(page).toHaveURL(/dashboard$/);
    }
    assertNoErrors();
});

test('visitante: la portada lleva a entrar y a registrarse, y la página de un salón a reservar', async ({
    page,
}) => {
    const assertNoErrors = watchForErrors(page);

    await page.goto('/');
    await expect(page.getByRole('heading').first()).toBeVisible();
    const toLogin = page
        .getByRole('link', { name: /Iniciar sesión|Entrar/ })
        .first();
    // On a phone the landing may keep its links in a menu.
    if (!(await toLogin.isVisible())) {
        await page.getByRole('button', { name: /menú/i }).first().click();
    }
    await page
        .getByRole('link', { name: /Iniciar sesión|Entrar/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/login$/);
    await page
        .getByRole('link', { name: /Regístrate|Crear cuenta/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/register$/);

    await page.goto('/s/salon-uno');
    await page
        .getByRole('link', { name: /Reservar/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/s\/salon-uno\/reservar/);
    assertNoErrors();
});

test('promociones: el dueño las ve con su alta; quien no es dueño no las lee ni las crea', async ({
    page,
}) => {
    test.setTimeout(120_000);

    await login(page, 'caja-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/promociones');
    await expectRealPage(page, 'Promociones');
    // Seven starting points, each with its own card.
    for (const card of [
        'Descuento',
        'Servicio de regalo',
        'Horas flojas',
        'Campaña de temporada',
        'Primera visita',
        'Combo',
        'Ven con alguien',
    ]) {
        await expect(
            page.locator('main').getByText(card, { exact: true }).first(),
            `tarjeta «${card}»`,
        ).toBeVisible();
    }

    for (const account of [
        'con-caja@example.com',
        'sin-caja@example.com',
        'cliente@example.com',
    ]) {
        await page.context().clearCookies();
        await login(page, account, 'password');
        await expect(page).toHaveURL(/dashboard$/);
        const read = await page.request.get('/promociones', {
            maxRedirects: 0,
        });
        expect(read.status(), `leer promociones: ${account}`).not.toBe(200);
        const write = await page.request.post('/promociones', {
            maxRedirects: 0,
            data: { name: 'Intruso' },
        });
        expect(
            [302, 401, 403, 404, 419],
            `crear promociones: ${account} (${write.status()})`,
        ).toContain(write.status());
        expect(write.status()).not.toBe(200);
    }
});

test('avisos en este dispositivo: el personal y el cliente los encuentran en Ajustes', async ({
    page,
}) => {
    test.setTimeout(120_000);

    for (const account of ['sin-caja@example.com', 'cliente@example.com']) {
        await page.context().clearCookies();
        await login(page, account, 'password');
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto('/settings/profile');
        await expect(
            page.getByRole('region', { name: 'Avisos en este dispositivo' }),
        ).toBeVisible();
    }
});

test('fila sin cita: se abre desde el menú donde el salón la usa y no existe donde no', async ({
    page,
}) => {
    await login(page, 'permisos-duena@example.com', 'password');
    await expect(page).toHaveURL(/dashboard$/);
    expect(await menuTitles(page)).not.toContain('Fila sin cita');
    await closeMenu(page);
    const response = await page.request.get('/fila', { maxRedirects: 0 });
    expect(response.status(), '/fila sin la fila activada').not.toBe(200);
});

/** The platform's own screens (Planes, Salones): admins only, with two-step verification. */
const PLATFORM_SCREENS = ['/plataforma/plans', '/plataforma/salons'];

async function gotoPlatform(page: Page, path: string) {
    await page.goto(path);
    if (/confirm-password/.test(page.url())) {
        await page.getByLabel('Contraseña', { exact: true }).fill('password');
        await page.keyboard.press('Enter');
    }
    await expect(page).toHaveURL(new RegExp(path));
}

async function loginWithTwoFactor(page: Page) {
    await login(page, 'plataforma-2fa@example.com', 'password');
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page.locator('input').first().click();
    await page.keyboard.type(totp('JBSWY3DPEHPK3PXP'));
    if (/two-factor-challenge/.test(page.url())) {
        await page.getByRole('button', { name: /Continuar|Verificar/ }).click();
    }
    await expect(page).toHaveURL(/dashboard$/);
}

test('Fase 7, plataforma: quien no administra recibe 403 en Planes y Salones, y no ve la franja de soporte', async ({
    page,
}) => {
    test.setTimeout(120_000);

    for (const account of [
        'caja-duena@example.com',
        'sin-caja@example.com',
        'con-caja@example.com',
        'cliente@example.com',
    ]) {
        await page.context().clearCookies();
        await login(page, account, 'password');
        await expect(page).toHaveURL(/dashboard$/);

        for (const path of PLATFORM_SCREENS) {
            const response = await page.request.get(path, { maxRedirects: 0 });
            expect(response.status(), `${path} para ${account}`).toBe(403);
        }

        await expect(
            page.getByText('Modo soporte', { exact: true }),
        ).toHaveCount(0);
    }
});

test('Fase 7, plataforma: el super admin con dos pasos ve los planes y las acciones de un salón', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await loginWithTwoFactor(page);

    await gotoPlatform(page, '/plataforma/plans');
    for (const plan of ['Solo', 'Equipo']) {
        await expect(
            page.getByText(plan, { exact: true }).first(),
        ).toBeVisible();
    }

    await gotoPlatform(page, '/plataforma/salons');
    await page.getByText('Salón Caja', { exact: true }).first().click();
    for (const action of [
        'Registrar pago',
        'Prorrogar prueba',
        'Entrar como soporte',
    ]) {
        await expect(page.getByRole('button', { name: action })).toBeVisible();
    }
    // The payments tab loads when it scrolls into view.
    await page.mouse.wheel(0, 5000);
    await expect(
        page.getByText('Pagos', { exact: true }).first(),
    ).toBeVisible();
});

test('Fase 7, soporte: el super admin ve el salón como su titular sin poder cambiar nada, y sale', async ({
    page,
}) => {
    test.setTimeout(180_000);
    await loginWithTwoFactor(page);
    await gotoPlatform(page, '/plataforma/salons');
    await page.getByText('Salón Caja', { exact: true }).first().click();
    await page.getByRole('button', { name: 'Entrar como soporte' }).click();
    await page.getByLabel('Motivo').fill('Recorrido de roles');
    await page
        .getByRole('button', { name: /Confirmar|Enviar|Entrar/ })
        .last()
        .click();
    await expect(page).toHaveURL(/dashboard$/);

    await expect(page.getByText('Modo soporte', { exact: true })).toBeVisible();
    await expectSalonAndRole(page, ['Salón Caja', 'Titular']);
    expect(await menuTitles(page)).toEqual(roles[0].menu);
    await closeMenu(page);

    await page.goto('/mi-plan');
    await expectRealPage(page, 'Mi plan (soporte)');
    await page.goto('/mi-salon');
    await page
        .getByRole('button', { name: /Guardar datos/ })
        .first()
        .click();
    await expect(
        page.getByText(
            'Estás en modo soporte: puedes ver todo, pero no hacer cambios.',
        ),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Salir del modo soporte' }).click();
    // Out of the salon and back in the platform panel, not a panel inside a modal.
    await expect(page).toHaveURL(/plataforma\/salons\/\d+/);
    await expect(page.getByText('Modo soporte', { exact: true })).toHaveCount(
        0,
    );
    const after = await page.request.get('/agenda', { maxRedirects: 0 });
    expect(after.status(), 'tras salir ya no ve el salón').not.toBe(200);
});

/** Puts "Salón Fichas" in a subscription state; the test always puts it back. */
function setFichasSubscription(attributes: string) {
    execFileSync(
        'C:\\php84\\php.exe',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\Salon::where('slug','salon-fichas')->update([${attributes}]);`,
        ],
        { env: { ...process.env, ...testEnv } },
    );
}

test('Fase 7, aviso de suscripción: el titular oye de la gracia y la pausa; el personal solo de la pausa', async ({
    page,
}) => {
    test.setTimeout(240_000);
    const notice = page.locator('[data-test="subscription-notice"]');
    const as = async (email: string) => {
        await page.context().clearCookies();
        await login(page, email, 'password');
        await expect(page).toHaveURL(/dashboard$/);
    };

    try {
        setFichasSubscription(
            "'trial_ends_at'=>now()->subDays(2),'paid_until'=>null,'status'=>'active'",
        );
        await as('ficha-duena@example.com');
        await expect(notice).toContainText('Tu pago está vencido');
        await expect(
            notice.getByRole('link', { name: 'Ver mi plan' }),
        ).toBeVisible();
        await as('paco-ficha@example.com');
        await expect(notice).toHaveCount(0);

        setFichasSubscription(
            "'trial_ends_at'=>now()->subDays(40),'paid_until'=>null,'status'=>'active'",
        );
        await as('ficha-duena@example.com');
        await expect(notice).toContainText('Tu salón está en pausa');
        await page.goto('/mi-plan');
        await expectRealPage(page, 'Mi plan en pausa');
        await as('paco-ficha@example.com');
        await expect(notice).toContainText('El salón está en pausa');
        await expect(
            notice.getByRole('link', { name: 'Ver mi plan' }),
        ).toHaveCount(0);
    } finally {
        setFichasSubscription(
            "'trial_ends_at'=>now()->addDays(30),'paid_until'=>null,'status'=>'active'",
        );
    }
});

test('Fase 7, portada: los dos planes con su precio y la prueba gratis lleva a registrarse', async ({
    page,
}) => {
    const assertNoErrors = watchForErrors(page);
    await page.goto('/');
    const pricing = page.locator('section', {
        has: page.getByRole('heading', {
            name: 'Un precio fijo, sin comisión por cliente',
        }),
    });
    await expect(pricing.getByText('S/ 39').first()).toBeVisible();
    await expect(pricing.getByText('Solo', { exact: true })).toBeVisible();
    await expect(pricing.getByText('Equipo', { exact: true })).toBeVisible();
    await expect(pricing.getByText('Negocio')).toHaveCount(0);
    await pricing
        .getByRole('link', { name: 'Empezar prueba gratis' })
        .first()
        .click();
    await expect(page).toHaveURL(/\/register/);
    assertNoErrors();
});
