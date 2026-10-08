// Capturas de la maqueta "Barbería nocturna" sin servidor (file://).
// Uso: node docs/design/propuestas/a-nocturna/capturas.mjs [filtro]
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const out = join(here, 'capturas');
mkdirSync(out, { recursive: true });

const MOBILE = { width: 390, height: 844 };
const DESKTOP = { width: 1440, height: 900 };

const shots = [
    { name: 'portada-390', page: 'portada.html', viewport: MOBILE },
    { name: 'portada-1440', page: 'portada.html', viewport: DESKTOP },
    { name: 'salon-390', page: 'salon.html', viewport: MOBILE },
    { name: 'salon-1440', page: 'salon.html', viewport: DESKTOP },
    { name: 'salon-390-claro', page: 'salon.html', query: '?tema=claro', viewport: MOBILE },
    { name: 'salon-390-marca-roja', page: 'salon.html', query: '?marca=E63946', viewport: MOBILE },
    { name: 'salon-390-marca-oscura', page: 'salon.html', query: '?marca=1F2937', viewport: MOBILE },
    { name: 'reserva-390', page: 'reserva.html', viewport: MOBILE },
    { name: 'reserva-1440', page: 'reserva.html', viewport: DESKTOP },
    { name: 'reserva-390-claro', page: 'reserva.html', query: '?tema=claro', viewport: MOBILE },
];

const filter = process.argv[2] ?? '';
const browser = await chromium.launch();

for (const shot of shots.filter((s) => s.name.includes(filter))) {
    const context = await browser.newContext({
        viewport: shot.viewport,
        deviceScaleFactor: 1,
        reducedMotion: 'reduce', // estado final de las entradas escalonadas
        locale: 'es-PE',
    });
    const page = await context.newPage();
    const url = pathToFileURL(join(here, shot.page)).href + (shot.query ?? '');
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    // Las barras fijas (position: fixed) quedan al pie de la página completa, como al llegar al final.
    const fullHeight = await page.evaluate(() => document.documentElement.scrollHeight);
    await page.setViewportSize({ width: shot.viewport.width, height: fullHeight });
    await page.screenshot({ path: join(out, `${shot.name}.png`), fullPage: true });
    console.log(`ok ${shot.name}`);
    await context.close();
}

await browser.close();
