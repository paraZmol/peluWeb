// Captures for proposal B "Salón luminoso". No server: opens index.html via file://.
// Run from the repo root: node docs/design/propuestas/b-luminosa/capturas.mjs
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const out = join(here, 'capturas');
mkdirSync(out, { recursive: true });
const page = pathToFileURL(join(here, 'index.html')).href;

// [file, query, width, height, fullPage]
const shots = [
    ['portada-390', 'only=portada', 390, 844, true],
    ['portada-1440', 'only=portada', 1440, 900, true],
    ['salon-390', 'only=salon', 390, 844, true],
    ['salon-1440', 'only=salon', 1440, 900, true],
    ['salon-390-oscuro', 'only=salon&theme=dark', 390, 844, true],
    // Emerald: BrandColor picks near-black text (white only reaches 3.4:1).
    ['salon-390-marca-verde', 'only=salon&brand=0F9D7A&ink=1F1B18', 390, 844, true],
    ['reserva-390', 'only=reserva', 390, 844, true],
    // First fold only: what the client sees before scrolling (sticky bar included).
    ['salon-390-primer-pantallazo', 'only=salon', 390, 844, false],
    ['reserva-390-primer-pantallazo', 'only=reserva', 390, 844, false],
];

const browser = await chromium.launch();

for (const [name, query, width, height, fullPage] of shots) {
    const context = await browser.newContext({
        viewport: { width, height },
        deviceScaleFactor: 1,
        colorScheme: query.includes('theme=dark') ? 'dark' : 'light',
    });
    const tab = await context.newPage();
    await tab.goto(`${page}?${query}${fullPage ? '&full=1' : ''}`, { waitUntil: 'networkidle' });
    await tab.evaluate(() => document.fonts.ready);
    await tab.waitForTimeout(1500); // staggered entrances + embedded iframe
    if (fullPage) {
        // Chromium's fullPage mode does not paint the embedded phone (file:// iframes are
        // out-of-process). Growing the viewport to the whole page keeps everything on screen.
        const total = await tab.evaluate(() => document.documentElement.scrollHeight);
        await tab.setViewportSize({ width, height: total });
        await tab.waitForTimeout(1200);
    }
    await tab.screenshot({ path: join(out, `${name}.png`) });
    await context.close();
    console.log(`ok ${name}`);
}

await browser.close();
