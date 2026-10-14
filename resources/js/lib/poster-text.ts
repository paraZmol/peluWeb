/*
 | Text on a canvas for the image of a promotion: blocks that wrap to a
 | width, shrink to fit one line when asked, and draw one under the other.
 */

export type TextBlock = {
    text: string;
    weight: number;
    family: string;
    /** In pixels, before any fitting. */
    size: number;
    lineHeight: number;
    color: string;
    /** Space above it. */
    gap?: number;
    maxLines?: number;
    isUpper?: boolean;
    isStruck?: boolean;
    /** Shrinks (down to this share of its size) to stay on one line. */
    fitsDownTo?: number;
};

export type LaidBlock = TextBlock & { lines: string[]; height: number };

const fontOf = (block: TextBlock) =>
    `${block.weight} ${block.size}px ${block.family}`;

/**
 * Where a word too long for one line (a link) is cut: after its last "/"
 * that fits, else after the last letter that fits.
 */
function cutAt(context: CanvasRenderingContext2D, word: string, width: number) {
    let cut = word.length - 1;

    while (cut > 1 && context.measureText(word.slice(0, cut)).width > width) {
        cut--;
    }

    const slash = word.lastIndexOf('/', cut - 1) + 1;

    return slash > 0 ? slash : cut;
}

/** Lines that fit the width. */
function wrap(context: CanvasRenderingContext2D, text: string, width: number) {
    const lines: string[] = [];
    let line = '';

    for (const word of text.split(' ')) {
        const tried = line === '' ? word : `${line} ${word}`;

        if (context.measureText(tried).width <= width) {
            line = tried;
            continue;
        }

        if (line !== '') {
            lines.push(line);
        }

        line = word;

        while (context.measureText(line).width > width && line.length > 1) {
            const cut = cutAt(context, line, width);
            lines.push(line.slice(0, cut));
            line = line.slice(cut);
        }
    }

    return line === '' ? lines : [...lines, line];
}

/** The block cut into lines for the width, smaller if it must fit one. */
export function lay(
    context: CanvasRenderingContext2D,
    block: TextBlock,
    width: number,
): LaidBlock {
    const text = block.isUpper ? block.text.toUpperCase() : block.text;
    const smallest = block.size * (block.fitsDownTo ?? 1);
    let fitted = block;
    context.font = fontOf(fitted);

    while (context.measureText(text).width > width && fitted.size > smallest) {
        fitted = { ...fitted, size: Math.max(smallest, fitted.size * 0.94) };
        context.font = fontOf(fitted);
    }

    let lines = wrap(context, text, width);

    if (block.maxLines && lines.length > block.maxLines) {
        lines = lines.slice(0, block.maxLines);
        lines[lines.length - 1] = `${lines.at(-1)}…`;
    }

    return {
        ...fitted,
        lines,
        height: lines.length * fitted.size * fitted.lineHeight,
    };
}

export const heightOf = (blocks: LaidBlock[]) =>
    blocks.reduce((total, block) => total + (block.gap ?? 0) + block.height, 0);

/** Draws the blocks from `top` down, each line centred in its height. */
export function drawBlocks(
    context: CanvasRenderingContext2D,
    blocks: LaidBlock[],
    x: number,
    top: number,
): void {
    let y = top;

    for (const block of blocks) {
        y += block.gap ?? 0;
        context.font = fontOf(block);
        context.fillStyle = block.color;
        context.strokeStyle = block.color;
        context.textBaseline = 'middle';

        for (const line of block.lines) {
            const middle = y + (block.size * block.lineHeight) / 2;
            y += block.size * block.lineHeight;
            context.fillText(line, x, middle);

            if (block.isStruck) {
                context.lineWidth = Math.max(3, block.size / 14);
                context.beginPath();
                context.moveTo(x - 4, middle);
                context.lineTo(x + context.measureText(line).width + 4, middle);
                context.stroke();
            }
        }
    }
}
