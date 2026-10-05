import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';

const EASE_OUT = [0.22, 1, 0.36, 1] as const;
const RISE_PX = 16;

type Props = {
    delay?: number;
    className?: string;
    children: ReactNode;
};

/** Scroll reveal: fade + 16px rise the first time it enters the viewport; only a short fade with reduced motion. */
export function Reveal({ delay = 0, className, children }: Props) {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            initial={{ opacity: 0, y: reduceMotion ? 0 : RISE_PX }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '0px 0px -64px 0px' }}
            transition={{
                duration: reduceMotion ? 0.15 : 0.4,
                delay: reduceMotion ? 0 : delay,
                ease: EASE_OUT,
            }}
            className={className}
        >
            {children}
        </motion.div>
    );
}
