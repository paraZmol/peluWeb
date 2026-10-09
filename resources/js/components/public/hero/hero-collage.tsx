import { cn } from '@/lib/utils';

/** Where each photo of the collage goes, and its shape (arch, circle, leaf). */
const SPOTS = [
    'left-0 top-0 h-full w-[64%] rounded-portrait',
    'right-0 top-2.5 aspect-square w-[42%] rounded-full',
    'right-[4%] bottom-0 aspect-4/5 w-[31%] rounded-tile',
];

type Props = { photos: string[]; className?: string };

/** Vibrante: up to three photos with mixed shapes over the color block. */
export function HeroCollage({ photos, className }: Props) {
    if (photos.length === 0) {
        return null;
    }

    return (
        <div className={cn('relative h-85 lg:h-125', className)}>
            {photos.slice(0, SPOTS.length).map((url, index) => (
                // photo-frame sets its own position: wrap it to place it.
                <div key={url} className={cn('absolute', SPOTS[index])}>
                    <div className="photo-frame size-full rounded-[inherit] shadow-raised">
                        <img
                            src={url}
                            alt=""
                            fetchPriority={index === 0 ? 'high' : 'auto'}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}
