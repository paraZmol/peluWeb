import type { MyReview } from '@/components/reviews/types';
import type { BrandTokens, SalonTheme } from '@/types';

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
    theme: SalonTheme;
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

/** Services and professional of an earlier booking, to start the wizard from. */
export type RepeatBooking = {
    selections: { serviceId: number; variantId: number | null }[];
    staffId: number | null;
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

export type StaffOption = { id: number; name: string };

/** A client already in the salon's list, offered while the team types. */
export type ClientSuggestion = {
    id: number;
    name: string;
    phone: string | null;
    isVip: boolean;
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
    referencePhotoUrl: string | null;
    canAttachPhoto: boolean;
    canCancel: boolean;
    canMove: boolean;
    cancelForfeitsDeposit: boolean;
    cancelWindowHours: number;
    /** What they wrote about this visit, if anything. */
    review: MyReview | null;
    /** They took their review back: the visit cannot be reviewed again. */
    reviewWithdrawn: boolean;
    /** A completed visit, while the salon takes reviews and the time lasts. */
    canReview: boolean;
    /** The salon shows its reviews: one left now carries the first name. */
    reviewIsPublic: boolean;
    salon: {
        name: string;
        slug: string;
        brand: BrandTokens | null;
        theme: SalonTheme;
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
    | 'move'
    | 'cancel'
    | 'refundDeposit';

export type AgendaBooking = {
    id: number;
    code: string;
    /** "Y-m-d" on the salon's clock. */
    date: string;
    time: string;
    endTime: string;
    /** ISO instants, for countdowns. */
    startsAt: string;
    endsAt: string;
    status: BookingStatus;
    statusLabel: string;
    isVip: boolean;
    client: {
        id: number;
        name: string;
        phone: string | null;
        whatsappUrl: string | null;
        noShows: number;
    };
    staffId: number;
    staffName: string;
    services: string[];
    price: string;
    deposit: { amount: string; isPaid: boolean; isRefunded: boolean } | null;
    /** Price minus the deposit already paid: what is left to collect. */
    balance: string;
    depositPaid: string;
    /** Set once completed. `total` is price − discount. */
    charged: { total: string; discount: string } | null;
    /** Taking money off the price needs the cash permission. */
    canDiscount: boolean;
    note: string | null;
    referencePhotoUrl: string | null;
    actions: AgendaAction[];
};
