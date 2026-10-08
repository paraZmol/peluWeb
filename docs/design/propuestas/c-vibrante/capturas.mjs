// Capturas de la maqueta "Vibrante" sin servidor: abre los html por file://.
// Uso (desde la raíz del repo): node docs/design/propuestas/c-vibrante/capturas.mjs [filtro]
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const here = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(here, 'capturas');
mkdirSync(out, { recursive: true });

const shots = [
    ['portada-390.png', 'index.html', '', 390, 844],
    ['portada-1440.png', 'index.html', '', 1440, 900],
    ['salon-390.png', 'salon.html', '', 390, 844],
    ['salon-1440.png', 'salon.html', '', 1440, 900],
    ['salon-390-oscuro.png', 'salon.html', '?theme=dark', 390, 844],
    ['salon-390-marca-naranja.png', 'salon.html', '?brand=FF6B1A', 390, 844],
    ['reserva-390.png', 'reserva.html', '', 390, 844],
    // Pruebas extra de design-craft: marca oscura y marca muy clara.
    ['extra-salon-390-marca-grafito.png', 'salon.html', '?brand=1F2937', 390, 844],
    ['extra-salon-390-marca-ambar.png', 'salon.html', '?brand=F59E0B', 390, 844],
    ['extra-reserva-390-oscuro-naranja.png', 'reserva.html', '?theme=dark&brand=FF6B1A', 390, 844],
];

const filter = process.argv[2] ?? '';
const browser = await chromium.launch();

for (const [name, file, query, width, height] of shots) {
    if (!name.includes(filter)) continue;
    const context = await browser.newContext({
        viewport: { width, height },
        deviceScaleFactor: 1,
        reducedMotion: 'reduce',
        colorScheme: query.includes('dark') ? 'dark' : 'light',
    });
    const page = await context.newPage();
    await page.goto(pathToFileURL(path.join(here, file)).href + query, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    // Chromium no pinta iframes de otro origen fuera de pantalla: se recorren antes.
    const frames = await page.locator('iframe').count();
    for (let i = 0; i < frames; i++) {
        await page.evaluate((n) => document.querySelectorAll('iframe')[n].scrollIntoView({ block: 'center' }), i);
        await page.waitForTimeout(500);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    // Página completa estirando el viewport (con fullPage los iframes salen en blanco).
    // La barra fija queda al final de la página, como se ve al terminar de bajar.
    const full = await page.evaluate(() => document.documentElement.scrollHeight);
    await page.setViewportSize({ width, height: full });
    await page.waitForTimeout(700);
    await page.screenshot({ path: path.join(out, name) });
    await context.close();
    console.log('ok', name);
}

await browser.close();
