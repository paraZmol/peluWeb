import { Head } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { StepConfirm } from '@/components/booking/step-confirm';
import { StepServices } from '@/components/booking/step-services';
import { StepStaff } from '@/components/booking/step-staff';
import { StepTime } from '@/components/booking/step-time';
import type {
    BookableSalon,
    BookingDay,
    Viewer,
} from '@/components/booking/types';
import { WizardHeader } from '@/components/booking/wizard-header';
import { BrandTheme } from '@/components/salon/brand-theme';
import { Button } from '@/components/ui/button';
import { useAvailability } from '@/hooks/use-availability';
import { useBookingWizard } from '@/hooks/use-booking-wizard';
import { formatPrice } from '@/lib/format';

type Props = {
    salon: BookableSalon;
    days: BookingDay[];
    isOpen: boolean;
    viewer: Viewer;
    preselectedServiceId: number | null;
};

const TITLES = [
    '¿Qué te quieres hacer?',
    '¿Con quién?',
    '¿Cuándo?',
    'Revisa y confirma',
];

export default function Booking({
    salon,
    days,
    isOpen,
    viewer,
    preselectedServiceId,
}: Props) {
    const reduceMotion = useReducedMotion();
    const resume =
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('continuar');
    const wizard = useBookingWizard({ salon, preselectedServiceId, resume });
    const firstOpenDay = days.find((day) => !day.closed)?.date ?? null;
    const availability = useAvailability({
        slug: salon.slug,
        selections: wizard.selections,
        staffId: wizard.staffId,
        // Nothing to ask until a professional was chosen.
        date: wizard.step >= 2 ? (wizard.date ?? firstOpenDay) : null,
    });
    const canContinue = wizard.chosen.length > 0 && !wizard.hasOnlyAddons;
    const offset = reduceMotion ? 0 : 32 * wizard.direction;

    return (
        <BrandTheme
            brand={salon.brand}
            className="min-h-svh bg-background text-foreground"
        >
            <Head title={`Reservar en ${salon.name}`} />
            <WizardHeader
                salonName={salon.name}
                slug={salon.slug}
                step={wizard.step}
                onBack={() => wizard.goTo(wizard.step - 1)}
            />

            <main className="mx-auto w-full max-w-2xl overflow-x-clip px-4 pt-6 pb-32">
                {!isOpen ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Este salón no está recibiendo reservas en línea por
                        ahora.
                    </p>
                ) : (
                    <AnimatePresence mode="wait" initial={false}>
                        <motion.div
                            key={wizard.step}
                            initial={{ opacity: 0, x: offset }}
                            animate={{ opacity: 1, x: 0 }}
                            exit={{ opacity: 0, x: -offset }}
                            transition={{
                                duration: 0.25,
                                ease: [0.22, 1, 0.36, 1],
                            }}
                            className="flex flex-col gap-5"
                        >
                            <h1 className="font-display text-2xl font-semibold">
                                {TITLES[wizard.step]}
                            </h1>
                            {wizard.step === 0 && (
                                <StepServices salon={salon} wizard={wizard} />
                            )}
                            {wizard.step === 1 && <StepStaff wizard={wizard} />}
                            {wizard.step === 2 && (
                                <StepTime
                                    days={days}
                                    wizard={wizard}
                                    availability={availability}
                                />
                            )}
                            {wizard.step === 3 && (
                                <StepConfirm
                                    salon={salon}
                                    wizard={wizard}
                                    quote={availability.quote}
                                    viewer={viewer}
                                />
                            )}
                        </motion.div>
                    </AnimatePresence>
                )}
            </main>

            {isOpen && wizard.step === 0 && wizard.chosen.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-background pb-[env(safe-area-inset-bottom)]">
                    <div className="mx-auto flex w-full max-w-2xl items-center gap-3 px-4 py-3">
                        <p className="min-w-0 flex-1 text-sm" role="status">
                            <span className="block font-medium">
                                {wizard.chosen.length}{' '}
                                {wizard.chosen.length === 1
                                    ? 'servicio'
                                    : 'servicios'}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                desde {formatPrice(wizard.estimate)}
                            </span>
                        </p>
                        <Button
                            size="lg"
                            className="h-12 rounded-full px-8"
                            disabled={!canContinue}
                            onClick={() => wizard.goTo(1)}
                        >
                            Continuar
                        </Button>
                    </div>
                </div>
            )}
        </BrandTheme>
    );
}
