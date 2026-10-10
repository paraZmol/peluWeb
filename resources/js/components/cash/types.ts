/** What moved by one payment method while the session was open. */
export type CashMethod = {
    value: string;
    label: string;
    /** Soles as text ("55.00"). */
    in: string;
    out: string;
    /** In minus out, signed céntimos. */
    netCents: number;
};

export type CashSession = {
    id: number;
    /** On the salon's clock, "2026-10-07 08:45". */
    openedAt: string;
    /** Null while the session is open. */
    closedAt: string | null;
    /** The float it started with, soles as text ("50.00"). */
    openingCash: string;
    /** Signed soles as text: "92.70", or "-3.00" when more left than came. */
    expectedCash: string | null;
    countedCash: string | null;
    /** Counted minus expected, signed céntimos: negative = cash missing. */
    differenceCents: number | null;
    note: string | null;
    /** Only the open session carries them. */
    methods: CashMethod[];
};
