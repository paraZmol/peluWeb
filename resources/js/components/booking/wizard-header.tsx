import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { STEPS } from '@/hooks/use-booking-wizard';
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
        <header className="sticky top-0 z-20 border-b bg-background">
            <div className="mx-auto flex w-full max-w-2xl items-center gap-2 px-2 py-2">
                {step === 0 ? (
                    <Button asChild variant="ghost" size="icon">
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
                        aria-label="Paso anterior"
                        onClick={onBack}
                    >
                        <ChevronLeft />
                    </Button>
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{salonName}</p>
                    <p className="text-xs text-muted-foreground">
                        Paso {step + 1} de {STEPS.length} · {STEPS[step]}
                    </p>
                </div>
            </div>
            <div
                className="h-1 bg-muted"
                role="progressbar"
                aria-valuemin={1}
                aria-valuemax={STEPS.length}
                aria-valuenow={step + 1}
                aria-label="Avance de la reserva"
            >
                <div
                    className="h-full bg-primary transition-[width] duration-300"
                    style={{ width: `${((step + 1) / STEPS.length) * 100}%` }}
                />
            </div>
        </header>
    );
}
