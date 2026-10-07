import { Link } from '@inertiajs/react';
import { ChevronDown, Clock } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatMinutes, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PublicService } from './types';

type Props = {
    service: PublicService;
    /** Online booking wizard; falls back to WhatsApp when null. */
    bookingUrl: string | null;
};

export function ServiceRow({ service, bookingUrl }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const variantsId = useId();
    const hasVariantList = service.hasVariants && service.variants.length > 0;

    return (
        <li className="flex flex-col gap-2 p-4">
            <div className="flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <p className="font-medium break-words">{service.name}</p>
                    {service.description && (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {service.description}
                        </p>
                    )}
                    <p className="mt-1 flex items-center gap-1 text-sm text-muted-foreground tabular-nums">
                        <Clock className="size-3.5" aria-hidden />
                        {formatMinutes(service.minutes)}
                    </p>
                </div>
                <p className="shrink-0 text-right font-medium tabular-nums">
                    {service.hasVariants && (
                        <span className="block text-sm font-normal text-muted-foreground">
                            desde
                        </span>
                    )}
                    {formatPrice(service.priceFrom)}
                </p>
            </div>

            {service.requiresEvaluation && (
                <p className="text-sm text-muted-foreground">
                    Precio final tras evaluación
                </p>
            )}

            {bookingUrl && (
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="h-11 self-start rounded-full md:h-9"
                >
                    <Link
                        href={`${bookingUrl}?servicio=${service.id}`}
                        aria-label={`Reservar ${service.name}`}
                    >
                        Reservar
                    </Link>
                </Button>
            )}

            {!bookingUrl && service.whatsappUrl && (
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="h-11 self-start rounded-full md:h-9"
                >
                    <a
                        href={service.whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`Reservar ${service.name} por WhatsApp`}
                    >
                        Reservar
                    </a>
                </Button>
            )}

            {hasVariantList && (
                <>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-11 self-start px-2 text-primary md:h-11"
                        aria-expanded={isOpen}
                        aria-controls={variantsId}
                        onClick={() => setIsOpen((open) => !open)}
                    >
                        {isOpen ? 'Ocultar opciones' : 'Ver opciones'}
                        <ChevronDown
                            className={cn(
                                'transition-transform duration-200',
                                isOpen && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </Button>
                    {isOpen && (
                        <ul
                            id={variantsId}
                            className="flex flex-col divide-y rounded-lg bg-muted/50 px-3"
                        >
                            {service.variants.map((variant) => (
                                <li
                                    key={variant.label}
                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                >
                                    <span className="min-w-0 break-words">
                                        {variant.label}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {formatPrice(variant.price)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </li>
    );
}
