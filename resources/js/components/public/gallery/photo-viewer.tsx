import { ChevronLeft, ChevronRight } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import type { KeyboardEvent } from 'react';
import { usePortalTheme } from '@/components/salon/portal-theme';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { CHIP_ICON } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import type { PublicPhoto } from '../types';

/** How far (px) a swipe must travel to change photo. */
const SWIPE_PX = 60;

type Props = {
    photos: PublicPhoto[];
    altFor: (index: number) => string;
    /** The photo shown; null while closed. */
    index: number | null;
    onIndexChange: (index: number | null) => void;
};

/** Full-screen viewer for every photo of the salon: swipe, arrows, "3 de 50". */
export function PhotoViewer({ photos, altFor, index, onIndexChange }: Props) {
    const reduceMotion = useReducedMotion();
    const portalTheme = usePortalTheme();
    const count = photos.length;
    const go = (step: number) =>
        index !== null && onIndexChange((index + step + count) % count);
    const onKeyDown = (event: KeyboardEvent) => {
        const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];

        if (step !== undefined) {
            event.preventDefault();
            go(step);
        }
    };
    const arrow = cn(CHIP_ICON, 'size-11');

    return (
        <Dialog
            open={index !== null}
            onOpenChange={(open) => !open && onIndexChange(null)}
        >
            {/* Rendered outside the page: wear the page's template again. */}
            <DialogContent
                {...portalTheme}
                onKeyDown={onKeyDown}
                className={cn(
                    portalTheme.className,
                    'max-w-[calc(100%-1rem)] gap-3 overflow-hidden p-3 sm:max-w-4xl sm:p-4',
                )}
            >
                {index !== null && (
                    <>
                        <DialogTitle className="sr-only">
                            {altFor(index)}
                        </DialogTitle>
                        <motion.img
                            key={photos[index].url}
                            src={photos[index].url}
                            alt={altFor(index)}
                            loading="lazy"
                            drag={count > 1 ? 'x' : false}
                            dragConstraints={{ left: 0, right: 0 }}
                            dragElastic={0.4}
                            onDragEnd={(_, info) => {
                                if (Math.abs(info.offset.x) > SWIPE_PX) {
                                    go(info.offset.x < 0 ? 1 : -1);
                                }
                            }}
                            initial={{
                                opacity: 0,
                                scale: reduceMotion ? 1 : 0.98,
                            }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.2 }}
                            className="max-h-[72svh] w-full touch-pan-y rounded-2xl object-contain select-none"
                        />
                        <div className="flex items-center gap-3">
                            {count > 1 && (
                                <button
                                    type="button"
                                    className={arrow}
                                    aria-label="Foto anterior"
                                    onClick={() => go(-1)}
                                >
                                    <ChevronLeft aria-hidden />
                                </button>
                            )}
                            <DialogDescription className="min-w-0 flex-1 text-center text-sm">
                                {photos[index].caption && (
                                    <span className="block font-medium text-foreground">
                                        {photos[index].caption}
                                    </span>
                                )}
                                <span className="tabular-nums">
                                    {index + 1} de {count}
                                </span>
                            </DialogDescription>
                            {count > 1 && (
                                <button
                                    type="button"
                                    className={arrow}
                                    aria-label="Foto siguiente"
                                    onClick={() => go(1)}
                                >
                                    <ChevronRight aria-hidden />
                                </button>
                            )}
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
