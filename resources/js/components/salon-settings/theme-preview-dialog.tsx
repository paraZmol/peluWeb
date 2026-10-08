import { Check, ExternalLink } from 'lucide-react';
import { BrandTheme } from '@/components/salon/brand-theme';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import type { BrandTokens } from '@/types';
import { ThemePreviewFrame } from './theme-preview-frame';
import type { ThemeChoice } from './types';

type Props = {
    choice: ThemeChoice | null;
    brand: BrandTokens | null;
    isSelected: boolean;
    onUse: (choice: ThemeChoice) => void;
    onClose: () => void;
};

/**
 * Desktop look at the real public page with one template, inside a phone,
 * with the choice and a link to open it full size in another tab.
 */
export function ThemePreviewDialog({
    choice,
    brand,
    isSelected,
    onUse,
    onClose,
}: Props) {
    return (
        <Dialog
            open={choice !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {choice && (
                <DialogContent className="sm:max-w-3xl">
                    <BrandTheme brand={brand} className="contents">
                        <div className="grid items-center gap-10 md:grid-cols-[auto_1fr]">
                            <div className="flex flex-col gap-4">
                                <DialogTitle className="font-display text-3xl font-semibold">
                                    {choice.label}
                                </DialogTitle>
                                <DialogDescription className="text-base text-pretty">
                                    {choice.tagline}
                                </DialogDescription>
                                <p className="text-sm text-pretty text-muted-foreground">
                                    Así verían tus clientes tu página en el
                                    celular, con tus servicios y tu equipo.
                                    Donde aún no tienes fotos, usamos fotos de
                                    muestra.
                                </p>
                                <div className="mt-2 flex flex-wrap gap-3">
                                    {isSelected ? (
                                        <p className="inline-flex h-9 items-center gap-2 rounded-full bg-brand-soft px-4 text-sm font-medium text-brand-text">
                                            <Check
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Es el que elegiste
                                        </p>
                                    ) : (
                                        <Button
                                            type="button"
                                            className="rounded-full"
                                            onClick={() => onUse(choice)}
                                        >
                                            Elegir este diseño
                                        </Button>
                                    )}
                                    <a
                                        href={choice.previewUrl}
                                        target="_blank"
                                        rel="noopener"
                                        className={cn(
                                            buttonVariants({
                                                variant: 'outline',
                                            }),
                                            'rounded-full',
                                        )}
                                    >
                                        Abrir la página completa
                                        <ExternalLink aria-hidden />
                                    </a>
                                </div>
                            </div>

                            {/* After the actions in the DOM (focus lands on them, Escape still closes); shown first. */}
                            <div className="order-first">
                                <ThemePreviewFrame
                                    key={choice.value}
                                    choice={choice}
                                    brand={brand}
                                />
                            </div>
                        </div>
                    </BrandTheme>
                </DialogContent>
            )}
        </Dialog>
    );
}
