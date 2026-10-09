import { Check, ExternalLink, Eye } from 'lucide-react';
import { BrandTheme } from '@/components/salon/brand-theme';
import { MiniSalonPage } from '@/components/theme-preview/mini-salon-page';
import type { MiniSalon } from '@/components/theme-preview/types';
import { cn } from '@/lib/utils';
import type { BrandTokens } from '@/types';
import type { ThemeChoice } from './types';

type Props = {
    choice: ThemeChoice;
    salon: MiniSalon;
    brand: BrandTokens | null;
    isSelected: boolean;
    /** The template the page uses today. */
    isCurrent: boolean;
    onSelect: () => void;
    /** Desktop only: look at it bigger without leaving the screen. */
    onPreview: () => void;
};

const ACTION_CLASS =
    'inline-flex min-h-11 items-center gap-1.5 self-start rounded-full px-1 text-sm font-medium whitespace-nowrap text-brand-text underline-offset-4 hover:underline';

/** One template as a radio card with a real thumbnail of the salon's page. */
export function ThemeOption({
    choice,
    salon,
    brand,
    isSelected,
    isCurrent,
    onSelect,
    onPreview,
}: Props) {
    const id = `theme-${choice.value}`;

    return (
        <div className="flex h-full flex-col gap-1">
            {/* Fills the row: the four cards end level and so do their links. */}
            <label className="group relative flex flex-1 cursor-pointer flex-col gap-3 rounded-2xl border bg-background p-2 transition-colors hover:border-border-strong has-checked:border-primary has-checked:bg-brand-soft has-focus-visible:ring-2 has-focus-visible:ring-ring">
                <input
                    type="radio"
                    name="theme"
                    value={choice.value}
                    checked={isSelected}
                    onChange={onSelect}
                    aria-labelledby={`${id}-label`}
                    aria-describedby={`${id}-tagline`}
                    className="sr-only"
                />
                {/* The frame stays in the panel's look; only the page inside wears the template. */}
                <div className="relative aspect-[3/5] overflow-hidden rounded-xl shadow-card ring-1 ring-border">
                    <BrandTheme
                        theme={choice.value}
                        brand={brand}
                        className="size-full"
                    >
                        <MiniSalonPage salon={salon} className="size-full" />
                    </BrandTheme>
                    <span
                        aria-hidden
                        className="absolute top-2 right-2 flex size-7 scale-75 items-center justify-center rounded-full bg-primary text-primary-foreground opacity-0 shadow-card transition duration-150 group-has-checked:scale-100 group-has-checked:opacity-100"
                    >
                        <Check className="size-4" strokeWidth={2.5} />
                    </span>
                </div>
                <div className="flex flex-col gap-1 px-1 pb-1">
                    <span
                        id={`${id}-label`}
                        className="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold"
                    >
                        {choice.label}
                        {isCurrent && (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                En uso
                            </span>
                        )}
                    </span>
                    <span
                        id={`${id}-tagline`}
                        className="text-sm text-pretty text-muted-foreground"
                    >
                        {choice.tagline}
                    </span>
                </div>
            </label>

            <a
                href={choice.previewUrl}
                target="_blank"
                rel="noopener"
                className={cn(ACTION_CLASS, 'md:hidden')}
            >
                Ver cómo quedaría
                <span className="sr-only"> con {choice.label}</span>
                <ExternalLink className="size-4" aria-hidden />
            </a>
            <button
                type="button"
                onClick={onPreview}
                className={cn(ACTION_CLASS, 'hidden md:inline-flex')}
            >
                <Eye className="size-4" aria-hidden />
                Ver cómo quedaría
                <span className="sr-only"> con {choice.label}</span>
            </button>
        </div>
    );
}
