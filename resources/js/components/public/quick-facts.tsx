import { MapPin, Tag } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PublicSalon } from './types';

type Fact = { key: string; icon: ReactNode; text: string; href?: string };

/** What helps decide at a glance: the lowest price and where it is. */
export function salonFacts(salon: PublicSalon): Fact[] {
    const prices = salon.serviceGroups.flatMap((group) =>
        group.services.map((service) => service.priceFrom),
    );
    const lowest = prices.reduce<string | null>(
        (min, price) =>
            min === null || Number(price) < Number(min) ? price : min,
        null,
    );
    const facts: Fact[] = [];

    if (lowest !== null) {
        facts.push({
            key: 'price',
            icon: <Tag aria-hidden />,
            text: `Servicios desde ${formatPrice(lowest)}`,
        });
    }

    if (salon.address) {
        facts.push({
            key: 'address',
            icon: <MapPin aria-hidden />,
            text: salon.address,
            href: salon.location?.directionsUrl ?? '#ubicacion',
        });
    }

    return facts;
}

type Props = {
    facts: Fact[];
    /** A row of chips that scrolls sideways on phones, a list from `lg`. */
    chipsOnMobile: boolean;
};

export function QuickFacts({ facts, chipsOnMobile }: Props) {
    if (facts.length === 0) {
        return null;
    }

    return (
        <ul
            className={cn(
                'flex gap-2 text-sm lg:flex-col lg:gap-3 lg:border-t lg:pt-4',
                chipsOnMobile
                    ? 'max-lg:-mx-4 max-lg:overflow-x-auto max-lg:px-4 max-lg:pb-1'
                    : 'flex-col border-t pt-4',
            )}
        >
            {facts.map((fact) => {
                const content = (
                    <>
                        <span className="text-brand-text [&_svg]:size-4.5">
                            {fact.icon}
                        </span>
                        <span className="min-w-0 truncate">{fact.text}</span>
                    </>
                );
                const itemClass = cn(
                    'flex items-center gap-2.5 text-muted-foreground',
                    chipsOnMobile &&
                        'max-lg:h-10 max-lg:shrink-0 max-lg:rounded-full max-lg:px-4 max-lg:font-medium max-lg:skin-chip max-lg:text-foreground',
                );

                return (
                    <li
                        key={fact.key}
                        className={cn(
                            'min-w-0',
                            chipsOnMobile && 'max-lg:shrink-0',
                        )}
                    >
                        {fact.href ? (
                            <a
                                href={fact.href}
                                {...(fact.href.startsWith('http') && {
                                    target: '_blank',
                                    rel: 'noopener noreferrer',
                                })}
                                className={cn(
                                    itemClass,
                                    'hover:text-foreground',
                                )}
                            >
                                {content}
                            </a>
                        ) : (
                            <p className={itemClass}>{content}</p>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
