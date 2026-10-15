import { usePage } from '@inertiajs/react';
import { Download, Share, SquarePlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { cn } from '@/lib/utils';

type Props = {
    /** What lands on the home screen: the salon; the platform when left out. */
    name?: string;
    className?: string;
};

/**
 * Offers to put the app on the home screen. On Android and desktop the
 * button opens the browser's own dialog; iPhone and iPad have none, so it
 * shows the two taps to do it by hand.
 */
export function InstallApp({ name: salonName, className }: Props) {
    const { mode, install, dismiss } = useInstallPrompt();
    const { name: appName } = usePage().props;
    const name = salonName ?? appName;

    if (mode === null) {
        return null;
    }

    return (
        <section
            aria-label={`Instalar ${name}`}
            className={cn(
                'flex flex-col items-start gap-3 rounded-xl p-5 skin-card',
                className,
            )}
        >
            <div>
                <h2 className="font-semibold">Ten {name} a un toque</h2>
                <p className="text-sm text-muted-foreground">
                    Instala la app de {name} en tu pantalla de inicio: abre más
                    rápido y tus reservas se ven aunque no haya conexión.
                </p>
            </div>

            {mode === 'ios' && (
                <ol className="flex flex-col gap-2 text-sm">
                    <li className="flex items-center gap-2">
                        <Share className="size-4 shrink-0" aria-hidden />
                        <span>Toca «Compartir» en la barra del navegador.</span>
                    </li>
                    <li className="flex items-center gap-2">
                        <SquarePlus className="size-4 shrink-0" aria-hidden />
                        <span>Elige «Añadir a pantalla de inicio».</span>
                    </li>
                </ol>
            )}

            <div className="flex flex-wrap gap-2">
                {mode === 'prompt' && (
                    <Button className="h-11" onClick={() => void install()}>
                        <Download aria-hidden /> Instalar
                    </Button>
                )}
                <Button variant="outline" className="h-11" onClick={dismiss}>
                    {mode === 'ios' ? 'Entendido' : 'Ahora no'}
                </Button>
            </div>
        </section>
    );
}
