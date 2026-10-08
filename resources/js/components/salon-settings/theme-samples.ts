import type { MiniSalon, MiniService } from '@/components/theme-preview/types';
import type { Option } from '@/types';
import type { SalonSettings } from './types';

/**
 * Example services for the template thumbnails, by kind of business. This
 * screen does not load the salon's catalog, and a thumbnail only needs to
 * look like the salon's page, not to list its real prices.
 */
const SAMPLE_SERVICES: Record<string, MiniService[]> = {
    barbershop: [
        { name: 'Corte clásico', minutes: 30, price: '30.00' },
        { name: 'Perfilado de barba', minutes: 20, price: '20.00' },
        { name: 'Corte y barba', minutes: 50, price: '45.00' },
        { name: 'Afeitado con toalla caliente', minutes: 30, price: '30.00' },
    ],
    beauty_salon: [
        { name: 'Corte y peinado', minutes: 45, price: '45.00' },
        { name: 'Tinte completo', minutes: 90, price: '120.00' },
        { name: 'Brushing', minutes: 40, price: '35.00' },
        { name: 'Mechas balayage', minutes: 150, price: '220.00' },
    ],
    unisex: [
        { name: 'Corte', minutes: 30, price: '30.00' },
        { name: 'Tinte completo', minutes: 90, price: '120.00' },
        { name: 'Peinado', minutes: 40, price: '35.00' },
        { name: 'Perfilado de barba', minutes: 20, price: '20.00' },
    ],
    nails: [
        { name: 'Manicure en gel', minutes: 60, price: '55.00' },
        { name: 'Pedicure spa', minutes: 60, price: '60.00' },
        { name: 'Uñas acrílicas', minutes: 90, price: '90.00' },
        { name: 'Esmaltado tradicional', minutes: 30, price: '25.00' },
    ],
    spa: [
        { name: 'Limpieza facial', minutes: 60, price: '80.00' },
        { name: 'Masaje relajante', minutes: 60, price: '90.00' },
        { name: 'Hidratación capilar', minutes: 45, price: '60.00' },
        { name: 'Depilación de cejas', minutes: 20, price: '20.00' },
    ],
};

/** The salon as its template thumbnails show it: its name, photos and logo. */
export function miniSalonFrom(
    salon: SalonSettings,
    businessTypes: Option[],
): MiniSalon {
    const typeLabel =
        businessTypes.find((type) => type.value === salon.businessType)
            ?.label ?? '';
    const photoUrls = salon.photos.map((photo) => photo.url);

    return {
        name: salon.name,
        subtitle: [typeLabel, salon.district].filter(Boolean).join(' · '),
        coverUrl: salon.coverUrl ?? photoUrls[0] ?? null,
        logoUrl: salon.logoUrl,
        photoUrls,
        services: SAMPLE_SERVICES[salon.businessType] ?? SAMPLE_SERVICES.unisex,
    };
}
