import { motion } from 'motion/react';

const PIECES = 14;
const COLORS = ['bg-primary', 'bg-success', 'bg-warning', 'bg-primary/50'];
const SHAPES = ['h-2.5 w-1.5 rounded-[2px]', 'size-2 rounded-full'];
const MIN_DISTANCE_PX = 52;
const DISTANCE_STEP_PX = 14;
/** Pieces drift down a little as they fade, like paper would. */
const FALL_PX = 14;

/**
 * A short burst of paper around whatever it is placed in (which must be
 * `relative`). Decorative, plays once and leaves nothing behind.
 */
export function ConfettiBurst() {
    return (
        <span aria-hidden className="pointer-events-none absolute inset-0">
            {Array.from({ length: PIECES }, (_, index) => {
                // Fixed angles and three rings: lively without being random.
                const angle = (index / PIECES) * 2 * Math.PI - Math.PI / 2;
                const distance =
                    MIN_DISTANCE_PX + (index % 3) * DISTANCE_STEP_PX;

                return (
                    <motion.span
                        key={index}
                        className={`absolute top-1/2 left-1/2 -mt-1 -ml-1 ${COLORS[index % COLORS.length]} ${SHAPES[index % SHAPES.length]}`}
                        initial={{ x: 0, y: 0, scale: 0.4, opacity: 0 }}
                        animate={{
                            x: Math.cos(angle) * distance,
                            y: Math.sin(angle) * distance + FALL_PX,
                            rotate: (index % 2 === 0 ? 1 : -1) * 160,
                            scale: 1,
                            opacity: [0, 1, 1, 0],
                        }}
                        transition={{
                            duration: 0.6,
                            delay: 0.25,
                            ease: [0.22, 1, 0.36, 1],
                            opacity: { duration: 0.6, delay: 0.25 },
                        }}
                    />
                );
            })}
        </span>
    );
}
