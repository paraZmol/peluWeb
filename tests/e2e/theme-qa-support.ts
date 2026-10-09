import { expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

/** Shared by the 4.12 (d) template specs: showcase salon, accounts and page checks. */
export const owner = { email: 'vitrina@example.com', password: 'password' };
export const client = { email: 'cliente@example.com', password: 'password' };
export const SLUG = 'estudio-vitrina';

export type Theme = 'editorial' | 'nocturna' | 'luminosa' | 'vibrante';
export const LABEL: Record<Theme, string> = {
    editorial: 'Editorial',
    nocturna: 'Nocturna',
    luminosa: 'Luminosa',
    vibrante: 'Vibrante',
};
// Nocturna goes last: it is the salon's original template.
export const THEMES: Theme[] = [
    'vibrante',
    'luminosa',
    'editorial',
    'nocturna',
];

export async function chooseTheme(page: Page, theme: Theme) {
    await page.goto('/mi-salon');
    const section = page.locator('#diseno');
    // The label may carry an "En uso" badge, so pick the card by its radio.
    await section
        .locator('label')
        .filter({ has: page.locator(`input[value="${theme}"]`) })
        .click();
    await section.getByRole('button', { name: 'Guardar diseño' }).click();
    await expect(page.getByText('Diseño guardado.')).toBeVisible();
    await expect(
        section.getByRole('radio', { name: new RegExp(`^${LABEL[theme]}`) }),
    ).toBeChecked();
}

/** Every element that declares a template wears the expected one. */
export async function expectTemplate(page: Page, theme: Theme) {
    await expect(
        page.locator(`[data-theme="${theme}"]`).first(),
    ).toBeAttached();
    // The signed-in shell (app layout) wraps the page in the platform's look:
    // an element that contains the salon's template is that wrapper, not a leak.
    const foreign = await page
        .locator('[data-theme]')
        .evaluateAll(
            (elements, expected) =>
                elements
                    .filter(
                        (element) =>
                            element.getAttribute('data-theme') !== expected &&
                            element.querySelector(
                                `[data-theme="${expected}"]`,
                            ) === null,
                    )
                    .map((element) => element.getAttribute('data-theme')),
            theme,
        );
    expect(foreign, 'elementos con otra plantilla').toEqual([]);
}

export async function expectNoHorizontalScroll(page: Page) {
    const overflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );
    expect(overflow, 'scroll horizontal').toBe(false);
}

/**
 * Visible surfaces painted with a light background: the "pieces that stayed
 * light" inside a page that must be dark. Images, frames (the Google map is
 * light by decision) and tiny dots are ignored.
 */
export async function lightSurfaces(
    page: Page,
    scope?: Locator,
): Promise<string[]> {
    const handle = await (
        scope ?? page.locator('[data-theme="nocturna"]').first()
    ).elementHandle();

    return page.evaluate((root) => {
        const luminance = (color: string): number | null => {
            const match = color.match(/rgba?\(([^)]+)\)/);
            if (!match) {
                return null;
            }
            const [r, g, b, a = 1] = match[1]
                .split(/[ ,/]+/)
                .filter(Boolean)
                .map(Number);
            if (a < 0.6) {
                return null;
            }
            const channel = (value: number) => {
                const c = value / 255;
                return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
            };
            return (
                0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
            );
        };
        const found: string[] = [];
        const base = (root as Element | null) ?? document.documentElement;
        const all = [base, ...Array.from(base.querySelectorAll('*'))];
        for (const element of all) {
            if (
                element.closest(
                    'iframe, img, svg, video, canvas, [aria-hidden="true"]',
                )
            ) {
                continue;
            }
            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            if (
                rect.width * rect.height < 900 ||
                style.visibility === 'hidden' ||
                style.display === 'none' ||
                Number(style.opacity) < 0.5
            ) {
                continue;
            }
            const light = luminance(style.backgroundColor);
            if (light !== null && light > 0.6) {
                const classes = (element.getAttribute('class') ?? '').slice(
                    0,
                    70,
                );
                found.push(
                    `${element.tagName.toLowerCase()}.${classes} ${style.backgroundColor}`,
                );
            }
        }
        return found;
    }, handle);
}

export async function isDark(page: Page): Promise<boolean> {
    return page.evaluate(() => {
        const root = document.querySelector('[data-theme="nocturna"]');
        return root !== null && root.closest('.dark') !== null;
    });
}

/** Steps 1 to 3 of the wizard in the showcase salon. */
export async function chooseUntilConfirm(
    page: Page,
    service: RegExp = /Corte clásico/,
    staff: RegExp = /José Antonio/,
    dayIndex = 1,
) {
    await page.getByRole('button', { name: service }).click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    await page.getByRole('button', { name: staff }).click();
    await expect(page.getByRole('heading', { name: '¿Cuándo?' })).toBeVisible();
    await page.getByRole('radio').nth(dayIndex).click();
    // A late slot, far from the cancel/move cut-off whatever the hour of the run.
    await page
        .getByRole('button', { name: /^\d{1,2}:\d{2} [ap]\. m\.$/ })
        .last()
        .click();
    await expect(
        page.getByRole('heading', { name: 'Revisa y confirma' }),
    ).toBeVisible();
}
