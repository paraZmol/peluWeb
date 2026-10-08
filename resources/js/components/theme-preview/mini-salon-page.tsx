import { MessageCircle } from 'lucide-react';
import { useSalonTheme } from '@/components/salon/brand-theme';
import { cn } from '@/lib/utils';
import { MiniHero } from './mini-hero';
import { MiniServices } from './mini-services';
import type { MiniSalon } from './types';

type Props = {
    salon: MiniSalon;
    className?: string;
};

/**
 * A salon's public page in miniature (cover, services, booking bar), laid
 * out by the template around it. Must sit inside a `BrandTheme`; it scales
 * with its own width, so the parent only fixes width and height. Purely
 * illustrative: hidden from assistive technology.
 */
export function MiniSalonPage({ salon, className }: Props) {
    const { layout } = useSalonTheme();

    return (
        <div
            aria-hidden
            className={cn(
                '@container pointer-events-none relative isolate overflow-hidden bg-background text-foreground select-none',
                className,
            )}
        >
            <MiniHero salon={salon} variant={layout.hero} />
            <MiniServices
                variant={layout.serviceRow}
                services={salon.services}
                featuredPhoto={salon.photoUrls[0] ?? salon.coverUrl}
            />
            <div className="absolute inset-x-0 bottom-0 z-20 flex items-center gap-[2.5cqi] px-[4cqi] py-[3cqi] skin-dock">
                <span className="flex size-[11cqi] shrink-0 items-center justify-center rounded-full skin-chip">
                    <MessageCircle className="size-[5cqi]" />
                </span>
                <span className="flex h-[11cqi] flex-1 items-center justify-center rounded-full skin-primary text-[3.8cqi] font-bold">
                    Reservar cita
                </span>
            </div>
        </div>
    );
}
