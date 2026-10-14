import { Wallet } from 'lucide-react';
import { formatPrice } from '@/lib/format';

type Props = {
    /** Soles as text, on the total with its promotion. */
    amount: string;
    percent: number;
};

/** Before confirming: the deposit the salon asks, and that how comes later. */
export function DepositNotice({ amount, percent }: Props) {
    return (
        <p className="flex items-start gap-3 rounded-xl border border-brand-line bg-brand-soft p-4 text-sm">
            <Wallet
                className="mt-0.5 size-5 shrink-0 text-brand-text"
                aria-hidden
            />
            <span>
                Para confirmar, el salón pide una seña de{' '}
                <strong className="whitespace-nowrap tabular-nums">
                    {formatPrice(amount)}
                </strong>{' '}
                ({percent}%). Te diremos cómo enviarla al terminar.
            </span>
        </p>
    );
}
