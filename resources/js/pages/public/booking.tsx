import { Head } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { SelectionBar } from '@/components/booking/selection-bar';
import { SelectionRecap } from '@/components/booking/selection-recap';
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
import { useAvailability } from '@/hooks/use-availability';
import { useBookingWizard } from '@/hooks/use-booking-wizard';

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
    const staffName =
        salon.staff.find((person) => person.id === wizard.staffId)?.name ??
        null;
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

            <main className="mx-auto w-full max-w-2xl overflow-x-clip px-4 pt-6 pb-32 sm:pt-8">
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
                            <h1 className="font-display text-2xl font-semibold text-balance sm:text-3xl">
                                {TITLES[wizard.step]}
                            </h1>
                            {(wizard.step === 1 || wizard.step === 2) && (
                                <SelectionRecap
                                    wizard={wizard}
                                    staffName={
                                        wizard.step === 2 ? staffName : null
                                    }
                                />
                            )}
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
                <SelectionBar wizard={wizard} />
            )}
        </BrandTheme>
    );
}
