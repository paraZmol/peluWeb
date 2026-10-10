import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { UNCATEGORIZED } from '@/lib/expenses';
import type { ExpenseCategory } from './types';

/** Radix does not take an empty value for an item. */
const NO_CATEGORY = 'none';

type Props = {
    id: string;
    categories: ExpenseCategory[];
    /** The category's id as text; empty for none. */
    value: string;
    onChange: (category: string) => void;
};

/** One of the salon's categories, or none. */
export function CategorySelect({ id, categories, value, onChange }: Props) {
    return (
        <Select
            value={value === '' ? NO_CATEGORY : value}
            onValueChange={(chosen) =>
                onChange(chosen === NO_CATEGORY ? '' : chosen)
            }
        >
            <SelectTrigger id={id} className="w-full data-[size=default]:h-11">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NO_CATEGORY}>{UNCATEGORIZED}</SelectItem>
                {categories.map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                        {category.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
