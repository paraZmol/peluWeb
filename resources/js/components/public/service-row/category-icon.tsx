import {
    Baby,
    Droplets,
    Eye,
    Flower2,
    Hand,
    Palette,
    Scissors,
    Sparkles,
    WandSparkles,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Services have no photo yet (plantillas.md §6): a neutral icon of their
 * group stands in. Keyed by the group label the server sends; an unknown
 * label gets the generic sparkle.
 */
const BY_GROUP: Record<string, LucideIcon> = {
    'Cortes de hombre': Scissors,
    'Barba y afeitado': Scissors,
    'Cortes de mujer': Scissors,
    Infantil: Baby,
    Color: Palette,
    'Tratamientos y químicos': Droplets,
    'Peinados y eventos': WandSparkles,
    Uñas: Hand,
    'Cejas y pestañas': Eye,
    Depilación: Flower2,
    Estética: Flower2,
};

type Props = { group: string; className?: string };

/** Round thumbnail with the icon of the service's group, in the brand tint. */
export function CategoryIcon({ group, className }: Props) {
    const Icon = BY_GROUP[group] ?? Sparkles;

    return (
        <span
            aria-hidden
            className={cn(
                'flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text',
                className,
            )}
        >
            <Icon className="size-5" strokeWidth={1.75} />
        </span>
    );
}
