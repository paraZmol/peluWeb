import { BrandTheme } from '@/components/salon/brand-theme';
import {
    SALON_THEME_ORDER,
    SALON_THEMES,
} from '@/components/salon/salon-themes';
import { cn } from '@/lib/utils';
import type { SalonTheme } from '@/types';
import { SHOWCASE } from './content';

type Props = {
    value: SalonTheme;
    onChange: (theme: SalonTheme) => void;
};

/** Four chips, one per template, each with a dot of that template's accent. */
export function TemplatePicker({ value, onChange }: Props) {
    return (
        <div
            role="group"
            aria-label={SHOWCASE.pickerLabel}
            className="flex flex-wrap gap-2"
        >
            {SALON_THEME_ORDER.map((theme) => {
                const isActive = theme === value;

                return (
                    <button
                        key={theme}
                        type="button"
                        aria-pressed={isActive}
                        onClick={() => onChange(theme)}
                        className={cn(
                            'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-colors',
                            isActive
                                ? 'border-transparent bg-foreground text-background'
                                : 'bg-card text-foreground hover:border-border-strong',
                        )}
                    >
                        <BrandTheme
                            theme={theme}
                            brand={null}
                            className="contents"
                        >
                            <span
                                aria-hidden
                                className="size-3 rounded-full bg-primary ring-2 ring-card"
                            />
                        </BrandTheme>
                        {SALON_THEMES[theme].label}
                    </button>
                );
            })}
        </div>
    );
}
