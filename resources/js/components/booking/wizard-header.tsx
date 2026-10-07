import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { STEPS } from '@/hooks/use-booking-wizard';
import { cn } from '@/lib/utils';
import { show as salonPage } from '@/routes/salons';

type Props = {
    salonName: string;
    slug: string;
    step: number;
    onBack: () => void;
};

/** Where you are in the booking and the way back, always in view. */
export function WizardHeader({ salonName, slug, step, onBack }: Props) {
    return (
        <header className="sticky top-0 z-20 border-b bg-background/90 backdrop-blur">
            <div className="mx-auto flex w-full max-w-2xl items-center gap-1 px-2 pt-2">
                {step === 0 ? (
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="size-11 rounded-full"
                    >
                        <Link
                            href={salonPage(slug)}
                            aria-label={`Volver a ${salonName}`}
                        >
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
                    <p className="truncate text-sm font-medium">{salonName}</p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        Paso {step + 1} de {STEPS.length} · {STEPS[step]}
                    </p>
                </div>
            </div>
            {/* One segment per step, aligned with the content column. */}
            <div
                className="mx-auto flex w-full max-w-2xl gap-1.5 px-4 pt-2 pb-3"
                role="progressbar"
                aria-valuemin={1}
                aria-valuemax={STEPS.length}
                aria-valuenow={step + 1}
                aria-label="Avance de la reserva"
            >
                {STEPS.map((label, index) => (
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
