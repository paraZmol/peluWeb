import { Head, Link, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { ExternalLink, Store, Ticket } from 'lucide-react';
import { PlatformCard } from '@/components/platform-card';
import { PanelShortcuts } from '@/components/salon/panel-shortcuts';
import { SetupChecklist } from '@/components/salon/setup-checklist';
import type { SetupStep } from '@/components/salon/setup-checklist';
import { Button } from '@/components/ui/button';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { dashboard } from '@/routes';
import { index as myBookings } from '@/routes/bookings';
import { create as createSalon } from '@/routes/salons';

type Props = {
    setup: SetupStep[] | null;
    publicUrl: string | null;
    /** Only for who runs the platform: the way into its panel. */
    platform: { hasTwoFactor: boolean } | null;
};

export default function Dashboard({ setup, publicUrl, platform }: Props) {
    const { auth } = usePage().props;
    const { currentSalon, isOwner } = useCurrentSalon();
    const reduceMotion = useReducedMotion();
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Panel" />
            <motion.div
                initial={reduceMotion ? false : { opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6"
            >
                <div className="flex flex-col gap-1">
                    <h1 className="text-3xl font-semibold">
                        Hola, {firstName}
                    </h1>
                    {currentSalon && (
                        <p className="text-sm text-muted-foreground">
                            {currentSalon.name} ·{' '}
                            {isOwner ? 'Titular' : 'Personal'}
                        </p>
                    )}
                </div>

                {currentSalon && publicUrl && (
                    <Button
                        asChild
                        variant="outline"
                        className="self-start rounded-full"
                    >
                        <a href={publicUrl} target="_blank" rel="noopener">
                            <ExternalLink /> Ver la página de{' '}
                            {currentSalon.name}
                        </a>
                    </Button>
                )}

                {platform && (
                    <PlatformCard hasTwoFactor={platform.hasTwoFactor} />
                )}

                {setup && <SetupChecklist steps={setup} />}

                {currentSalon ? (
                    <PanelShortcuts />
                ) : (
                    <Link
                        href={myBookings()}
                        className="flex flex-col gap-2 rounded-xl border bg-card p-5 transition-colors hover:bg-accent"
                    >
                        <Ticket className="size-5 text-primary" />
                        <h2 className="font-semibold">Mis reservas</h2>
                        <p className="text-sm text-muted-foreground">
                            Tus próximas citas y las que ya pasaron.
                        </p>
                    </Link>
                )}

                {!currentSalon && !platform && (
                    <div className="flex flex-col items-start gap-4 rounded-xl border bg-card p-6">
                        <Store className="size-6 text-primary" />
                        <div>
                            <h2 className="text-lg font-semibold">
                                ¿Tienes una peluquería, barbería o salón?
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Crea tu salón en un minuto y prueba gratis
                                durante 30 días.
                            </p>
                        </div>
                        <Button asChild className="rounded-full">
                            <Link href={createSalon()}>Crear mi salón</Link>
                        </Button>
                    </div>
                )}
            </motion.div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Panel',
            href: dashboard(),
        },
    ],
};
