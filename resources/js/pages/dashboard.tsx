import { Head, Link, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { CalendarDays, ExternalLink, Store, Users } from 'lucide-react';
import { SetupChecklist } from '@/components/salon/setup-checklist';
import type { SetupStep } from '@/components/salon/setup-checklist';
import { Button } from '@/components/ui/button';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { dashboard } from '@/routes';
import { index as agenda } from '@/routes/agenda';
import { create as createSalon } from '@/routes/salons';
import { index as team } from '@/routes/team';

type Props = {
    setup: SetupStep[] | null;
    publicUrl: string | null;
};

export default function Dashboard({ setup, publicUrl }: Props) {
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
                <h1 className="text-3xl font-semibold">Hola, {firstName}</h1>

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

                {setup && <SetupChecklist steps={setup} />}

                {currentSalon ? (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Link
                            href={agenda()}
                            className="flex flex-col gap-2 rounded-xl border bg-card p-5 transition-colors hover:bg-accent"
                        >
                            <CalendarDays className="size-5 text-primary" />
                            <h2 className="font-semibold">Agenda de hoy</h2>
                            <p className="text-sm text-muted-foreground">
                                Las citas del día y lo que falta confirmar.
                            </p>
                        </Link>
                        {isOwner && (
                            <Link
                                href={team()}
                                className="flex flex-col gap-2 rounded-xl border bg-card p-5 transition-colors hover:bg-accent"
                            >
                                <Users className="size-5 text-primary" />
                                <h2 className="font-semibold">Tu equipo</h2>
                                <p className="text-sm text-muted-foreground">
                                    Invita a quienes atienden en{' '}
                                    {currentSalon.name}.
                                </p>
                            </Link>
                        )}
                    </div>
                ) : (
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
