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
    /** The salon's plan ran out: online booking is paused, WhatsApp still works. */
    bookingsPaused: boolean;
    instagramUrl: string | null;
    address: string | null;
    location: PublicLocation | null;
    photos: PublicPhoto[];
    openingHours: PublicOpeningHours | null;
    serviceGroups: PublicServiceGroup[];
};

/** What one included service costs with a promotion, from its catalogue price. */
export type PromotionPrice = {
    serviceId: number;
    name: string;
    normal: string;
    promo: string;
    /** It has options: both prices are the lowest ones ("desde"). */
    isFrom: boolean;
};

/** A promotion the salon is running today, as visitors read it. */
export type PublicPromotion = {
    id: number;
    name: string;
    /**
     * What it does, in the system's words ("20 % menos en Corte"), under a
     * title the salon typed; null when the title already says it.
     */
    summary: string | null;
    /** The campaign it belongs to ("Halloween"), above the title. */
    overline: string | null;
    mechanic: 'discount' | 'gift' | 'combo' | 'pair';
    /** "Y-m-d": the last day a visit can use it. */
    endsOn: string;
    /** "del 5 al 18 de octubre de 2026": the visits it is valid for. */
    period: string;
    /** Bookings it takes; null without a limit. */
    quota: number | null;
    /** "lunes y martes, de 10:00 a 16:00"; null when it holds at any time. */
    when: string | null;
    finePrint: string;
    /**
     * Discount: the services it lowers. Gift: the ones that earn it. Combo:
     * the ones that must all be booked.
     */
    serviceIds: number[];
    prices: PromotionPrice[];
    gift: {
        serviceId: number;
        name: string;
        price: string;
        minutes: number;
    } | null;
    /**
     * A combo: its services together and apart ("desde" when an option or
     * a professional may change it). Its `prices` hold only all together.
     */
    combo: PromotionCombo | null;
    /**
     * "Ven con alguien": what the second one gets off (50 or 100). Until the
     * server sends it, read from the system's title (`pairPercent`).
     */
    pair?: { percent: number } | null;
    /**
     * Only with these professionals, when chosen; empty = all of the team.
     * With "Quien esté disponible" it does not hold.
     */
    staff?: { id: number; name: string }[];
};

export type PromotionCombo = {
    price: string;
    normal: string;
    saves: string;
    isFrom: boolean;
};

export type PublicPromotions = {
    items: PublicPromotion[];
    /**
     * Set when the visitor came through a promotion's own link. `hasEnded`:
     * it is not on offer (over, paused, out of places or never was).
     */
    shared: { id: number | null; hasEnded: boolean } | null;
};
