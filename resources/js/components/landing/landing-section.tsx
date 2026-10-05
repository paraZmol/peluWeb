import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const CONTAINER_CLASS = 'mx-auto w-full max-w-6xl px-4 sm:px-6';

const TONE_CLASSES = {
    plain: 'bg-background',
    muted: 'border-y bg-muted/40',
    card: 'border-y bg-card',
} as const;

type Props = {
    id?: string;
    tone?: keyof typeof TONE_CLASSES;
    className?: string;
    children: ReactNode;
};

/** Full-width band with the shared vertical rhythm; `scroll-mt-20` keeps titles below the sticky header. */
export function LandingSection({
    id,
    tone = 'plain',
    className,
    children,
}: Props) {
    return (
        <section
            id={id}
            className={cn(
                'scroll-mt-20 py-16 md:py-24',
                TONE_CLASSES[tone],
                className,
            )}
        >
            <div className={CONTAINER_CLASS}>{children}</div>
        </section>
    );
}
