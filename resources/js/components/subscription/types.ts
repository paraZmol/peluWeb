export type SubscriptionState =
    | 'trial'
    | 'active'
    | 'grace'
    | 'read_only'
    | 'suspended';

export type SubscriptionPlan = {
    code: string;
    name: string;
    maxStaff: number;
    /** What this salon would pay per month (its founder price if it gets one). */
    monthlyCents: number;
    /** Twelve months for the price of ten. */
    yearCents: number;
    normalCents: number;
    isCurrent: boolean;
};

export type SubscriptionPayment = {
    id: number;
    paidOn: string | null;
    planName: string;
    months: number;
    amountCents: number;
    method: string;
    coversFrom: string;
    coversUntil: string;
};

export type Subscription = {
    state: SubscriptionState;
    stateLabel: string;
    coveredUntil: string | null;
    /** Days to the last covered day: 0 that day, negative after. */
    daysLeft: number | null;
    graceEndsOn: string | null;
    plan: { code: string; name: string; maxStaff: number };
    withAgenda: number;
    isFounder: boolean;
    plans: SubscriptionPlan[];
    offersYear: boolean;
    payTo: { yape: string | null; name: string | null };
    payments: SubscriptionPayment[];
};
