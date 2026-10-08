// Screenshots of the CURRENT app with the real photos, for the comparison page.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const out = 'C:/Users/nunes/StudioProjects/peluWeb/docs/design/propuestas/actual';
mkdirSync(out, { recursive: true });
const base = 'http://127.0.0.1:8000';
const browser = await chromium.launch();

for (const [name, width, height, path] of [
    ['portada-390', 390, 844, '/'],
    ['portada-1440', 1440, 900, '/'],
    ['salon-390', 390, 844, '/s/barberia-don-lucho'],
    ['salon-1440', 1440, 900, '/s/barberia-don-lucho'],
    ['reserva-390', 390, 844, '/s/barberia-don-lucho/reservar'],
]) {
    const page = await browser.newPage({ viewport: { width, height }, locale: 'es-PE' });
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: name !== 'salon-1440' && name !== 'portada-1440' });
    await page.close();
}

await browser.close();
console.log('ok');
