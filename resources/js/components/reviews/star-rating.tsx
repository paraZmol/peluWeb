import { motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import {
    ratingText,
    starFill,
    starsLabel,
    STAR_POSITIONS,
} from '@/lib/reviews';
import { cn } from '@/lib/utils';
import { StarGlyph } from './star-glyph';

const SIZES = { sm: 'size-4', md: 'size-5', lg: 'size-7' };

type Props = {
    /** 0 while nothing is chosen; an average may carry a decimal. */
    value: number;
    size?: keyof typeof SIZES;
    className?: string;
    /** On a block of the brand color the stars take the color of its text. */
    onBrand?: boolean;
    /** With it the stars become a group of radios named "Calificación". */
    onChange?: (rating: number) => void;
    /** Field name of the radios. */
    name?: string;
    /** Ids of the hint and the error that go with the radios. */
    describedBy?: string;
    isInvalid?: boolean;
};

/**
 * The five stars, to read a rating or to choose one. Reading: one image
 * with the rating as its name. Choosing: native radios (arrow keys, screen
 * readers), each a 44 px target with the star drawn under it.
 */
export function StarRating({
    value,
    size = 'md',
    className,
    onBrand = false,
    onChange,
    name = 'rating',
    describedBy,
    isInvalid = false,
}: Props) {
    const reduceMotion = useReducedMotion();
    const [hovered, setHovered] = useState(0);

    if (!onChange) {
        return (
            <span
                role="img"
                aria-label={ratingText(value)}
                className={cn('inline-flex items-center gap-0.5', className)}
            >
                {STAR_POSITIONS.map((position) => (
                    <StarGlyph
                        key={position}
                        fill={starFill(value, position)}
                        className={SIZES[size]}
                        onBrand={onBrand}
                    />
                ))}
            </span>
        );
    }

    const shown = hovered || value;

    return (
        <div
            role="radiogroup"
            aria-label="Calificación"
            aria-required
            aria-invalid={isInvalid}
            aria-describedby={describedBy}
            onMouseLeave={() => setHovered(0)}
            className={cn('flex items-center', className)}
        >
            {STAR_POSITIONS.map((position) => (
                <label
                    key={position}
                    onMouseEnter={() => setHovered(position)}
                    className="relative flex size-11 cursor-pointer items-center justify-center sm:size-12"
                >
                    <input
                        type="radio"
                        name={name}
                        value={position}
                        checked={value === position}
                        onChange={() => onChange(position)}
                        aria-label={starsLabel(position)}
                        className="peer absolute inset-0 z-10 cursor-pointer opacity-0"
                    />
                    <span
                        aria-hidden
                        className="pointer-events-none absolute inset-0.5 rounded-full peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring"
                    />
                    <motion.span
                        // The chosen stars answer the tap once, in a wave.
                        key={`${position}-${position <= value}`}
                        initial={
                            position <= value && !reduceMotion
                                ? { scale: 0.8 }
                                : false
                        }
                        animate={{ scale: 1 }}
                        transition={{
                            duration: 0.25,
                            delay: position * 0.03,
                            ease: [0.22, 1, 0.36, 1],
                        }}
                    >
                        <StarGlyph
                            fill={position <= shown ? 1 : 0}
                            className="size-8 sm:size-9"
                        />
                    </motion.span>
                </label>
            ))}
        </div>
    );
}
