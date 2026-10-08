import { motion, useReducedMotion } from 'motion/react';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

type Props = {
    name: string;
    options: Option[];
    defaultValue?: string;
    columns?: 2 | 3;
    describedBy?: string;
};

/**
 * Big tappable radio cards. Native radios underneath, so they work inside an
 * uncontrolled Inertia <Form> and with the keyboard.
 */
export function ChoiceCards({
    name,
    options,
    defaultValue,
    columns = 2,
    describedBy,
}: Props) {
    const reduceMotion = useReducedMotion();

    return (
        <div
            role="radiogroup"
            aria-describedby={describedBy}
            className={cn(
                'grid gap-3',
                columns === 3 ? 'grid-cols-3' : 'grid-cols-2',
            )}
        >
            {options.map((option, index) => (
                <motion.label
                    key={option.value}
                    initial={reduceMotion ? false : { opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{
                        duration: 0.25,
                        delay: index * 0.04,
                        ease: [0.22, 1, 0.36, 1],
                    }}
                    className="relative block cursor-pointer"
                >
                    <input
                        type="radio"
                        name={name}
                        value={option.value}
                        defaultChecked={option.value === defaultValue}
                        className="peer sr-only"
                        required
                    />
                    <span className="flex min-h-12 items-center justify-center rounded-xl border bg-card px-4 py-3 text-center text-sm font-medium transition-colors peer-checked:border-primary peer-checked:bg-brand-soft peer-checked:text-foreground peer-checked:ring-1 peer-checked:ring-primary peer-checked:ring-inset peer-focus-visible:ring-2 peer-focus-visible:ring-ring hover:bg-accent">
                        {option.label}
                    </span>
                </motion.label>
            ))}
        </div>
    );
}
