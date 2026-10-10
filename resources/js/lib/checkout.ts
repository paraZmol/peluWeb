import { formatCents } from './format';
import { toCents } from './money';

/** The server takes at most this many methods for one visit. */
export const MAX_PAYMENT_PARTS = 3;

/** One method and what was typed as received with it (soles, as text). */
export type PaymentPart = { method: string; amount: string };

export type CheckoutInput = {
    /** Price minus the deposit already paid. */
    balanceCents: number;
    discount: string;
    tip: string;
    parts: PaymentPart[];
    /** The last part takes whatever the others leave. */
    lastIsAuto: boolean;
};

export type CheckoutState = {
    discountCents: number;
    tipCents: number;
    /** balance − discount + tip: what the client hands over now. */
    totalCents: number;
    /** What each part adds; 0 for a part whose text is not an amount. */
    partCents: number[];
    /** Why it cannot be charged yet, in words for the screen. */
    problem: string | null;
};

const BAD_AMOUNT = 'Revisa los montos: usa números como 20 o 20.50.';

/**
 * Everything the checkout shows, from what was typed. Whole céntimos only;
 * the server checks the same sums again under the booking's lock.
 */
export function resolveCheckout(input: CheckoutInput): CheckoutState {
    const discountCents = toCents(input.discount);
    const tipCents = toCents(input.tip);
    const totalCents =
        input.balanceCents - (discountCents ?? 0) + (tipCents ?? 0);
    const typed = input.parts.map((part) => toCents(part.amount));
    const last = typed.length - 1;
    const isSingle = typed.length === 1;
    const others = typed
        .slice(0, last)
        .reduce<number>((sum, cents) => sum + (cents ?? 0), 0);
    const partCents = typed.map((cents, index) =>
        index === last && (isSingle || input.lastIsAuto)
            ? Math.max(0, totalCents - others)
            : (cents ?? 0),
    );
    const hasBadAmount =
        discountCents === null ||
        tipCents === null ||
        typed.some(
            (cents, index) =>
                cents === null &&
                !(index === last && (isSingle || input.lastIsAuto)),
        );
    const difference =
        partCents.reduce((sum, cents) => sum + cents, 0) - totalCents;

    return {
        discountCents: discountCents ?? 0,
        tipCents: tipCents ?? 0,
        totalCents,
        partCents,
        problem: problemOf({
            hasBadAmount,
            discountOver: (discountCents ?? 0) > input.balanceCents,
            balanceCents: input.balanceCents,
            difference,
            hasEmptyPart: !isSingle && totalCents > 0 && partCents.includes(0),
        }),
    };
}

type Findings = {
    hasBadAmount: boolean;
    discountOver: boolean;
    balanceCents: number;
    difference: number;
    hasEmptyPart: boolean;
};

function problemOf(findings: Findings): string | null {
    if (findings.hasBadAmount) {
        return BAD_AMOUNT;
    }

    if (findings.discountOver) {
        return `El descuento no puede pasar de ${formatCents(findings.balanceCents)}.`;
    }

    if (findings.difference < 0) {
        return `Faltan ${formatCents(-findings.difference)} para llegar al total.`;
    }

    if (findings.difference > 0) {
        return `Sobran ${formatCents(findings.difference)}: los montos pasan del total.`;
    }

    if (findings.hasEmptyPart) {
        return 'Escribe cuánto se paga con cada método o quita el que no uses.';
    }

    return null;
}
