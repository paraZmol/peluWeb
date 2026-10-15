import { Link } from '@inertiajs/react';
import { BellRing, Hourglass } from 'lucide-react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { formatLongDate, formatTime } from '@/lib/format';
import { PRIMARY_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/waitlist';
import type { WaitlistPlace } from './types';

function WaitlistPlaceCard({ place }: { place: WaitlistPlace }) {
    const day = formatLongDate(place.date);
    const Icon = place.hasTurn ? BellRing : Hourglass;

    return (
        <article
            aria-label={`${place.salonName}, ${day}`}
            className={cn(
                'flex h-full flex-col gap-4 rounded-xl p-4 sm:p-5',
                // Their turn stands out from the places still waiting.
                place.hasTurn
                    ? 'border border-brand-line bg-brand-soft'
                    : 'skin-card',
            )}
        >
            <div className="flex items-start gap-4">
                <span
                    aria-hidden
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-full',
                        place.hasTurn
                            ? 'skin-primary'
                            : 'bg-brand-soft text-brand-text',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <p className="display-type text-xl [overflow-wrap:normal]">
                        {place.salonName}
                    </p>
                    <p className="text-lg leading-tight font-extrabold tabular-nums first-letter:uppercase">
                        {day}
                    </p>
                    {place.services.length > 0 && (
                        <p className="text-sm break-words text-muted-foreground">
                            {place.services.join(', ')}
                        </p>
                    )}
                    {place.hasTurn ? (
                        <p role="status" className="text-sm font-semibold">
                            Se liberó un horario. Es tu turno
                            {/* "p. m." already closes the sentence. */}
                            {place.turnUntil ? (
                                <>
                                    {' hasta las '}
                                    <span className="whitespace-nowrap tabular-nums">
                                        {formatTime(place.turnUntil)}
                                    </span>
                                </>
                            ) : (
                                '.'
                            )}
                        </p>
                    ) : (
                        <p className="text-sm">
                            Te avisaremos si se libera un horario.
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-auto flex flex-wrap items-center justify-between gap-2">
                {place.hasTurn && (
                    <Link href={place.bookUrl} className={PRIMARY_ACTION}>
                        Reservar ahora
                    </Link>
                )}
                <ConfirmRemoveDialog
                    triggerLabel="Salir de la lista"
                    trigger={
                        <Button
                            variant="ghost"
                            className="-mx-2 h-11 px-2 text-muted-foreground"
                        >
                            Salir de la lista
                        </Button>
                    }
                    title="¿Salir de la lista de espera?"
                    description={`Ya no te avisaremos si se libera un horario el ${day} en ${place.salonName}. Puedes volver a anotarte cuando quieras.`}
                    confirmLabel="Sí, salir de la lista"
                    tone="default"
                    form={destroy.form(place.id)}
                />
            </div>
        </article>
    );
}

/**
 * "Lista de espera" in Mis reservas: the days someone asked to be told
 * about. Nothing is drawn while they hold no place.
 */
export function WaitlistPlaces({
    places,
    titleClassName,
}: {
    places: WaitlistPlace[];
    titleClassName: string;
}) {
    if (places.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-3">
            <h2 className={titleClassName}>Lista de espera</h2>
            <ul className="grid gap-4 lg:grid-cols-2 lg:gap-5">
                {places.map((place) => (
                    <li key={place.id} className="min-w-0">
                        <WaitlistPlaceCard place={place} />
                    </li>
                ))}
            </ul>
        </section>
    );
}
