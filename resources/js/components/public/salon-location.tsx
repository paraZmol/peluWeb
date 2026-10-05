import { MapPin, Navigation } from 'lucide-react';
import { SalonMap } from '@/components/map/salon-map';
import { Button } from '@/components/ui/button';
import type { PublicLocation } from './types';

type Props = {
    address: string | null;
    location: PublicLocation | null;
};

export function SalonLocation({ address, location }: Props) {
    return (
        <div className="flex flex-col gap-4">
            <h2 className="font-display text-2xl font-semibold">Ubicación</h2>

            {address && (
                <p className="flex items-start gap-2 text-muted-foreground">
                    <MapPin
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden
                    />
                    <span className="min-w-0 break-words">{address}</span>
                </p>
            )}

            {location && (
                <>
                    {/* isolate keeps Leaflet's high z-index panes under the sticky bar */}
                    <div
                        className="isolate"
                        role="img"
                        aria-label="Mapa con la ubicación del salón"
                    >
                        <SalonMap
                            position={{
                                latitude: location.latitude,
                                longitude: location.longitude,
                            }}
                            className="h-56 w-full rounded-xl"
                        />
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 self-start rounded-full md:h-11"
                    >
                        <a
                            href={location.directionsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Navigation aria-hidden />
                            Cómo llegar
                            <span className="sr-only"> (abre Google Maps)</span>
                        </a>
                    </Button>
                </>
            )}
        </div>
    );
}
