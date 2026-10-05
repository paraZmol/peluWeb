import { router } from '@inertiajs/react';
import { BookOpen, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { formatMinutes } from '@/lib/format';
import { store } from '@/routes/services/catalog';
import type { Option } from '@/types';
import type { CatalogEntry } from './types';

type Props = {
    catalog: CatalogEntry[];
    categories: Option[];
    primary?: boolean;
};

/** Accent-insensitive search, so "peluqueria" finds "Peluquería". */
const normalize = (text: string) =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

export function CatalogSheet({ catalog, categories, primary = false }: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<number[]>([]);

    const groups = useMemo(() => {
        const q = normalize(query.trim());
        const matches = catalog.filter(
            (entry) =>
                q === '' ||
                normalize(
                    [
                        entry.name,
                        entry.nameEn ?? '',
                        entry.description,
                        ...entry.tags,
                    ].join(' '),
                ).includes(q),
        );

        return categories
            .map((category) => ({
                ...category,
                entries: matches.filter(
                    (entry) => entry.category === category.value,
                ),
            }))
            .filter((group) => group.entries.length > 0);
    }, [catalog, categories, query]);

    const toggle = (id: number) =>
        setSelected((ids) =>
            ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id],
        );

    const add = () =>
        router.post(
            store(),
            { template_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setOpen(false);
                },
            },
        );

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant={primary ? 'default' : 'outline'}>
                    <BookOpen /> Agregar del catálogo
                </Button>
            </SheetTrigger>
            <SheetContent className="flex w-full flex-col gap-0 sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Catálogo de servicios</SheetTitle>
                    <SheetDescription>
                        Elige los que ofreces. Luego ajustas precio y tiempo.
                    </SheetDescription>
                </SheetHeader>
                <div className="relative px-4 pb-3">
                    <Search className="pointer-events-none absolute top-1/2 left-7 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Buscar en el catálogo"
                        placeholder="Busca: fade, balayage, keratina…"
                        className="pl-9"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>
                <div className="flex-1 overflow-y-auto px-4">
                    {groups.length === 0 && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No encontramos ese servicio. Puedes crearlo tú.
                        </p>
                    )}
                    {groups.map((group) => (
                        <section key={group.value} className="mb-4">
                            <h3 className="mb-2 text-sm font-semibold text-muted-foreground">
                                {group.label}
                            </h3>
                            <ul className="flex flex-col gap-2">
                                {group.entries.map((entry) => (
                                    <li key={entry.id}>
                                        <label className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-accent">
                                            <Checkbox
                                                className="mt-0.5"
                                                checked={selected.includes(
                                                    entry.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggle(entry.id)
                                                }
                                            />
                                            <span className="flex flex-col gap-0.5">
                                                <span className="text-sm font-medium">
                                                    {entry.name}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {entry.description}
                                                </span>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {formatMinutes(
                                                        entry.minutes,
                                                    )}{' '}
                                                    · S/ {entry.priceMin}–
                                                    {entry.priceMax} en Lima
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
                <SheetFooter className="border-t">
                    <Button
                        className="rounded-full"
                        disabled={selected.length === 0}
                        onClick={add}
                    >
                        {selected.length === 0
                            ? 'Elige al menos uno'
                            : `Agregar ${selected.length}`}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
