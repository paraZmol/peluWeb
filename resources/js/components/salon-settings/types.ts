import type { BrandTokens, SalonTheme } from '@/types';

/** One option of "Diseño de tu página" (SalonSettingsController::edit). */
export type ThemeChoice = {
    value: SalonTheme;
    label: string;
    tagline: string;
    /** The public page with that template and sample photos where missing. */
    previewUrl: string;
};

/** Mirrors App\Http\Resources\SalonSettingsResource. */
export type BookingSettings = {
    acceptsOnlineBookings: boolean;
    walkInsEnabled: boolean;
    minLeadMinutes: number;
    maxDaysAhead: number;
    slotStepMinutes: number;
    cancelWindowHours: number;
    depositPercent: number;
    vipDepositPercent: number | null;
    holdMinutes: number;
    paymentInstructions: string | null;
};

/** Who provides the service and the salon's own book (requirement P7). */
export type SalonLegalSettings = {
    legalName: string | null;
    ruc: string | null;
    contactEmail: string | null;
    complaintsBookUrl: string | null;
};

export type SalonSettings = {
    booking: BookingSettings;
    name: string;
    slug: string;
    publicUrl: string;
    businessType: string;
    clientele: string;
    description: string | null;
    phone: string | null;
    instagram: string | null;
    legal: SalonLegalSettings;
    logoUrl: string | null;
    coverUrl: string | null;
    brandColor: string | null;
    brand: BrandTokens | null;
    theme: SalonTheme;
    address: string | null;
    district: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    photos: { id: number; url: string; caption: string | null }[];
};
