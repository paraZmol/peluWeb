import { BellOff, BellRing, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePush } from '@/hooks/use-push';
import type { PushState } from '@/lib/push';
import { cn } from '@/lib/utils';

const HELP: Record<PushState, string> = {
    on: 'Este dispositivo recibe un aviso cuando una reserva se confirma, cambia de horario o se cancela, y el recordatorio del día anterior.',
    off: 'Recibe un aviso en este dispositivo cuando una reserva se confirma, cambia de horario o se cancela. Puedes apagarlo cuando quieras.',
    denied: 'Las notificaciones están bloqueadas para este sitio. Para recibirlas, permítelas en los ajustes del navegador y vuelve a esta pantalla.',
    'needs-install':
        'En iPhone y iPad los avisos solo funcionan con la app en la pantalla de inicio: toca «Compartir» y elige «Añadir a pantalla de inicio»; después actívalos desde ahí.',
    unsupported: 'Este navegador no permite notificaciones.',
};

type Props = {
    /** On a list, it only shows while there is something to turn on. */
    isOfferOnly?: boolean;
    className?: string;
};

/**
 * "Avisos en este dispositivo": the switch for push notifications here,
 * or why there is none. Each device is switched on its own.
 */
export function PushToggle({ isOfferOnly = false, className }: Props) {
    const { state, isBusy, hasFailed, enable, disable, recheck } = usePush();
    const [isStillBlocked, setIsStillBlocked] = useState(false);

    if (state === null || (isOfferOnly && state !== 'off')) {
        return null;
    }

    const lookAgain = () =>
        void recheck()
            .then((found) => setIsStillBlocked(found === 'denied'))
            .catch(() => setIsStillBlocked(true));

    return (
        <section
            aria-label="Avisos en este dispositivo"
            className={cn('flex flex-col items-start gap-3', className)}
        >
            <div>
                <h2 className="flex items-center gap-2 font-semibold">
                    {state === 'on' ? (
                        <BellRing className="size-4" aria-hidden />
                    ) : (
                        <BellOff className="size-4" aria-hidden />
                    )}
                    Avisos en este dispositivo
                    {state === 'on' && (
                        <span className="text-sm font-normal text-muted-foreground">
                            · activados
                        </span>
                    )}
                </h2>
                <p className="text-sm text-muted-foreground">{HELP[state]}</p>
                {state === 'denied' && (
                    <p className="mt-2 text-sm font-medium">
                        Toca el candado junto a la dirección y activa
                        Notificaciones.
                    </p>
                )}
            </div>

            {state === 'off' && (
                <BusyButton
                    isBusy={isBusy}
                    busyLabel="Activando…"
                    onClick={enable}
                >
                    Activar avisos
                </BusyButton>
            )}
            {state === 'on' && (
                <BusyButton
                    variant="outline"
                    isBusy={isBusy}
                    busyLabel="Desactivando…"
                    onClick={disable}
                >
                    Desactivar en este dispositivo
                </BusyButton>
            )}
            {state === 'denied' && (
                <BusyButton
                    variant="outline"
                    isBusy={isBusy}
                    busyLabel="Comprobando…"
                    onClick={lookAgain}
                >
                    Ya lo cambié
                </BusyButton>
            )}

            {state === 'denied' && isStillBlocked && !isBusy && (
                <p role="status" className="text-sm text-muted-foreground">
                    Siguen bloqueadas en este navegador.
                </p>
            )}
            {hasFailed && (
                <p role="alert" className="text-sm text-destructive-foreground">
                    No se pudo cambiar. Revisa tu conexión y vuelve a
                    intentarlo.
                </p>
            )}
        </section>
    );
}

type BusyButtonProps = {
    isBusy: boolean;
    busyLabel: string;
    variant?: 'default' | 'outline';
    onClick: () => unknown;
    children: string;
};

/** A 44 px button that says what it is doing while the browser answers. */
function BusyButton({
    isBusy,
    busyLabel,
    variant = 'default',
    onClick,
    children,
}: BusyButtonProps) {
    return (
        <Button
            variant={variant}
            className="h-11"
            disabled={isBusy}
            aria-busy={isBusy}
            onClick={() => void onClick()}
        >
            {isBusy && <LoaderCircle className="animate-spin" aria-hidden />}
            {isBusy ? busyLabel : children}
        </Button>
    );
}
