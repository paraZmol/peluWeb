import type { PublicPhoto } from '../types';

type Props = {
    photo: PublicPhoto;
    alt: string;
    /** Photos left out of the mosaic, announced on the last tile. */
    more: number;
    onOpen: () => void;
};

/** One photo of the mosaic; tapping it opens the viewer. */
export function GalleryTile({ photo, alt, more, onOpen }: Props) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="group block size-full rounded-[inherit] text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/60"
            aria-label={
                more > 0 ? `Ver las ${more} fotos más` : `Ampliar: ${alt}`
            }
        >
            <span className="photo-frame block size-full rounded-[inherit]">
                <img
                    src={photo.url}
                    alt={alt}
                    loading="lazy"
                    className="transition-transform duration-500 motion-safe:group-hover:scale-105"
                />
                {photo.caption && more === 0 && (
                    <span className="absolute inset-x-0 bottom-0 z-10 bg-linear-to-t from-veil/85 to-transparent px-3 pt-8 pb-2.5 text-sm font-semibold text-on-photo">
                        {photo.caption}
                    </span>
                )}
                {more > 0 && (
                    <span className="absolute inset-0 z-10 flex items-center justify-center bg-veil/55 display-type text-3xl text-on-photo">
                        +{more}
                    </span>
                )}
            </span>
        </button>
    );
}
