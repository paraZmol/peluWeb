import type { BrandTokens, SalonTheme } from '@/types';

export type PublicServiceVariant = {
    label: string;
    price: string;
};

export type PublicService = {
    id: number;
    name: string;
    description: string | null;
    minutes: number;
    priceFrom: string;
    hasVariants: boolean;
    requiresEvaluation: boolean;
    whatsappUrl: string | null;
    variants: PublicServiceVariant[];
};

export type PublicServiceGroup = {
    label: string;
    services: PublicService[];
};

export type PublicPhoto = {
    url: string;
    caption: string | null;
};

export type PublicLocation = {
    latitude: number;
    longitude: number;
    directionsUrl: string;
};

export type PublicOpeningHours = {
    days: { weekday: number; ranges: { start: string; end: string }[] }[];
    /** ISO weekday on the salon's clock. */
    today: number;
    /** Name of today's holiday, if the salon is closed for one. */
    closedToday: string | null;
    status: {
        isOpen: boolean;
        /** "HH:MM": closing time when open, next opening when closed. */
        time: string | null;
        daysAhead: number | null;
        weekday: number | null;
    };
};

/** Who attends, as clients see them in "Quién te atiende". */
export type PublicTeamMember = {
    name: string;
    title: string | null;
    photoUrl: string | null;
};

export type PublicSalon = {
    name: string;
    slug: string;
    businessType: string;
    clientele: string;
    description: string | null;
    logoUrl: string | null;
    coverUrl: string | null;
    brand: BrandTokens | null;
    theme: SalonTheme;
    /** Its manager is looking at another template (`?diseno=`). */
    isPreview: boolean;
    /** Some photos are samples standing in for the salon's own (preview only). */
    usesSamplePhotos: boolean;
    team: PublicTeamMember[];
    whatsappUrl: string | null;
    /** Online booking wizard; null when the salon cannot take bookings yet. */
    bookingUrl: string | null;
    instagramUrl: string | null;
    address: string | null;
    location: PublicLocation | null;
    photos: PublicPhoto[];
    openingHours: PublicOpeningHours | null;
    serviceGroups: PublicServiceGroup[];
};
