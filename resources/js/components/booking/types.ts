import type { BrandTokens } from '@/types';

export type BookableVariant = {
    id: number;
    label: string;
    price: string;
    extraMinutes: number;
};

export type BookableService = {
    id: number;
    name: string;
    description: string | null;
    minutes: number;
    price: string;
    isAddon: boolean;
    requiresEvaluation: boolean;
    /** Professionals who perform it. */
    staffIds: number[];
    variants: BookableVariant[];
};

export type BookableStaff = {
    id: number;
    name: string;
    title: string | null;
    photoUrl: string | null;
};

export type BookableSalon = {
    name: string;
    slug: string;
    logoUrl: string | null;
    brand: BrandTokens | null;
    staff: BookableStaff[];
    serviceGroups: { label: string; services: BookableService[] }[];
};

export type BookingDay = {
    /** "Y-m-d" on the salon's clock. */
    date: string;
    closed: boolean;
};

export type Viewer = {
    state: 'guest' | 'unverified' | 'ready';
    name: string | null;
};

export type Selection = { serviceId: number; variantId: number | null };

export type Slot = {
    /** Unix seconds (UTC): sent back as-is when booking. */
    start: number;
    /** "HH:MM" on the salon's clock. */
    time: string;
};

export type Quote = {
    price: string;
    priceVaries: boolean;
    minutes: number;
    minutesVary: boolean;
    depositPercent: number;
    deposit: string;
};

export type Availability = { slots: Slot[]; quote: Quote | null };

export type BookingStatus =
    | 'pending'
    | 'confirmed'
    | 'in_service'
    | 'completed'
    | 'cancelled'
    | 'no_show'
    | 'expired';

export type ClientBooking = {
    code: string;
    status: BookingStatus;
    statusLabel: string;
    date: string;
    time: string;
    endTime: string;
    isUpcoming: boolean;
    isVip: boolean;
    staffName: string;
    services: { name: string; price: string }[];
    price: string;
    note: string | null;
    deposit: {
        amount: string;
        isPaid: boolean;
        expiresAt: string | null;
        instructions: string | null;
        whatsappUrl: string | null;
        isRefundable: boolean | null;
    } | null;
    canCancel: boolean;
    cancelForfeitsDeposit: boolean;
    cancelWindowHours: number;
    salon: {
        name: string;
        slug: string;
        brand: BrandTokens | null;
        address: string | null;
        directionsUrl: string | null;
        whatsappUrl: string | null;
    };
};

export type AgendaAction =
    | 'confirm'
    | 'start'
    | 'complete'
    | 'noShow'
    | 'cancel';

export type AgendaBooking = {
    id: number;
    code: string;
    time: string;
    endTime: string;
    status: BookingStatus;
    statusLabel: string;
    isVip: boolean;
    client: {
        name: string;
        phone: string | null;
        whatsappUrl: string | null;
        noShows: number;
    };
    staffName: string;
    services: string[];
    price: string;
    deposit: { amount: string; isPaid: boolean } | null;
    note: string | null;
    actions: AgendaAction[];
};
