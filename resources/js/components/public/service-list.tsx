import { MessageCircle, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useSalonTheme } from '@/components/salon/brand-theme';
import type { ServiceOffer } from '@/lib/promo-offers';
import { PRIMARY_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { SectionHeading } from './section-heading';
import { ServiceGroup } from './service-group';
import type { PublicPhoto, PublicServiceGroup } from './types';

type Props = {
    groups: PublicServiceGroup[];
    bookingUrl: string | null;
    whatsappUrl: string | null;
    /** The salon's photos, lent to the first card of each group (photo-card). */
    photos: PublicPhoto[];
    /** By service: its price with the best promotion that lowers it. */
    offers?: Map<number, ServiceOffer>;
};

export function ServiceList({
    groups,
    bookingUrl,
    whatsappUrl,
    photos,
    offers,
}: Props) {
    const { layout } = useSalonTheme();
    const [filter, setFilter] = useState<string | null>(null);
    const visibleGroups = groups.filter((group) => group.services.length > 0);
    const total = visibleGroups.reduce(
        (count, group) => count + group.services.length,
        0,
    );
    // Photo cards take room: there, the groups are filtered with chips.
    const hasFilter =
        layout.serviceRow === 'photo-card' && visibleGroups.length > 1;
    const shownGroups =
        hasFilter && filter !== null
            ? visibleGroups.filter((group) => group.label === filter)
            : visibleGroups;
    const photoFor = (group: PublicServiceGroup): string | null =>
        photos.length === 0
            ? null
            : photos[visibleGroups.indexOf(group) % photos.length].url;

    return (
        <section
            aria-labelledby="servicios-titulo"
            className="flex flex-col gap-6"
        >
            <SectionHeading
                id="servicios-titulo"
                eyebrow="La carta"
                title="Servicios"
                aside={
                    total > 0
                        ? `${total} ${total === 1 ? 'servicio' : 'servicios'}`
                        : undefined
                }
            />

            {visibleGroups.length === 0 && (
                <div className="flex flex-col items-center gap-3 rounded-xl p-8 text-center skin-card">
                    <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand-text">
                        <Sparkles className="size-6" aria-hidden />
                    </span>
                    <p className="font-semibold">
                        Pronto verás aquí los servicios
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Mientras tanto, escríbenos y te contamos qué ofrecemos.
                    </p>
                    {whatsappUrl && (
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn(PRIMARY_ACTION, 'mt-2')}
                        >
                            <MessageCircle aria-hidden /> Escribir por WhatsApp
                        </a>
                    )}
                </div>
            )}

            {hasFilter && (
                <div
                    role="group"
                    aria-label="Filtrar servicios"
                    className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:px-0"
                >
                    {[null, ...visibleGroups.map((group) => group.label)].map(
                        (label) => (
                            <button
                                key={label ?? 'todos'}
                                type="button"
                                aria-pressed={filter === label}
                                onClick={() => setFilter(label)}
                                className={cn(
                                    'h-10 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring',
                                    filter === label
                                        ? 'bg-foreground text-background'
                                        : 'skin-chip',
                                )}
                            >
                                {label ?? 'Todos'}
                            </button>
                        ),
                    )}
                </div>
            )}

            {shownGroups.map((group) => (
                <ServiceGroup
                    key={group.label}
                    group={group}
                    bookingUrl={bookingUrl}
                    photoUrl={photoFor(group)}
                    offers={offers}
                />
            ))}
        </section>
    );
}
