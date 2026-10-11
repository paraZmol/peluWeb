import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { SalonMark } from '@/components/public/salon-mark';
import { show as salonPage } from '@/routes/salons';
import { CareSection } from './care-section';
import { PhotosSection } from './photos-section';
import type { MyDataFile } from './types';

/**
 * What one salon keeps of the person under their authorization. Phones: one
 * column. Desktop: allergies on the left, pictures on the right.
 */
export function SalonFileCard({ file }: { file: MyDataFile }) {
    const { salon } = file;
    const titleId = `my-data-salon-${file.clientId}`;

    return (
        <article
            aria-labelledby={titleId}
            className="overflow-hidden rounded-xl skin-card"
        >
            <header className="flex items-center gap-4 border-b p-4 sm:p-5">
                <SalonMark name={salon.name} logoUrl={null} size="sm" />
                <div className="min-w-0 flex-1">
                    <h2
                        id={titleId}
                        className="text-xl leading-tight font-semibold break-words"
                    >
                        {salon.name}
                    </h2>
                    {salon.isClosed ? (
                        <p className="mt-1 inline-flex rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                            Este salón cerró su cuenta
                        </p>
                    ) : (
                        <Link
                            href={salonPage(salon.slug)}
                            aria-label={`Ver la página de ${salon.name}`}
                            className="-my-1 inline-flex min-h-11 items-center gap-1 rounded-md text-sm font-medium text-brand-text outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:min-h-8"
                        >
                            Ver la página del salón
                            <ChevronRight className="size-4" aria-hidden />
                        </Link>
                    )}
                </div>
            </header>

            <div className="grid gap-6 p-4 sm:p-5 lg:grid-cols-2 lg:gap-10">
                <CareSection file={file} />
                <PhotosSection file={file} />
            </div>

            <p className="border-t bg-surface-2 px-4 py-3 text-xs text-muted-foreground sm:px-5">
                {salon.isClosed
                    ? 'Este salón ya no usa peluWeb. Puedes retirar tus autorizaciones para que se borre lo que guardó.'
                    : `¿Quieres ver o corregir el resto de lo que ${salon.name} guarda de ti? Pídelo en el salón.`}
            </p>
        </article>
    );
}
