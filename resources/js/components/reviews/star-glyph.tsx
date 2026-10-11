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
                    // The empty outline still reads at 3:1 against the surface.
                    onBrand ? 'opacity-40' : 'text-muted-foreground',
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
