import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';

const STAGGER_SECONDS = 0.06;

type Props = {
    order: number;
    className?: string;
    children: ReactNode;
};

/**
 * Section entrance: fade + 12px rise, staggered by `order`; only a short
 * fade with reduced motion. Never wrap something `fixed` in it: a
 * transformed ancestor breaks `fixed` on phones.
 */
export function Reveal({ order, className, children }: Props) {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{
                duration: reduceMotion ? 0.15 : 0.35,
                delay: reduceMotion ? 0 : order * STAGGER_SECONDS,
                ease: [0.22, 1, 0.36, 1],
            }}
            className={className}
        >
            {children}
        </motion.div>
    );
}
