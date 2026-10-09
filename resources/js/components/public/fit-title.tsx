import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';

type TitleSize = 'title-1' | 'title-2' | 'title-3';

const SMALLER: Record<TitleSize, TitleSize> = {
    'title-1': 'title-2',
    'title-2': 'title-3',
    'title-3': 'title-3',
};

/** Past this many letters a name would take more than three lines at full size. */
const LONG_NAME = 26;
const VERY_LONG_NAME = 40;

type Props = {
    text: string;
    as?: 'h1' | 'h2' | 'h3' | 'p';
    /** Largest size it may take (the name of the salon: title-1). */
    size?: TitleSize;
    className?: string;
    id?: string;
};

/**
 * A display title with a proper name in it (salon, professional). It shrinks
 * just enough for its longest word to fit, so Bebas or Syne never split a
 * word (`display-fit`, ui-system §2.1). Its parent must be an `@container`.
 */
export function FitTitle({
    text,
    as: Tag = 'h1',
    size = 'title-1',
    className,
    id,
}: Props) {
    // One letter of slack: --display-char is an average of wide glyphs, and
    // a word full of them (Syne's "m", "á") would still overflow by a hair.
    const longestWord =
        Math.max(...text.split(/\s+/u).map((word) => Array.from(word).length)) +
        1;
    const length = Array.from(text).length;
    const cap =
        length > VERY_LONG_NAME
            ? 'title-3'
            : length > LONG_NAME
              ? SMALLER[size]
              : size;
    const style = {
        '--longest-word': longestWord,
        '--fit-size': `var(--${cap})`,
    } as CSSProperties;

    return (
        <Tag
            id={id}
            // Never split a name: if a glyph runs wider than the estimate,
            // a hair of overflow lands in the padding instead.
            className={cn(
                'display-type display-fit [overflow-wrap:normal]',
                className,
            )}
            style={style}
        >
            {text}
        </Tag>
    );
}
