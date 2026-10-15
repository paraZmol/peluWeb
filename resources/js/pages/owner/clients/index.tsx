import { Head, Link, router } from '@inertiajs/react';
import { VipControl } from '@/components/clients/vip-control';
import { Search, Users } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/clients';

type Client = {
    id: number;
    name: string;
    phone: string | null;
    isVip: boolean;
    hasAccount: boolean;
    bookingsCount: number;
    noShows: number;
};

type Props = {
    clients: Client[];
    search: string;
    isTruncated: boolean;
    /** false: they see who is VIP but cannot change it. */
    canManageVip: boolean;
};

export default function Clients({
    clients,
    search,
    isTruncated,
    canManageVip,
}: Props) {
    const [term, setTerm] = useState(search);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(index(), term ? { buscar: term } : {}, {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title="Clientes" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Clientes</h1>
                    <p className="text-sm text-muted-foreground">
                        Quienes han reservado contigo. Marca como VIP a quien
                        quieras darle acceso a tus horarios reservados.
                    </p>
                </div>

                <form onSubmit={submit} role="search" className="flex gap-2">
                    <Input
                        type="search"
                        aria-label="Buscar por nombre o teléfono"
                        placeholder="Buscar por nombre o teléfono"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                    />
                    <Button type="submit" variant="outline">
                        <Search /> Buscar
                    </Button>
                </form>

                {clients.length === 0 ? (
                    <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        <Users className="size-6" aria-hidden />
                        {search
                            ? 'Nadie coincide con esa búsqueda.'
                            : 'Aquí aparecerán tus clientes cuando hagan su primera reserva.'}
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border bg-card">
                        {clients.map((client) => (
                            <li
                                key={client.id}
                                className="flex items-center gap-3 p-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium break-words">
                                        <Link
                                            href={show(client.id)}
                                            aria-label={`Ver ficha de ${client.name}`}
                                            className="inline-flex min-h-11 items-center rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:min-h-0"
                                        >
                                            {client.name}
                                        </Link>
                                    </p>
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {[
                                            client.phone,
                                            `${client.bookingsCount} ${client.bookingsCount === 1 ? 'reserva' : 'reservas'}`,
                                            client.noShows > 0
                                                ? `faltó ${client.noShows}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <VipControl
                                    client={client}
                                    canManage={canManageVip}
                                />
                            </li>
                        ))}
                    </ul>
                )}

                {isTruncated && (
                    <p className="text-center text-sm text-muted-foreground">
                        Mostramos los primeros 50. Usa el buscador para
                        encontrar a alguien más.
                    </p>
                )}
            </div>
        </>
    );
}

Clients.layout = {
    breadcrumbs: [{ title: 'Clientes', href: index() }],
};
