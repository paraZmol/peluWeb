import { useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, update } from '@/routes/expenses/categories';
import { MAX_CATEGORY_NAME } from './category-limits';
import type { ExpenseCategory } from './types';

type Props = { category: ExpenseCategory };

/** A category that can be renamed in place or removed. */
export function CategoryRow({ category }: Props) {
    const [isRenaming, setIsRenaming] = useState(false);
    const form = useForm({ name: category.name });

    const stopRenaming = () => {
        form.setData('name', category.name);
        form.clearErrors();
        setIsRenaming(false);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(update(category.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setIsRenaming(false),
        });
    };

    if (!isRenaming) {
        return (
            <li className="flex items-center gap-1 py-1 pr-1 pl-4">
                <p className="min-w-0 flex-1 break-words">{category.name}</p>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Renombrar ${category.name}`}
                    onClick={() => setIsRenaming(true)}
                >
                    <Pencil aria-hidden />
                </Button>
                <ConfirmRemoveDialog
                    triggerLabel="Eliminar"
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Eliminar ${category.name}`}
                            className="text-destructive-foreground"
                        >
                            <Trash2 aria-hidden />
                        </Button>
                    }
                    title={`¿Eliminar la categoría "${category.name}"?`}
                    description="Sus gastos no se borran: quedan sin categoría."
                    confirmLabel="Sí, eliminar"
                    themed
                    form={destroy.form(category.id)}
                />
            </li>
        );
    }

    return (
        <li className="p-3">
            <form onSubmit={submit} noValidate className="grid gap-2">
                <Input
                    autoFocus
                    aria-label={`Nuevo nombre de ${category.name}`}
                    value={form.data.name}
                    maxLength={MAX_CATEGORY_NAME}
                    onChange={(e) => form.setData('name', e.target.value)}
                    aria-invalid={Boolean(form.errors.name)}
                    className="h-11"
                />
                <InputError message={form.errors.name} />
                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={stopRenaming}
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Guardar
                    </Button>
                </div>
            </form>
        </li>
    );
}
