import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PublicService } from '../types';

/** What a price may hide: an evaluation first, or options by length or size. */
export function ServiceExtras({ service }: { service: PublicService }) {
    const [isOpen, setIsOpen] = useState(false);
    const variantsId = useId();
    const hasVariantList = service.hasVariants && service.variants.length > 0;

    if (!service.requiresEvaluation && !hasVariantList) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1">
            {service.requiresEvaluation && (
                <p className="text-sm text-muted-foreground">
                    Precio final tras evaluación
                </p>
            )}
            {hasVariantList && (
                <>
                    <button
                        type="button"
                        className="-ml-1 inline-flex h-11 w-fit items-center gap-1 rounded-full px-1 text-sm font-semibold text-brand-text outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                        aria-expanded={isOpen}
                        aria-controls={variantsId}
                        onClick={() => setIsOpen((open) => !open)}
                    >
                        {isOpen ? 'Ocultar opciones' : 'Ver opciones'}
                        <ChevronDown
                            className={cn(
                                'size-4 transition-transform duration-200',
                                isOpen && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </button>
                    {isOpen && (
                        <ul
                            id={variantsId}
                            className="flex flex-col divide-y rounded-lg bg-muted px-3"
                        >
                            {service.variants.map((variant) => (
                                <li
                                    key={variant.label}
                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                >
                                    <span className="min-w-0 break-words">
                                        {variant.label}
                                    </span>
                                    <span className="shrink-0 font-semibold tabular-nums">
                                        {formatPrice(variant.price)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
