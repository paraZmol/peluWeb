import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useState } from 'react';
import { takeBookingCelebration } from '@/lib/celebration';
import { ConfettiBurst } from './confetti-burst';

type Props = {
    /** Code of a booking: confetti only the first time after it was made. */
    celebrateKey?: string;
};

/** A check that draws itself once: the "it worked" moment of a booking. */
export function SuccessCheck({ celebrateKey }: Props) {
    const reduceMotion = useReducedMotion();
    const [celebrates, setCelebrates] = useState(false);

    useEffect(() => {
        if (celebrateKey && takeBookingCelebration(celebrateKey)) {
            setCelebrates(true);
        }
    }, [celebrateKey]);

    return (
        <div className="relative">
            {celebrates && !reduceMotion && <ConfettiBurst />}
            <motion.div
                aria-hidden
                initial={reduceMotion ? false : { scale: 0.6, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                className="relative flex size-16 items-center justify-center rounded-full bg-success/15 text-success"
            >
                <svg viewBox="0 0 24 24" className="size-9" fill="none">
                    <motion.path
                        d="M5 12.5l4.5 4.5L19 7.5"
                        stroke="currentColor"
                        strokeWidth={2.5}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        initial={reduceMotion ? false : { pathLength: 0 }}
                        animate={{ pathLength: 1 }}
                        transition={{ duration: 0.4, delay: 0.15 }}
                    />
                </svg>
            </motion.div>
        </div>
    );
}
