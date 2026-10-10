import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Option } from '@/types';
import { AmountInput } from './amount-input';

type Row = { method: string; amount: string; options: Option[] };

type Props = {
    rows: Row[];
    canAdd: boolean;
    onMethod: (index: number, method: string) => void;
    onAmount: (index: number, amount: string) => void;
    onAdd: () => void;
    onRemove: (index: number) => void;
};

const ORDINALS = ['Primer', 'Segundo', 'Tercer'];

/** A visit paid with several methods: one row per method and its amount. */
export function SplitPayments({
    rows,
    canAdd,
    onMethod,
    onAmount,
    onAdd,
    onRemove,
}: Props) {
    const labelOf = (row: Row) =>
        row.options.find((option) => option.value === row.method)?.label ??
        row.method;

    return (
        <fieldset className="flex min-w-0 flex-col gap-2">
            <legend className="mb-2 text-sm font-medium">
                ¿Cómo se reparte el pago?
            </legend>
            {rows.map((row, index) => (
                <div
                    // By position: keyed by method, changing it would remount
                    // the row and drop the keyboard focus.
                    key={index}
                    className="grid grid-cols-[minmax(0,1fr)_7.5rem_2.75rem] gap-2"
                >
                    <Select
                        value={row.method}
                        onValueChange={(method) => onMethod(index, method)}
                    >
                        <SelectTrigger
                            aria-label={`${ORDINALS[index]} método de pago`}
                            className="w-full data-[size=default]:h-11"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {row.options.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <AmountInput
                        aria-label={`Monto en ${labelOf(row)}`}
                        value={row.amount}
                        onChange={(amount) => onAmount(index, amount)}
                        autoFocus={index === 0 && row.amount === ''}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-11 md:size-11"
                        aria-label={`Quitar ${labelOf(row)}`}
                        onClick={() => onRemove(index)}
                    >
                        <X aria-hidden />
                    </Button>
                </div>
            ))}
            {canAdd && (
                <Button
                    type="button"
                    variant="ghost"
                    className="h-11 self-start md:h-11"
                    onClick={onAdd}
                >
                    <Plus aria-hidden /> Agregar otro método
                </Button>
            )}
        </fieldset>
    );
}
