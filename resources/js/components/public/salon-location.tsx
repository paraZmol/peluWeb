import { MapPin } from 'lucide-react';
import { mapEmbedUrl } from '@/lib/maps';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { SectionHeading } from './section-heading';
import type { PublicLocation } from './types';

type Props = {
    address: string | null;
    location: PublicLocation | null;
};

/**
 * The real Google map in a framed card (light even in dark templates: the
 * free embed cannot be styled), the address and the way there.
 */
export function SalonLocation({ address, location }: Props) {
    return (
        <section
            id="ubicacion"
            aria-labelledby="ubicacion-titulo"
            className="flex scroll-mt-6 flex-col gap-6"
        >
            <SectionHeading id="ubicacion-titulo" title="Cómo llegar" compact />
            <div className="overflow-hidden rounded-xl skin-card">
                {location && (
                    <div className="p-2 pb-0">
                        <div className="photo-frame aspect-16/10 rounded-2xl">
                            <iframe
                                title="Mapa con la ubicación del salón"
                                src={mapEmbedUrl(location)}
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                className="size-full border-0"
                            />
                        </div>
                    </div>
                )}
                <div className="flex flex-col gap-4 p-4 sm:p-5">
                    {address && (
                        <p className="flex items-start gap-2.5">
                            <MapPin
                                className="mt-0.5 size-5 shrink-0 text-brand-text"
                                aria-hidden
                            />
                            <span className="min-w-0 font-medium break-words">
                                {address}
                            </span>
                        </p>
                    )}
                    {location && (
                        <a
                            href={location.directionsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn(CHIP_ACTION, 'w-full')}
                        >
                            <MapPin aria-hidden />
                            Abrir en Google Maps
                        </a>
                    )}
                </div>
            </div>
        </section>
    );
}
