import { Head, Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { ComplaintForm } from '@/components/complaints/complaint-form';
import type {
    ComplaintFormOptions,
    ComplaintProvider,
} from '@/components/complaints/types';
import { home } from '@/routes';

type Props = ComplaintFormOptions & {
    provider: ComplaintProvider;
    responseDays: number;
};

/** The platform's Libro de Reclamaciones: open to anyone, no account. */
export default function CreateComplaint({
    provider,
    responseDays,
    ...options
}: Props) {
    const providerLine = [
        provider.name,
        provider.ruc && `RUC ${provider.ruc}`,
        provider.address,
    ]
        .filter(Boolean)
        .join(' — ');

    return (
        <>
            <Head title="Libro de Reclamaciones" />
            <div className="min-h-dvh bg-background text-foreground">
                <main className="mx-auto flex w-full max-w-2xl flex-col gap-8 px-4 py-8 sm:py-12">
                    <Link
                        href={home()}
                        className="self-start text-sm font-semibold text-muted-foreground hover:text-foreground"
                    >
                        peluWeb
                    </Link>

                    <header className="flex flex-col gap-3">
                        <span className="flex size-11 items-center justify-center rounded-full bg-brand-soft text-brand-text">
                            <BookOpen className="size-5" strokeWidth={1.75} />
                        </span>
                        <h1 className="font-display text-3xl font-semibold text-balance sm:text-4xl">
                            Libro de Reclamaciones
                        </h1>
                        {providerLine && (
                            <p className="text-sm font-medium">
                                {providerLine}
                            </p>
                        )}
                        <p className="text-muted-foreground">
                            Conforme al Código de Protección y Defensa del
                            Consumidor, este sitio cuenta con un Libro de
                            Reclamaciones a tu disposición. Te responderemos en
                            un máximo de {responseDays} días hábiles.
                        </p>
                        <p className="rounded-xl bg-band p-4 text-sm">
                            Aquí reclamas sobre <strong>la plataforma</strong>{' '}
                            (tu cuenta, el funcionamiento de las reservas, tu
                            suscripción). Si tu reclamo es sobre{' '}
                            <strong>el servicio de un salón</strong> (la
                            atención, el precio, una seña), preséntalo en el
                            Libro de Reclamaciones de ese salón: lo encuentras
                            al pie de su página.
                        </p>
                    </header>

                    <section className="rounded-xl border bg-card p-5 shadow-card sm:p-8">
                        <ComplaintForm {...options} />
                    </section>
                </main>
            </div>
        </>
    );
}
