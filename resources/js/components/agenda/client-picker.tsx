import { Star, UserRound, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ClientSuggestion } from '@/components/booking/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFetched } from '@/hooks/use-fetched';
import { clients } from '@/routes/agenda/bookings';

export type ClientChoice = {
    /** Set when someone already in the salon's list was picked. */
    id: number | null;
    name: string;
    phone: string;
};

type Props = {
    value: ClientChoice;
    onChange: (value: ClientChoice) => void;
    errors: Partial<
        Record<'client_id' | 'client_name' | 'client_phone', string>
    >;
};

const MIN_SEARCH_LENGTH = 2;
const TYPING_PAUSE_MS = 250;

/** Who the visit is for: someone the salon already knows, or a new name. */
export function ClientPicker({ value, onChange, errors }: Props) {
    const [search, setSearch] = useState('');
    const typed = value.name.trim();

    // Ask only once the typing pauses.
    useEffect(() => {
        const timer = window.setTimeout(
            () => setSearch(typed),
            TYPING_PAUSE_MS,
        );

        return () => window.clearTimeout(timer);
    }, [typed]);

    const suggestions = useFetched<{ clients: ClientSuggestion[] }>(
        value.id === null && search.length >= MIN_SEARCH_LENGTH
            ? clients.url({ query: { buscar: search } })
            : null,
        '',
    );

    if (value.id !== null) {
        return (
            <div className="flex items-center gap-3 rounded-xl border bg-card p-4">
                <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                >
                    <UserRound className="size-5" />
                </span>
                <p className="min-w-0 flex-1">
                    <span className="block font-medium break-words">
                        {value.name}
                    </span>
                    {value.phone && (
                        <span className="block text-sm text-muted-foreground tabular-nums">
                            {value.phone}
                        </span>
                    )}
                </p>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange({ id: null, name: '', phone: '' })}
                >
                    <X aria-hidden /> Cambiar
                </Button>
            </div>
        );
    }

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="client-name">Nombre de tu cliente</Label>
                <Input
                    id="client-name"
                    autoComplete="off"
                    maxLength={80}
                    placeholder="Escribe para buscar o agregar"
                    value={value.name}
                    onChange={(e) =>
                        onChange({ ...value, name: e.target.value })
                    }
                />
                <InputError message={errors.client_name ?? errors.client_id} />
                {(suggestions.data?.clients.length ?? 0) > 0 && (
                    <ul
                        aria-label="Clientes que ya están en tu lista"
                        className="flex flex-col divide-y overflow-hidden rounded-xl border bg-card"
                    >
                        {suggestions.data?.clients.map((client) => (
                            <li key={client.id}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange({
                                            id: client.id,
                                            name: client.name,
                                            phone: client.phone ?? '',
                                        })
                                    }
                                    className="flex min-h-12 w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                                >
                                    <span className="min-w-0 flex-1 font-medium break-words">
                                        {client.name}
                                    </span>
                                    {client.isVip && (
                                        <Star
                                            className="size-4 shrink-0 text-primary"
                                            aria-label="VIP"
                                        />
                                    )}
                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                        {client.phone}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="client-phone">WhatsApp (opcional)</Label>
                <Input
                    id="client-phone"
                    type="tel"
                    inputMode="tel"
                    autoComplete="off"
                    maxLength={16}
                    placeholder="987654321"
                    value={value.phone}
                    onChange={(e) =>
                        onChange({ ...value, phone: e.target.value })
                    }
                />
                <InputError message={errors.client_phone} />
            </div>
        </div>
    );
}
