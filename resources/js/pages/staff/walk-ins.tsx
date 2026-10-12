import { Head } from '@inertiajs/react';
import { Armchair } from 'lucide-react';
import { AddWalkIn } from '@/components/walk-ins/add-walk-in';
import { LineItem } from '@/components/walk-ins/line-item';
import type { WalkIn, WalkInOption } from '@/components/walk-ins/types';
import { index } from '@/routes/walk-ins';

type Props = {
    line: WalkIn[];
    services: WalkInOption[];
    staff: WalkInOption[];
    nameMaxLength: number;
};

/**
 * "Fila sin cita": who came in without a booking today, in the order they
 * arrived. The team writes them down, calls them and closes their place.
 */
export default function WalkIns({
    line,
    services,
    staff,
    nameMaxLength,
}: Props) {
    const open = line.filter(
        (walkIn) => walkIn.status === 'waiting' || walkIn.status === 'called',
    );
    const waiting = open.filter((walkIn) => walkIn.status === 'waiting');
    const closed = line.filter(
        (walkIn) => walkIn.status === 'attended' || walkIn.status === 'left',
    );

    return (
        <>
            <Head title="Fila sin cita" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-3xl font-semibold">Fila sin cita</h1>
                    <p className="text-muted-foreground">
                        Quien llega sin reservar, en el orden en que llegó. La
                        fila empieza de nuevo cada día.
                    </p>
                </div>

                <AddWalkIn
                    services={services}
                    staff={staff}
                    nameMaxLength={nameMaxLength}
                />

                <section
                    aria-labelledby="walk-ins-open"
                    className="flex flex-col gap-3"
                >
                    <h2 id="walk-ins-open" className="font-semibold">
                        {waiting.length === 1
                            ? '1 persona en espera'
                            : `${waiting.length} personas en espera`}
                    </h2>
                    {open.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-10 text-center text-muted-foreground">
                            <Armchair className="size-6" aria-hidden />
                            <p>
                                No hay nadie esperando. Anota arriba a quien
                                llegue sin cita.
                            </p>
                        </div>
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {open.map((walkIn) => (
                                <LineItem
                                    key={walkIn.id}
                                    walkIn={walkIn}
                                    turn={
                                        walkIn.status === 'waiting'
                                            ? waiting.indexOf(walkIn) + 1
                                            : null
                                    }
                                />
                            ))}
                        </ol>
                    )}
                </section>

                {closed.length > 0 && (
                    <section
                        aria-labelledby="walk-ins-closed"
                        className="flex flex-col gap-2"
                    >
                        <h2 id="walk-ins-closed" className="font-semibold">
                            Hoy ya pasaron
                        </h2>
                        <ul className="divide-y rounded-xl border bg-card text-sm">
                            {closed.map((walkIn) => (
                                <li
                                    key={walkIn.id}
                                    className="flex items-center justify-between gap-3 px-4 py-3"
                                >
                                    <span>{walkIn.name}</span>
                                    <span className="text-muted-foreground">
                                        {walkIn.statusLabel}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

WalkIns.layout = {
    breadcrumbs: [{ title: 'Fila sin cita', href: index() }],
};
