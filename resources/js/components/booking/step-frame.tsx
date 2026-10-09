import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { StepHeading } from './step-heading';

type Props = {
    step: number;
    /** 1 forward, -1 back: the step slides that way. */
    direction: number;
    title: string;
    subtitle: string | null;
    /** What was picked so far: under the question on phones, aside on desktop. */
    recap?: ReactNode;
    /** A single centered column (confirming). */
    narrow?: boolean;
    children: ReactNode;
};

/** One step of the public wizard: its question, the recap and its content. */
export function StepFrame({
    step,
    direction,
    title,
    subtitle,
    recap,
    narrow = false,
    children,
}: Props) {
    const reduceMotion = useReducedMotion();
    const offset = reduceMotion ? 0 : 32 * direction;

    return (
        <AnimatePresence mode="wait" initial={false}>
            <motion.div
                key={step}
                initial={{ opacity: 0, x: offset }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -offset }}
                transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
                className={cn(
                    'grid grid-cols-1 gap-6',
                    recap && 'lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-x-12',
                    narrow && 'mx-auto max-w-2xl',
                )}
            >
                <StepHeading title={title} subtitle={subtitle} />
                {recap && (
                    <div className="lg:sticky lg:top-36 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
                        {recap}
                    </div>
                )}
                <div className="flex min-w-0 flex-col gap-5 lg:col-start-1">
                    {children}
                </div>
            </motion.div>
        </AnimatePresence>
    );
}
