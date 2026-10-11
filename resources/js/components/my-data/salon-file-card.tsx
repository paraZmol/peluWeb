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
            <header className="flex items-center gap-4 border-b p-4 sm:p-5 lg:px-6">
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

            {/* Two matters, told apart by a rule: under on a phone, between on desktop. */}
            <div className="grid divide-y lg:grid-cols-2 lg:divide-x lg:divide-y-0 [&>section]:p-4 sm:[&>section]:p-5 lg:[&>section]:p-6">
                <CareSection file={file} />
                <PhotosSection file={file} />
            </div>

            <p className="border-t bg-surface-2 px-4 py-3 text-sm text-pretty text-muted-foreground sm:px-5 lg:px-6">
                {salon.isClosed
                    ? 'Este salón cerró su cuenta en peluWeb. Retira tus autorizaciones para borrar ahora tus alergias y fotos.'
                    : `¿Quieres ver, corregir o borrar el resto de lo que ${salon.name} guarda de ti (nombre, teléfono, citas)? Pídelo en el salón.`}
            </p>
        </article>
    );
}
