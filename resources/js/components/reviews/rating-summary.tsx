import { BadgeCheck } from 'lucide-react';
import { formatAverage, verifiedCountLabel } from '@/lib/reviews';
import { cn } from '@/lib/utils';
import { StarRating } from './star-rating';

type Props = {
    average: number;
    count: number;
    /** A line under the count: who can leave a review. */
    note?: string;
    /** The panel sits on the brand color: stars and text take its ink. */
    onBrand?: boolean;
    /** Surface of the panel (a skin, or a brand tint). */
    className?: string;
};

/** The average, large, with its stars and how many reviews stand behind it. */
export function RatingSummary({
    average,
    count,
    note,
    onBrand = false,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex items-center gap-4 rounded-xl p-4 sm:gap-6 sm:p-6',
                className,
            )}
        >
            {/* Digits in the body face: a display font is not made for them. */}
            <p className="font-sans text-6xl leading-none font-extrabold tracking-tight tabular-nums sm:text-7xl">
                {formatAverage(average)}
            </p>
            <div className="flex min-w-0 flex-col gap-1.5">
                <StarRating value={average} size="lg" onBrand={onBrand} />
                <p className="flex items-center gap-1.5 text-sm font-semibold">
                    <BadgeCheck className="size-4 shrink-0" aria-hidden />
                    {verifiedCountLabel(count)}
                </p>
                {note && (
                    <p
                        className={cn(
                            'text-sm text-balance',
                            onBrand ? 'opacity-85' : 'text-muted-foreground',
                        )}
                    >
                        {note}
                    </p>
                )}
            </div>
        </div>
    );
}
