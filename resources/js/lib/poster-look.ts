import { paintPoster } from './poster-canvas';
import type { PosterFonts, PosterPalette } from './poster-canvas';
import type { PosterContent } from './promo-poster';

/*
 | What the Instagram image needs from the page: the colors and fonts of
 | the template it is drawn in (read from an element that wears it), and
 | how the PNG reaches the owner's device.
 */

/** Long enough for the browser to start saving the file. */
const KEEP_URL_MS = 10_000;

/** A token of the template, as the color the browser resolved. */
function read(probe: HTMLElement, token: string): string {
    probe.style.color = `var(${token})`;

    return getComputedStyle(probe).color;
}

function family(probe: HTMLElement, token: string): string {
    probe.style.fontFamily = `var(${token})`;

    return getComputedStyle(probe).fontFamily;
}

/** The template's colors and fonts, once the fonts are ready to draw. */
async function lookOf(
    probe: HTMLElement,
): Promise<{ palette: PosterPalette; fonts: PosterFonts }> {
    const fonts = {
        display: family(probe, '--font-display'),
        sans: family(probe, '--font-sans'),
    };
    // A font is only fetched when some text uses it: ask for each one drawn.
    await Promise.all(
        [
            `600 84px ${fonts.display}`,
            `500 38px ${fonts.sans}`,
            `600 38px ${fonts.sans}`,
            `700 30px ${fonts.sans}`,
            `800 112px ${fonts.sans}`,
        ].map((font) => document.fonts.load(font)),
    );
    await document.fonts.ready;

    return {
        fonts,
        palette: {
            background: read(probe, '--background'),
            foreground: read(probe, '--foreground'),
            muted: read(probe, '--muted-foreground'),
            primary: read(probe, '--primary'),
            primaryForeground: read(probe, '--primary-foreground'),
            primaryEnd: read(probe, '--brand-2'),
            accent: read(probe, '--brand-text'),
        },
    };
}

/** Hands a file to the browser to save, as a link to it would. */
export function saveFile(blob: Blob, name: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), KEEP_URL_MS);
}

/** The image as a PNG, drawn with the look of the element given. */
export async function posterBlob(
    probe: HTMLElement,
    content: PosterContent,
): Promise<Blob> {
    const { palette, fonts } = await lookOf(probe);
    const canvas = document.createElement('canvas');
    paintPoster(canvas, content, palette, fonts);
    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/png'),
    );

    if (!blob) {
        throw new Error('The image could not be made.');
    }

    return blob;
}
