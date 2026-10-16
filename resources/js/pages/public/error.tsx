import { Head, Link } from '@inertiajs/react';
import { LegalFooter } from '@/components/legal/legal-footer';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const MESSAGES: Record<number, { title: string; text: string }> = {
    403: {
        title: 'Esta pantalla no es para tu cuenta',
        text: 'Tu cuenta no tiene permiso para verla. Si crees que deberías, pídeselo a quien dirige el salón.',
    },
    404: {
        title: 'No encontramos esa página',
        text: 'Puede que la dirección esté mal escrita o que ya no exista.',
    },
};

/** "Not yours" and "not found", with a way back instead of a dead end. */
export default function ErrorPage({ status }: { status: number }) {
    const { title, text } = MESSAGES[status] ?? MESSAGES[404];

    return (
        <>
            <Head title={title} />
            <main className="mx-auto flex min-h-[80dvh] w-full max-w-md flex-col items-start justify-center gap-4 px-6 py-10">
                <p className="text-sm font-medium text-muted-foreground tabular-nums">
                    Error {status}
                </p>
                <h1 className="text-3xl font-semibold text-balance">{title}</h1>
                <p className="text-muted-foreground">{text}</p>
                <div className="flex flex-wrap gap-2">
                    <Button
                        className="h-11 rounded-full"
                        onClick={() => window.history.back()}
                    >
                        Volver
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 rounded-full"
                    >
                        <Link href={home()}>Ir al inicio</Link>
                    </Button>
                </div>
            </main>
            <LegalFooter />
        </>
    );
}
