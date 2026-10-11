import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { SalonFileCard } from '@/components/my-data/salon-file-card';
import type { MyDataFile } from '@/components/my-data/types';
import { Button } from '@/components/ui/button';
import { index as bookings } from '@/routes/bookings';
import { index } from '@/routes/my-data';

type Props = { files: MyDataFile[] };

/**
 * "Mis datos": what each salon keeps of the person that needed their
 * authorization (allergies and pictures), and the switches to give it or
 * take it back. One card per salon.
 */
export default function MyData({ files }: Props) {
    return (
        <>
            <Head title="Mis datos" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 py-6 lg:py-10">
                <div className="flex flex-col gap-2">
                    <h1 className="text-3xl font-semibold lg:text-4xl">
                        Mis datos
                    </h1>
                    {files.length > 0 && (
                        <p className="max-w-2xl text-muted-foreground">
                            Lo que cada salón guarda de ti solo si lo autorizas:
                            tus alergias y las fotos de tus servicios. Aquí lo
                            ves, lo autorizas y lo retiras.
                        </p>
                    )}
                </div>

                {files.length === 0 && (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                        <span
                            aria-hidden
                            className="flex size-14 items-center justify-center rounded-full bg-brand-soft text-brand-text"
                        >
                            <ShieldCheck className="size-6" />
                        </span>
                        <p className="max-w-sm text-balance">
                            Todavía no tienes ficha en ningún salón. Aparecerá
                            aquí cuando reserves con tu cuenta.
                        </p>
                        <Button asChild variant="outline" className="h-11">
                            <Link href={bookings()}>Ir a Mis reservas</Link>
                        </Button>
                    </div>
                )}

                {files.length > 0 && (
                    <ul className="flex flex-col gap-6">
                        {files.map((file) => (
                            <li key={file.clientId} className="min-w-0">
                                <SalonFileCard file={file} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

MyData.layout = {
    breadcrumbs: [{ title: 'Mis datos', href: index() }],
};
