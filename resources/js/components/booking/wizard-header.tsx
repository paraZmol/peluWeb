import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { CHIP_ICON } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';

type Props = {
    title: string;
    /** Where the back arrow of the first step leads. */
    exit: { href: RouteDefinition<'get'>; label: string };
    steps: string[];
    step: number;
    onBack: () => void;
    /** The salon's logo next to its name (public wizard). */
    logoUrl?: string | null;
    /** Match a two-column content (public wizard on desktop). */
    wide?: boolean;
    className?: string;
};

const BACK = cn(CHIP_ICON, 'size-11');

/** Where you are in the booking and the way back, always in view. */
export function WizardHeader({
    title,
    exit,
    steps,
    step,
    onBack,
    logoUrl = null,
    wide = false,
    className,
}: Props) {
    const column = cn('mx-auto w-full px-4', wide ? 'max-w-5xl' : 'max-w-2xl');

    return (
        <header
            className={cn(
                'sticky top-0 z-20 border-b bg-background/85 backdrop-blur-lg backdrop-saturate-150',
                className,
            )}
        >
            <div className={cn(column, 'flex items-center gap-3 pt-3')}>
                {step === 0 ? (
                    <Link
                        href={exit.href}
                        aria-label={exit.label}
                        className={BACK}
                    >
                        <ArrowLeft aria-hidden />
                    </Link>
                ) : (
                    <button
                        type="button"
                        className={BACK}
                        aria-label="Paso anterior"
                        onClick={onBack}
                    >
                        <ArrowLeft aria-hidden />
                    </button>
                )}
                {logoUrl && (
                    <img
                        src={logoUrl}
                        alt=""
                        className="size-9 shrink-0 rounded-lg object-cover shadow-card"
                    />
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold">{title}</p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        Paso {step + 1} de {steps.length} · {steps[step]}
                    </p>
                </div>
            </div>
            {/* One segment per step, aligned with the content column. */}
            <div
                className={cn(column, 'flex gap-1.5 py-3')}
                role="progressbar"
                aria-valuemin={1}
                aria-valuemax={steps.length}
                aria-valuenow={step + 1}
                aria-label="Avance de la reserva"
            >
                {steps.map((label, index) => (
                    <span
                        key={label}
                        className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                    >
                        <span
                            className={cn(
                                'block h-full origin-left rounded-full bg-primary transition-transform duration-300',
                                index <= step ? 'scale-x-100' : 'scale-x-0',
                            )}
                        />
                    </span>
                ))}
            </div>
        </header>
    );
}
