import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PublicPhoto } from './types';

type Props = {
    salonName: string;
    photos: PublicPhoto[];
};

export function PhotoGallery({ salonName, photos }: Props) {
    const [openIndex, setOpenIndex] = useState<number | null>(null);
    const altFor = (index: number): string =>
        photos[index].caption ?? `${salonName} — foto ${index + 1}`;
    const current = openIndex === null ? null : photos[openIndex];

    return (
        <div className="flex flex-col gap-4">
            <h2 className="font-display text-2xl font-semibold">Fotos</h2>

            <ul className="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0">
                {photos.map((photo, index) => (
                    <li
                        key={photo.url}
                        className="w-64 shrink-0 snap-start md:w-auto"
                    >
                        <button
                            type="button"
                            onClick={() => setOpenIndex(index)}
                            className="block aspect-square w-full overflow-hidden rounded-xl bg-muted outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            aria-label={`Ampliar: ${altFor(index)}`}
                        >
                            <img
                                src={photo.url}
                                alt={altFor(index)}
                                loading="lazy"
                                className="size-full object-cover transition-transform duration-300 motion-safe:hover:scale-105"
                            />
                        </button>
                    </li>
                ))}
            </ul>

            <Dialog
                open={current !== null}
                onOpenChange={(open) => !open && setOpenIndex(null)}
            >
                <DialogContent className="max-w-[calc(100%-2rem)] gap-3 p-3 sm:max-w-3xl">
                    {current && openIndex !== null && (
                        <>
                            <DialogTitle className="sr-only">
                                {altFor(openIndex)}
                            </DialogTitle>
                            <img
                                src={current.url}
                                alt={altFor(openIndex)}
                                className="max-h-[75svh] w-full rounded-lg object-contain"
                            />
                            <DialogDescription
                                className={current.caption ? 'px-1' : 'sr-only'}
                            >
                                {current.caption ??
                                    `Foto ${openIndex + 1} de ${photos.length}`}
                            </DialogDescription>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
