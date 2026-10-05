import { Head, Link, usePage } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Inicio" />
            <main className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-center text-foreground">
                <h1 className="max-w-2xl font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                    Reservas y gestión para tu peluquería, barbería o salón
                </h1>
                <p className="max-w-xl text-muted-foreground">
                    Organiza tu agenda, a tu equipo y a tus clientes en un solo
                    lugar.
                </p>
                <div className="flex flex-wrap items-center justify-center gap-3">
                    {auth.user ? (
                        <Link
                            href={dashboard()}
                            className={cn(buttonVariants())}
                        >
                            Ir al panel
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className={cn(buttonVariants())}
                            >
                                Iniciar sesión
                            </Link>
                            <Link
                                href={register()}
                                className={cn(
                                    buttonVariants({ variant: 'outline' }),
                                )}
                            >
                                Crear cuenta
                            </Link>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
