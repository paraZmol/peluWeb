import { useForm } from '@inertiajs/react';
import { Plus, Tags } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { usePortalTheme } from '@/components/salon/portal-theme';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { store } from '@/routes/expenses/categories';
import { MAX_CATEGORIES, MAX_CATEGORY_NAME } from './category-limits';
import { CategoryRow } from './category-row';
import type { ExpenseCategory } from './types';

type Props = { categories: ExpenseCategory[] };

/** The salon's own kinds of expense: add, rename and remove. */
export function CategoriesDialog({ categories }: Props) {
    const portalTheme = usePortalTheme();
    const form = useForm({ name: '' });
    const isFull = categories.length >= MAX_CATEGORIES;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Tags aria-hidden /> Categorías
                </Button>
            </DialogTrigger>
            <DialogContent
                {...portalTheme}
                className={cn(
                    portalTheme.className,
                    SHEET_ON_PHONE,
                    'overflow-y-auto',
                )}
            >
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>Categorías de gastos</DialogTitle>
                    <DialogDescription>
                        Ordena tus gastos a tu manera. Si eliminas una
                        categoría, sus gastos quedan sin categoría.
                    </DialogDescription>
                </DialogHeader>

                {categories.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Aún no tienes categorías. Agrega la primera aquí abajo.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border bg-card">
                        {categories.map((category) => (
                            <CategoryRow
                                key={category.id}
                                category={category}
                            />
                        ))}
                    </ul>
                )}

                {isFull ? (
                    <p className="text-sm text-muted-foreground">
                        Llegaste al máximo de {MAX_CATEGORIES} categorías.
                        Elimina una para agregar otra.
                    </p>
                ) : (
                    <form onSubmit={submit} noValidate className="grid gap-2">
                        <Label htmlFor="new-category">Nueva categoría</Label>
                        <div className="flex gap-2">
                            <Input
                                id="new-category"
                                value={form.data.name}
                                maxLength={MAX_CATEGORY_NAME}
                                placeholder="Ej. Publicidad"
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                                className="h-11"
                            />
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="h-11"
                            >
                                <Plus aria-hidden /> Agregar
                            </Button>
                        </div>
                        <InputError message={form.errors.name} />
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
