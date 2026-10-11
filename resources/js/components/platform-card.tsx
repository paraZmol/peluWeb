import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { edit as security } from '@/routes/security';

/** Where the platform panel lives; a Filament page, outside this app's pages. */
const PLATFORM_URL = '/plataforma';

/**
 * The way into the platform panel, for who runs peluWeb. Without two-step
 * verification the panel sends them back, so it says so first.
 */
export function PlatformCard({ hasTwoFactor }: { hasTwoFactor: boolean }) {
    return (
        <section className="flex flex-col items-start gap-4 rounded-xl border bg-card p-6">
            <ShieldCheck className="size-6 text-primary" aria-hidden />
            <div>
                <h2 className="text-lg font-semibold">
                    Administras la plataforma
                </h2>
                <p className="text-sm text-muted-foreground">
                    {hasTwoFactor
                        ? 'Salones, usuarios y reseñas reportadas.'
                        : 'Para entrar al panel de la plataforma, primero activa la verificación en dos pasos.'}
                </p>
            </div>
            <Button asChild className="rounded-full">
                {hasTwoFactor ? (
                    <a href={PLATFORM_URL}>Abrir el panel de la plataforma</a>
                ) : (
                    <Link href={security()}>
                        Activar la verificación en dos pasos
                    </Link>
                )}
            </Button>
        </section>
    );
}
