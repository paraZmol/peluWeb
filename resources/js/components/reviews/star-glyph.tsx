import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

/** One star, filled from the left by `fill` (0 to 1). */
export function StarGlyph({
    fill,
    className,
    onBrand = false,
}: {
    fill: number;
    className: string;
    onBrand?: boolean;
}) {
    return (
        <span aria-hidden className={cn('relative block shrink-0', className)}>
            <Star
                strokeWidth={1.75}
                className={cn(
                    'size-full',
                    // The empty outline still reads against the surface. On
                    // the brand color it keeps the full ink of its text
                    // (4.5:1 with any salon color): empty and filled differ
                    // by the fill, never by a faded line.
                    !onBrand && 'text-muted-foreground',
                )}
            />
            {fill > 0 && (
                <Star
                    strokeWidth={1.75}
                    className={cn(
                        'absolute inset-0 size-full fill-current',
                        !onBrand && 'text-warning',
                    )}
                    style={{ clipPath: `inset(0 ${(1 - fill) * 100}% 0 0)` }}
                />
            )}
        </span>
    );
}
