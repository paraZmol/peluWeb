import { PauseCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    isPaused: boolean;
    hasWhatsapp: boolean;
    /** The card has its own frame; otherwise a pause needs one on phones. */
    framed: boolean;
};

/** "Reserva tu cita" (desktop only) or, on any screen, "Reservas en pausa". */
export function BookingCardHeading({ isPaused, hasWhatsapp, framed }: Props) {
    return (
        <div
            className={cn(
                !isPaused && 'hidden lg:block',
                // On phones a pause is a notice of its own, not a heading adrift.
                isPaused &&
                    !framed &&
                    'max-lg:rounded-xl max-lg:p-4 max-lg:skin-card',
            )}
        >
            <h2
                id="booking-card-title"
                className="flex items-center gap-2 display-type text-title-3"
            >
                {isPaused && (
                    <PauseCircle
                        className="size-[.8em] shrink-0 text-brand-text"
                        strokeWidth={1.75}
                        aria-hidden
                    />
                )}
                {isPaused ? 'Reservas en pausa' : 'Reserva tu cita'}
            </h2>
            <p
                className={cn(
                    'mt-1 text-sm',
                    isPaused
                        ? 'text-pretty text-foreground'
                        : 'text-muted-foreground',
                )}
            >
                {!isPaused
                    ? 'Elige el servicio, quién te atiende y la hora.'
                    : hasWhatsapp
                      ? 'Por ahora este salón no toma citas nuevas en línea. Escríbele por WhatsApp para pedir la tuya. Si ya tienes una cita, sigue en pie.'
                      : 'Por ahora este salón no toma citas nuevas en línea. Vuelve más tarde. Si ya tienes una cita, sigue en pie.'}
            </p>
        </div>
    );
}
