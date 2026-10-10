import { groupByDay } from '@/lib/expenses';
import { formatCents, formatDate } from '@/lib/format';
import { ExpenseRow } from './expense-row';
import type { Expense } from './types';

type Props = { expenses: Expense[]; onEdit: (expense: Expense) => void };

/** The month's expenses, newest day first, each day with its own total. */
export function ExpenseList({ expenses, onEdit }: Props) {
    return (
        <div className="flex flex-col gap-5">
            {groupByDay(expenses).map((day) => (
                <section key={day.date} className="flex flex-col gap-2">
                    <div className="flex items-baseline justify-between gap-3 px-1">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {formatDate(day.date)}
                        </h2>
                        {day.expenses.length > 1 && (
                            <p className="text-sm text-muted-foreground tabular-nums">
                                {formatCents(day.cents)}
                            </p>
                        )}
                    </div>
                    <ul className="divide-y rounded-xl border bg-card">
                        {day.expenses.map((expense) => (
                            <ExpenseRow
                                key={expense.id}
                                expense={expense}
                                onEdit={onEdit}
                            />
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
