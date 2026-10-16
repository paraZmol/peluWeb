import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import type { ComplaintReceipt } from '@/components/complaints/types';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

/** The receipt right after filing: the number and where the copy went. */
export default function ComplaintReceiptPage({
    receipt,
}: {
    receipt: ComplaintReceipt;
}) {
    return (
        <>
            <Head title={`Constancia N.º ${receipt.code}`} />
            <div className="min-h-dvh bg-background text-foreground">
                <main className="mx-auto flex min-h-dvh w-full max-w-lg flex-col justify-center gap-5 px-4 py-10">
                    <CheckCircle2
                        className="size-10 text-success"
                        strokeWidth={1.75}
                        aria-hidden
                    />
                    <h1 className="font-display text-3xl font-semibold text-balance">
                        Recibimos tu {receipt.kind}{' '}
                        <span className="tabular-nums">N.º {receipt.code}</span>
                    </h1>
                    <div className="flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-card">
                        <p>
                            Te enviamos una copia a{' '}
                            <strong className="break-all">
                                {receipt.email}
                            </strong>
                            .
                        </p>
                        <p>
                            Te responderemos en un máximo de{' '}
                            {receipt.responseDays} días hábiles, a más tardar el{' '}
                            <strong className="tabular-nums">
                                {receipt.dueOn}
                            </strong>
                            .
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Presentar un reclamo no impide acudir a otras vías
                            ni es requisito para denunciar ante Indecopi.
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 self-start rounded-full"
                    >
                        <Link href={home()}>Ir al inicio</Link>
                    </Button>
                </main>
            </div>
        </>
    );
}
