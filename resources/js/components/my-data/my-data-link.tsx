import { Link } from '@inertiajs/react';
import { index as myData } from '@/routes/my-data';

/** The way from "Mis reservas" to what each salon keeps of the person. */
export function MyDataLink() {
    return (
        <p className="text-sm text-muted-foreground">
            ¿Qué guardan de ti los salones?{' '}
            <Link
                href={myData()}
                className="inline-flex min-h-11 items-center rounded-md font-medium text-brand-text underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                Ver mis datos en cada salón
            </Link>
        </p>
    );
}
