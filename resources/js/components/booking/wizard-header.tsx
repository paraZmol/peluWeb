import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';

type Props = {
    title: string;
    /** Where the back arrow of the first step leads. */
    exit: { href: RouteDefinition<'get'>; label: string };
    steps: string[];
    step: number;
    onBack: () => void;
    className?: string;
};

/** Where you are in the booking and the way back, always in view. */
export function WizardHeader({
    title,
    exit,
    steps,
    step,
    onBack,
    className,
}: Props) {
    return (
        <header
            className={cn(
                'sticky top-0 z-20 border-b bg-background/90 backdrop-blur',
                className,
            )}
        >
            <div className="mx-auto flex w-full max-w-2xl items-center gap-1 px-2 pt-2">
                {step === 0 ? (
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="size-11 rounded-full"
                    >
                        <Link href={exit.href} aria-label={exit.label}>
                            <ChevronLeft />
                        </Link>
                    </Button>
                ) : (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-11 rounded-full"
                        aria-label="Paso anterior"
                        onClick={onBack}
                    >
                        <ChevronLeft />
                    </Button>
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{title}</p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        Paso {step + 1} de {steps.length} · {steps[step]}
                    </p>
                </div>
            </div>
            {/* One segment per step, aligned with the content column. */}
            <div
                className="mx-auto flex w-full max-w-2xl gap-1.5 px-4 pt-2 pb-3"
                role="progressbar"
                aria-valuemin={1}
                aria-valuemax={steps.length}
                aria-valuenow={step + 1}
                aria-label="Avance de la reserva"
            >
                {steps.map((label, index) => (
                    <span
                        key={label}
                        className={cn(
                            'h-1 flex-1 rounded-full transition-colors duration-300',
                            index <= step ? 'bg-primary' : 'bg-muted',
                        )}
                    />
                ))}
            </div>
        </header>
    );
}
