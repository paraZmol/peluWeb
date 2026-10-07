import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';

const EASE = [0.22, 1, 0.36, 1] as const;

/**
 * The time that was just tapped, arriving in the summary: it shows up still
 * shaped like the chip, and the chip dissolves around it. (The steps swap
 * one after the other, so the chip itself cannot travel between them.)
 */
export function PickedTime({ children }: { children: ReactNode }) {
    const reduceMotion = useReducedMotion();

    if (reduceMotion) {
        return <>{children}</>;
    }

    return (
        <span className="relative inline-block">
            <motion.span
                aria-hidden
                className="absolute -inset-x-3 -inset-y-1 rounded-full border border-primary bg-primary/15"
                initial={{ opacity: 1, scale: 0.92 }}
                animate={{ opacity: 0, scale: 1.06 }}
                transition={{ duration: 0.6, delay: 0.2, ease: EASE }}
            />
            <motion.span
                className="relative inline-block origin-left"
                initial={{ opacity: 0, scale: 0.85 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.25, delay: 0.05, ease: EASE }}
            >
                {children}
            </motion.span>
        </span>
    );
}
