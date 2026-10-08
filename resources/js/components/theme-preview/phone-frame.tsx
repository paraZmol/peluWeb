import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    children: ReactNode;
};

/**
 * A phone drawn with markup around a screen of fixed proportion. The bezel
 * uses the veil color, which stays dark in both modes.
 */
export function PhoneFrame({ className, children }: Props) {
    return (
        <div
            className={cn(
                'relative rounded-[2.75rem] bg-veil p-2.5 shadow-float ring-1 ring-border-strong',
                className,
            )}
        >
            <div className="relative aspect-[9/19] overflow-hidden rounded-[2.2rem]">
                {children}
                <span
                    aria-hidden
                    className="absolute top-2.5 left-1/2 z-30 h-5 w-20 -translate-x-1/2 rounded-full bg-veil"
                />
            </div>
        </div>
    );
}
