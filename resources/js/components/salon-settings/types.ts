import type { BrandTokens } from '@/types';

/** Mirrors App\Http\Resources\SalonSettingsResource. */
export type BookingSettings = {
    acceptsOnlineBookings: boolean;
    minLeadMinutes: number;
    maxDaysAhead: number;
    slotStepMinutes: number;
    cancelWindowHours: number;
    depositPercent: number;
    vipDepositPercent: number | null;
    holdMinutes: number;
    paymentInstructions: string | null;
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
    logoUrl: string | null;
    coverUrl: string | null;
    brandColor: string | null;
    brand: BrandTokens | null;
    address: string | null;
    district: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    photos: { id: number; url: string; caption: string | null }[];
};
