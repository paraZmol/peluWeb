import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { cn } from '@/lib/utils';

/** The fine print of a promotion, folded: one tap away, never hidden. */
export function PromotionTerms({ text }: { text: string }) {
    const [isOpen, setIsOpen] = useState(false);
    const termsId = useId();

    return (
        <div className="flex flex-col">
            <button
                type="button"
                className="-ml-1 inline-flex h-11 w-fit items-center gap-1 rounded-full px-1 text-sm font-semibold text-muted-foreground outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                aria-expanded={isOpen}
                aria-controls={termsId}
                onClick={() => setIsOpen((open) => !open)}
            >
                {isOpen ? 'Ocultar condiciones' : 'Ver condiciones'}
                <ChevronDown
                    className={cn(
                        'size-4 transition-transform duration-200 motion-reduce:transition-none',
                        isOpen && 'rotate-180',
                    )}
                    aria-hidden
                />
            </button>
            {isOpen && (
                <p
                    id={termsId}
                    className="rounded-lg bg-muted p-3 text-sm leading-relaxed text-muted-foreground"
                >
                    {text}
                </p>
            )}
        </div>
    );
}
