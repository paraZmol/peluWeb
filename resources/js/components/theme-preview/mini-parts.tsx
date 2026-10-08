import type { CSSProperties, ReactNode } from 'react';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { MiniSalon } from './types';

/*
 * Building blocks of a miniature salon page. Every size is in `cqi` (percent
 * of the miniature's width) so the same page reads as a 150 px thumbnail or
 * a 360 px phone. Always rendered inside a BrandTheme: colors, fonts, radii
 * and skins come from the template around it.
 */

/** Letters of the longest word: what `display-fit` needs to never break a word. */
function longestWord(text: string): number {
    return Math.max(
        ...text.split(/\s+/u).map((word) => Array.from(word).length),
        1,
    );
}

type NameProps = {
    name: string;
    /** Upper bound for the size, relative to the name's own width. */
    fitSize?: string;
    className?: string;
};

/** The salon name in the template's display face, shrunk to fit. */
export function MiniName({ name, fitSize = '12cqi', className }: NameProps) {
    const style = {
        '--longest-word': longestWord(name),
        '--fit-size': fitSize,
    } as CSSProperties;

    return (
        <div className="@container w-full">
            <p
                className={cn('display-type display-fit', className)}
                style={style}
            >
                {name}
            </p>
        </div>
    );
}

type CoverProps = {
    src: string | null;
    className?: string;
    children?: ReactNode;
};

/** A photo with the template's frame; without one, a block of the brand color. */
export function MiniCover({ src, className, children }: CoverProps) {
    return (
        <div className={cn('photo-frame shrink-0', className)}>
            {src ? (
                <img src={src} alt="" loading="lazy" draggable={false} />
            ) : (
                <div className="size-full bg-primary" />
            )}
            {children}
        </div>
    );
}

type MonogramProps = {
    salon: MiniSalon;
    className?: string;
};

/** The salon logo, or its initials on the primary skin. */
export function MiniMonogram({ salon, className }: MonogramProps) {
    const initials = useInitials()(salon.name);

    if (salon.logoUrl) {
        return (
            <img
                src={salon.logoUrl}
                alt=""
                loading="lazy"
                draggable={false}
                className={cn('shrink-0 object-cover', className)}
            />
        );
    }

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center skin-primary font-bold',
                className,
            )}
        >
            {initials}
        </span>
    );
}

/** "Abierto" chip; on a photo it is glass, elsewhere a soft chip. */
export function MiniStatus({ onPhoto = false }: { onPhoto?: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-[1.5cqi] rounded-full px-[2.6cqi] py-[1.2cqi] text-[2.8cqi] font-semibold',
                onPhoto ? 'skin-glass' : 'skin-chip',
            )}
        >
            <span className="size-[1.6cqi] rounded-full bg-success" />
            Abierto · hasta las 8:00 p. m.
        </span>
    );
}

type PillsProps = {
    /** `chip` on a block of brand color, `soft` on a card. */
    tone?: 'soft' | 'chip';
    className?: string;
};

/** Two quick actions under the cover (WhatsApp, how to get there). */
export function MiniPills({ tone = 'soft', className }: PillsProps) {
    return (
        <div className={cn('flex gap-[2cqi]', className)}>
            {['WhatsApp', 'Cómo llegar'].map((label) => (
                <span
                    key={label}
                    className={cn(
                        'rounded-full px-[3cqi] py-[1.4cqi] text-[3cqi] font-semibold',
                        tone === 'soft'
                            ? 'bg-brand-soft text-brand-text'
                            : 'skin-chip',
                    )}
                >
                    {label}
                </span>
            ))}
        </div>
    );
}
