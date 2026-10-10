import { AmountInput } from '@/components/accounting/amount-input';
import InputError from '@/components/input-error';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ServiceRuleDraft } from '@/hooks/use-commission-rules-form';
import type { RuleMode } from '@/lib/commissions';
import { PercentInput } from './percent-input';

const MODES: { value: RuleMode; label: string }[] = [
    { value: 'general', label: 'Usa el general' },
    { value: 'percent', label: 'Otro porcentaje' },
    { value: 'amount', label: 'Monto fijo' },
];

type Props = {
    /** The service's name, as the catalog shows it. */
    name: string;
    rule: ServiceRuleDraft;
    onChange: (patch: Partial<ServiceRuleDraft>) => void;
    error?: string;
};

/** How one service pays: like the rest, its own percent, or a fixed amount. */
export function ServiceRuleField({ name, rule, onChange, error }: Props) {
    const errorId = `service-rule-${rule.id}-error`;
    const field = {
        'aria-invalid': Boolean(error),
        'aria-describedby': errorId,
    };

    return (
        <li className="grid grid-cols-[minmax(0,1fr)_7.5rem] gap-2 py-3 sm:grid-cols-[minmax(0,1fr)_11rem_7.5rem] sm:items-center">
            <p className="col-span-2 font-medium break-words sm:col-span-1">
                {name}
            </p>
            <Select
                value={rule.mode}
                onValueChange={(mode) => onChange({ mode: mode as RuleMode })}
            >
                <SelectTrigger
                    aria-label={`Comisión de ${name}`}
                    className="h-11 w-full"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {MODES.map((mode) => (
                        <SelectItem key={mode.value} value={mode.value}>
                            {mode.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {rule.mode === 'percent' && (
                <PercentInput
                    {...field}
                    aria-label={`Porcentaje de ${name}`}
                    value={rule.percent}
                    onChange={(percent) => onChange({ percent })}
                />
            )}
            {rule.mode === 'amount' && (
                <AmountInput
                    {...field}
                    aria-label={`Monto fijo de ${name}`}
                    value={rule.amount}
                    onChange={(amount) => onChange({ amount })}
                />
            )}
            <InputError
                id={errorId}
                message={error}
                className="col-span-full"
            />
        </li>
    );
}
