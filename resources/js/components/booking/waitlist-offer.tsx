import { Link } from '@inertiajs/react';
import { BellRing, Check } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { useWaitlistJoin } from '@/hooks/use-waitlist-join';
import { PRIMARY_ACTION, SOFT_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { resume } from '@/routes/salons/booking';
import { notice } from '@/routes/verification';
import type { Viewer } from './types';

const LABEL = 'Avísame si se libera';

type Props = {
    salonSlug: string;
    viewer: Viewer;
    wizard: BookingWizard;
    /** The day being looked at. */
    date: string;
    /** That day has no free times for what was chosen. */
    isFull: boolean;
    /** The day turned out to have times: ask for them again. */
    onStale: () => void;
};

/**
 * Under a day with no free times: be told if one opens up. Guests sign in
 * first (what they chose is kept) and come back to this same day.
 */
export function WaitlistOffer({
    salonSlug,
    viewer,
    wizard,
    date,
    isFull,
    onStale,
}: Props) {
    const { sending, problem, hasJoined, join } = useWaitlistJoin({
        salonSlug,
        onStale,
    });
    const isDone = hasJoined(date);

    // Stays mounted while other days are looked at: it remembers the joined ones.
    if (!isFull) {
        return null;
    }

    return (
        <section
            aria-label="Lista de espera"
            className="flex flex-col items-center gap-3 rounded-xl p-5 text-center skin-card sm:p-6"
        >
            <span
                aria-hidden
                className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand-text"
            >
                <BellRing className="size-5" />
            </span>
            <p className="max-w-sm font-semibold text-balance">
                ¿Quieres que te avisemos si se libera un horario este día?
            </p>

            {isDone && (
                <button
                    type="button"
                    disabled
                    className={cn(SOFT_ACTION, 'disabled:opacity-100')}
                >
                    <Check aria-hidden /> Ya estás en la lista de ese día
                </button>
            )}
            {!isDone &&
                viewer.state === 'guest' && (
                    // A full page load: the server sends guests to sign in and back.
                    <a href={resume.url(salonSlug)} className={PRIMARY_ACTION}>
                        {LABEL}
                    </a>
                )}
            {!isDone && viewer.state === 'unverified' && (
                <Link href={notice()} className={PRIMARY_ACTION}>
                    Verifica tu correo para anotarte
                </Link>
            )}
            {!isDone && viewer.state === 'ready' && (
                <button
                    type="button"
                    className={PRIMARY_ACTION}
                    disabled={sending}
                    onClick={() =>
                        join({
                            selections: wizard.selections,
                            staffId: wizard.staffId,
                            date,
                        })
                    }
                >
                    {sending && <Spinner />}
                    {LABEL}
                </button>
            )}

            <p className="max-w-sm text-sm text-balance text-muted-foreground">
                Avisamos a una persona a la vez. Tendrás 30 minutos para
                reservar; el horario no queda apartado.
                {viewer.state === 'guest' &&
                    ' Te pediremos entrar o crear tu cuenta: guardamos lo que elegiste.'}
            </p>

            {problem && (
                <p
                    role="alert"
                    className="w-full rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm"
                >
                    {problem}
                </p>
            )}
        </section>
    );
}
