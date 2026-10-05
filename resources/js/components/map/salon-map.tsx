import 'leaflet/dist/leaflet.css';
import { divIcon, type LatLngExpression } from 'leaflet';
import { useEffect } from 'react';
import {
    MapContainer,
    Marker,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';

export type Coordinates = { latitude: number; longitude: number };

/** Lima centre, used when the salon has no location yet. */
export const DEFAULT_CENTER: Coordinates = {
    latitude: -12.0464,
    longitude: -77.0428,
};

/**
 * Pin drawn with CSS in the brand color: avoids Leaflet's default marker
 * images, which break once bundled.
 */
const pinIcon = divIcon({
    className: '',
    html: '<span style="display:block;width:28px;height:28px;border-radius:50% 50% 50% 0;background:var(--primary);border:3px solid var(--primary-foreground);transform:rotate(-45deg);box-shadow:0 2px 6px rgb(0 0 0 / .35)"></span>',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
});

type Props = {
    position: Coordinates | null;
    onChange?: (position: Coordinates) => void;
    className?: string;
    zoom?: number;
};

/**
 * OpenStreetMap map (free, no API key). Read-only unless `onChange` is
 * given: then a tap or a drag moves the pin.
 */
export function SalonMap({ position, onChange, className, zoom = 16 }: Props) {
    const center = position ?? DEFAULT_CENTER;
    const latLng: LatLngExpression = [center.latitude, center.longitude];
    const editable = onChange !== undefined;

    return (
        <MapContainer
            center={latLng}
            zoom={position ? zoom : 12}
            scrollWheelZoom={false}
            dragging={editable || undefined}
            className={className}
            attributionControl
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Recenter position={position} />
            {position && (
                <Marker
                    position={latLng}
                    icon={pinIcon}
                    draggable={editable}
                    eventHandlers={{
                        dragend: (event) => {
                            const { lat, lng } = event.target.getLatLng();
                            onChange?.({ latitude: lat, longitude: lng });
                        },
                    }}
                />
            )}
            {editable && <PickOnTap onPick={onChange} />}
        </MapContainer>
    );
}

function Recenter({ position }: { position: Coordinates | null }) {
    const map = useMap();

    useEffect(() => {
        if (position) {
            map.setView(
                [position.latitude, position.longitude],
                Math.max(map.getZoom(), 15),
            );
        }
    }, [map, position]);

    return null;
}

function PickOnTap({ onPick }: { onPick: (position: Coordinates) => void }) {
    useMapEvents({
        click: (event) =>
            onPick({ latitude: event.latlng.lat, longitude: event.latlng.lng }),
    });

    return null;
}

/**
 * Accepts the links people copy from Google Maps ("@-12.12,-77.03,17z",
 * "?q=-12.12,-77.03") or plain "lat, lng" text.
 */
export function parseCoordinates(text: string): Coordinates | null {
    const match = text.match(/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/);

    if (!match) {
        return null;
    }

    const latitude = Number(match[1]);
    const longitude = Number(match[2]);

    return Math.abs(latitude) <= 90 && Math.abs(longitude) <= 180
        ? { latitude, longitude }
        : null;
}
