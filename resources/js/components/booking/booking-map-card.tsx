import { MapPin } from 'lucide-react';
import { coordinatesFromDirections, mapEmbedUrl } from '@/lib/maps';
import { cn } from '@/lib/utils';
import type { ClientBooking } from './types';

type Props = { salon: ClientBooking['salon']; className?: string };

/** Where the salon is: the real map (light, framed) and its address. */
export function BookingMapCard({ salon, className }: Props) {
    // The booking payload carries the "Cómo llegar" link, not the coordinates.
    const coordinates = salon.directionsUrl
        ? coordinatesFromDirections(salon.directionsUrl)
        : null;

    if (!coordinates && !salon.address) {
        return null;
    }

    return (
        <section
            aria-label="Ubicación del salón"
            className={cn('overflow-hidden rounded-xl skin-card', className)}
        >
            {coordinates && (
                <div className="p-2 pb-0">
                    <div className="photo-frame aspect-16/10 rounded-2xl">
                        <iframe
                            title="Mapa con la ubicación del salón"
                            src={mapEmbedUrl(coordinates)}
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            className="size-full border-0"
                        />
                    </div>
                </div>
            )}
            {salon.address && (
                <p className="flex items-start gap-2.5 p-4 sm:p-5">
                    <MapPin
                        className="mt-0.5 size-5 shrink-0 text-brand-text"
                        aria-hidden
                    />
                    <span className="min-w-0 font-medium break-words">
                        {salon.address}
                    </span>
                </p>
            )}
        </section>
    );
}
