import { drawBlocks, heightOf, lay } from './poster-text';
import type { LaidBlock, TextBlock } from './poster-text';
import type { PosterContent } from './promo-poster';

/*
 | Draws the Instagram image of a promotion on a canvas (1080 × 1350, the
 | 4:5 portrait a feed shows whole): a block in the salon's color with its
 | name, the title and the offer, and under it with whom, when, how long,
 | how many and where the conditions are. Only text: nothing from another
 | origin, so the canvas can always be exported.
 */

export const POSTER_WIDTH = 1080;
export const POSTER_HEIGHT = 1350;

const MARGIN = 48;
const PADDING = 72;
const GAP = 44;
const RADIUS = 44;
const MIN_SCALE = 0.55;
/** Room for the short line in the brand's color over the facts. */
const BAR_SPACE = 28;

export type PosterPalette = {
    background: string;
    foreground: string;
    muted: string;
    primary: string;
    primaryForeground: string;
    /** The second tone of the brand gradient. */
    primaryEnd: string;
    /** The brand as text on the background. */
    accent: string;
};

export type PosterFonts = { display: string; sans: string };

type Look = { palette: PosterPalette; fonts: PosterFonts; scale: number };

/** The words, block by block: inside the color, at its foot, and under it. */
function blocksOf(content: PosterContent, { palette, fonts, scale }: Look) {
    const on = palette.primaryForeground;
    const sans = (size: number, weight: number, rest: Partial<TextBlock>) =>
        ({
            weight,
            family: fonts.sans,
            size: Math.round(size * scale),
            lineHeight: 1.25,
            color: on,
            text: '',
            ...rest,
            gap: Math.round((rest.gap ?? 0) * scale),
        }) satisfies TextBlock;
    const { benefit } = content;
    const present = <T>(value: T | null, block: (value: T) => TextBlock) =>
        value === null ? [] : [block(value)];

    return {
        top: [
            sans(30, 700, {
                text: content.salonName,
                maxLines: 1,
                isUpper: true,
            }),
            ...present(content.overline, (text) =>
                sans(30, 700, { text, gap: 36, maxLines: 1, isUpper: true }),
            ),
            sans(84, 600, {
                text: content.title,
                family: fonts.display,
                lineHeight: 1.05,
                gap: content.overline ? 12 : 36,
                maxLines: 4,
            }),
            ...present(content.subtitle, (text) =>
                sans(38, 500, { text, gap: 20, maxLines: 3 }),
            ),
        ],
        offer: benefit
            ? [
                  ...present(benefit.normal, (text) =>
                      sans(48, 500, { text, isStruck: true }),
                  ),
                  sans(124, 800, {
                      text: benefit.headline,
                      lineHeight: 1.05,
                      maxLines: 2,
                      fitsDownTo: 0.6,
                  }),
                  ...present(benefit.caption, (text) =>
                      sans(36, 500, { text, gap: 16, maxLines: 3 }),
                  ),
              ]
            : [],
        below: [
            ...content.facts.map((text, index) =>
                sans(38, 600, {
                    text,
                    lineHeight: 1.3,
                    color: palette.foreground,
                    gap: index === 0 ? 0 : 14,
                }),
            ),
            sans(31, 500, {
                text: content.link,
                lineHeight: 1.3,
                color: palette.muted,
                gap: 28,
            }),
        ],
    };
}

/** The blocks laid out, at the largest size where all of them fit. */
function layout(
    context: CanvasRenderingContext2D,
    content: PosterContent,
    look: Omit<Look, 'scale'>,
): LaidBlock[][] {
    const width = POSTER_WIDTH - 2 * (MARGIN + PADDING);
    let groups: LaidBlock[][] = [];

    for (let scale = 1; scale >= MIN_SCALE; scale -= 0.05) {
        const { top, offer, below } = blocksOf(content, { ...look, scale });
        groups = [top, offer, below].map((group) =>
            group.map((block) => lay(context, block, width)),
        );
        const needed = groups.reduce(
            (total, group) => total + heightOf(group),
            0,
        );

        if (
            needed + 2 * MARGIN + 2.5 * PADDING + 2 * GAP + BAR_SPACE <=
            POSTER_HEIGHT
        ) {
            break;
        }
    }

    return groups;
}

/** The color block: the brand's gradient and a soft circle of light. */
function paintPanel(
    context: CanvasRenderingContext2D,
    palette: PosterPalette,
    height: number,
) {
    const gradient = context.createLinearGradient(
        MARGIN,
        MARGIN,
        POSTER_WIDTH - MARGIN,
        MARGIN + height,
    );
    gradient.addColorStop(0, palette.primary);
    gradient.addColorStop(1, palette.primaryEnd);
    context.fillStyle = gradient;
    context.beginPath();
    context.roundRect(
        MARGIN,
        MARGIN,
        POSTER_WIDTH - 2 * MARGIN,
        height,
        RADIUS,
    );
    context.fill();

    context.save();
    context.clip();
    context.globalAlpha = 0.09;
    context.fillStyle = palette.primaryForeground;
    context.beginPath();
    context.arc(POSTER_WIDTH - MARGIN - 40, MARGIN + 60, 300, 0, Math.PI * 2);
    context.fill();
    context.restore();
}

/** Paints the whole image; smaller type when the words would not fit. */
export function paintPoster(
    canvas: HTMLCanvasElement,
    content: PosterContent,
    palette: PosterPalette,
    fonts: PosterFonts,
): void {
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Canvas 2D is not available.');
    }

    canvas.width = POSTER_WIDTH;
    canvas.height = POSTER_HEIGHT;
    const [top, offer, below] = layout(context, content, { palette, fonts });
    const belowTop = POSTER_HEIGHT - MARGIN - PADDING / 2 - heightOf(below);
    const panelHeight = belowTop - BAR_SPACE - GAP - MARGIN;
    const x = MARGIN + PADDING;

    context.fillStyle = palette.background;
    context.fillRect(0, 0, POSTER_WIDTH, POSTER_HEIGHT);
    paintPanel(context, palette, panelHeight);
    drawBlocks(context, top, x, MARGIN + PADDING);
    drawBlocks(
        context,
        offer,
        x,
        MARGIN + panelHeight - PADDING - heightOf(offer),
    );

    context.fillStyle = palette.accent;
    context.fillRect(x, belowTop - BAR_SPACE, 96, 8);
    drawBlocks(context, below, x, belowTop);
}
