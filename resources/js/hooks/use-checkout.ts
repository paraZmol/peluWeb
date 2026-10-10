import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { PaymentPart } from '@/lib/checkout';
import { MAX_PAYMENT_PARTS, resolveCheckout } from '@/lib/checkout';
import { fromCents, toCents } from '@/lib/money';
import type { Option } from '@/types';

/** How most visits are paid at the counter. */
const DEFAULT_METHOD = 'cash';

/** The methods a payment can be recorded with; the agenda page sends them. */
export function usePaymentMethods(): Option[] {
    return usePage<{ paymentMethods?: Option[] }>().props.paymentMethods ?? [];
}

/**
 * What is being typed in the checkout of one visit and what follows from
 * it. `balance` is the price minus the deposit already paid, in soles.
 */
export function useCheckout(balance: string, methods: Option[]) {
    const [discount, setDiscount] = useState('');
    const [parts, setParts] = useState<PaymentPart[]>([
        { method: DEFAULT_METHOD, amount: '' },
    ]);
    const [lastIsAuto, setLastIsAuto] = useState(true);
    const state = resolveCheckout({
        balanceCents: toCents(balance) ?? 0,
        discount,
        parts,
        lastIsAuto,
    });
    const last = parts.length - 1;
    const unused = methods.filter(
        (option) => !parts.some((part) => part.method === option.value),
    );
    const update = (index: number, change: Partial<PaymentPart>) =>
        setParts(
            parts.map((part, at) =>
                at === index ? { ...part, ...change } : part,
            ),
        );
    const append = (from: PaymentPart[]) => {
        setParts([...from, { method: unused[0].value, amount: '' }]);
        setLastIsAuto(true);
    };

    return {
        ...state,
        discount,
        setDiscount,
        isSplit: parts.length > 1,
        canAddPart: parts.length < MAX_PAYMENT_PARTS && unused.length > 0,
        /** Rows as shown: the automatic one displays what it was given. */
        rows: parts.map((part, index) => ({
            method: part.method,
            amount:
                index === last && lastIsAuto
                    ? fromCents(state.partCents[index])
                    : part.amount,
            /** Its own method plus the ones no other row took. */
            options: methods.filter(
                (option) =>
                    option.value === part.method || unused.includes(option),
            ),
        })),
        setMethod: (index: number, method: string) => update(index, { method }),
        setAmount: (index: number, amount: string) => {
            update(index, { amount });
            setLastIsAuto(index !== last);
        },
        /** The first row waits for its amount; the new one takes the rest. */
        split: () => append([{ ...parts[0], amount: '' }]),
        /** The row that was filling itself keeps what it showed. */
        addPart: () =>
            append(
                parts.map((part, index) =>
                    index === last && state.partCents[index] > 0
                        ? { ...part, amount: fromCents(state.partCents[index]) }
                        : part,
                ),
            ),
        removePart: (index: number) => {
            setParts(parts.filter((_, at) => at !== index));
            setLastIsAuto(true);
        },
        /** What the server expects; nothing to pay means no payments. */
        payload: () => ({
            discount: fromCents(state.discountCents),
            payments:
                state.totalCents === 0
                    ? []
                    : parts.map((part, index) => ({
                          method: part.method,
                          amount: fromCents(state.partCents[index]),
                      })),
        }),
    };
}
