export type Coordinates = { latitude: number; longitude: number };

/**
 * Google Maps embed without an API key (plantillas.md §6): it cannot be
 * styled, so dark templates show it light inside a framed card.
 */
export function mapEmbedUrl({ latitude, longitude }: Coordinates): string {
    const query = new URLSearchParams({
        q: `${latitude},${longitude}`,
        z: '16',
        hl: 'es',
        output: 'embed',
    });

    return `https://maps.google.com/maps?${query.toString()}`;
}

/**
 * The coordinates inside a "Cómo llegar" link built by the server
 * (`…/maps/dir/?api=1&destination=lat,lng`), for payloads that only carry
 * the link. Null when it is not one of those links.
 */
export function coordinatesFromDirections(url: string): Coordinates | null {
    try {
        const destination = new URL(url).searchParams.get('destination');
        const [latitude, longitude] = (destination ?? '')
            .split(',')
            .map(Number);

        return Number.isFinite(latitude) &&
            Number.isFinite(longitude) &&
            destination
            ? { latitude, longitude }
            : null;
    } catch {
        return null;
    }
}
