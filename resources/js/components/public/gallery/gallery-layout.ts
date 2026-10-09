/** Tiles of the mosaic; the rest open from the last one ("+N"). */
export const MAX_TILES = 6;

/**
 * The grid for `count` tiles (plantillas.md §6): one photo wide, two side by
 * side, from three a mosaic with a big first tile — 2 columns on phones,
 * 3 from `lg`.
 */
export function galleryGridClass(count: number): string {
    if (count === 1) {
        return 'grid grid-cols-1';
    }

    if (count === 2) {
        return 'grid grid-cols-2 gap-3';
    }

    return 'grid grid-cols-2 auto-rows-[9.5rem] gap-3 lg:grid-cols-3 lg:auto-rows-[11.5rem]';
}

/** Rotating shapes: in Vibrante a mix of leaf, arch and rounded tiles. */
const SHAPES = [
    'rounded-tile',
    'rounded-portrait',
    'rounded-2xl',
    'rounded-tile',
    'rounded-2xl',
];

/** Size and shape of tile `index` among `count` tiles. */
export function galleryTileClass(index: number, count: number): string {
    if (count === 1) {
        return 'aspect-video rounded-feature';
    }

    if (count === 2) {
        return `aspect-4/5 ${SHAPES[index]}`;
    }

    if (index === 0) {
        return 'row-span-2 rounded-feature lg:col-span-2';
    }

    const isLast = index === count - 1;
    // Phones: after the big tile, pairs; an odd one out takes the full row.
    const phoneSpan = isLast && (count - 1) % 2 === 1 ? 'col-span-2' : '';
    // Desktop: the big tile and two beside it, then rows of three.
    const desktopRest = (count - 3) % 3;
    const desktopSpan = !isLast
        ? ''
        : desktopRest === 1
          ? 'lg:col-span-3'
          : desktopRest === 2
            ? 'lg:col-span-2'
            : 'lg:col-span-1';

    return [phoneSpan, desktopSpan, SHAPES[(index - 1) % SHAPES.length]]
        .filter(Boolean)
        .join(' ');
}
