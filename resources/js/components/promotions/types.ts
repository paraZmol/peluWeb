/** The card a promotion starts from: it only shapes the form. */
export type PromotionCardKind = 'discount' | 'gift' | 'quiet_hours' | 'season';

export type PromotionMechanic = 'discount' | 'gift';

export type PromotionState =
    | 'draft'
    | 'scheduled'
    | 'active'
    | 'paused'
    | 'ended'
    | 'needs_review';

/** A service of the salon a promotion can be about. */
export type PromotionService = {
    id: number;
    name: string;
    /** Soles as text ("35.00"). */
    price: string;
    minutes: number;
    isAddon: boolean;
    /** One fixed price: the only kind that can be given away. */
    canBeGift: boolean;
};

export type PromotionResults = {
    bookings: number;
    completed: number;
    /** Soles as text. */
    charged: string;
    givenAway: string;
    placesUsed: number;
};

export type Promotion = {
    id: number;
    card: PromotionCardKind;
    mechanic: PromotionMechanic;
    name: string;
    overline: string | null;
    state: PromotionState;
    stateLabel: string;
    /** "2026-10-07", on the salon's calendar. */
    startsOn: string;
    endsOn: string;
    finePrint: string;
    /** "lunes, martes y miércoles, de 10:00 a 16:00"; null when always. */
    when: string | null;
    shareUrl: string;
    /** It has bookings: only its end, its places and its title may change. */
    isLocked: boolean;
    form: {
        services: number[];
        percent: number | null;
        amount: string | null;
        gift_service_id: number | null;
        weekdays: number[] | null;
        from_time: string | null;
        to_time: string | null;
        quota: number | null;
        once_per_client: boolean;
    };
    results: PromotionResults;
};

export type PromotionLimits = {
    minPercent: number;
    maxPercent: number;
    maxDays: number;
    maxActive: number;
};

/** What the form needs besides the promotion itself. */
export type PromotionContext = {
    services: PromotionService[];
    limits: PromotionLimits;
    /** Today on the salon's calendar. */
    today: string;
    depositPercent: number;
};

/** What is being typed. Numbers stay as text until they are sent. */
export type PromotionFields = {
    card: PromotionCardKind;
    mechanic: PromotionMechanic;
    services: number[];
    /** Which of the two the discount is written as. */
    benefit: 'percent' | 'amount';
    percent: string;
    amount: string;
    gift_service_id: number | null;
    starts_on: string;
    ends_on: string;
    weekdays: number[];
    from_time: string;
    to_time: string;
    quota: string;
    once_per_client: boolean;
    name: string;
    /** Key of the occasion picked (season card); empty for none. */
    occasion: string;
    overline: string;
};
