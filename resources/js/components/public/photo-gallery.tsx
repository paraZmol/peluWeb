import { useState } from 'react';
import {
    MAX_TILES,
    galleryGridClass,
    galleryTileClass,
} from './gallery/gallery-layout';
import { GalleryTile } from './gallery/gallery-tile';
import { PhotoViewer } from './gallery/photo-viewer';
import { SectionHeading } from './section-heading';
import type { PublicPhoto } from './types';

type Props = {
    salonName: string;
    photos: PublicPhoto[];
};

/** The salon's work as a mosaic; every photo opens in the viewer. */
export function PhotoGallery({ salonName, photos }: Props) {
    const [openIndex, setOpenIndex] = useState<number | null>(null);
    const tiles = photos.slice(0, MAX_TILES);
    const altFor = (index: number): string =>
        photos[index].caption ?? `${salonName}: foto ${index + 1}`;

    return (
        <section
            aria-labelledby="galeria-titulo"
            className="flex flex-col gap-6"
        >
            <SectionHeading
                id="galeria-titulo"
                eyebrow="Trabajos recientes"
                title="Galería"
                aside={
                    photos.length === 1 ? '1 foto' : `${photos.length} fotos`
                }
            />

            <ul className={galleryGridClass(tiles.length)}>
                {tiles.map((photo, index) => (
                    <li
                        key={photo.url}
                        className={galleryTileClass(index, tiles.length)}
                    >
                        <GalleryTile
                            photo={photo}
                            alt={altFor(index)}
                            more={
                                index === tiles.length - 1
                                    ? photos.length - tiles.length
                                    : 0
                            }
                            onOpen={() => setOpenIndex(index)}
                        />
                    </li>
                ))}
            </ul>

            <PhotoViewer
                photos={photos}
                altFor={altFor}
                index={openIndex}
                onIndexChange={setOpenIndex}
            />
        </section>
    );
}
