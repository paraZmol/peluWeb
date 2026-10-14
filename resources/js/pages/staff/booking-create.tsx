import { Head } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { StepClient } from '@/components/agenda/step-client';
import { SelectionBar } from '@/components/booking/selection-bar';
import { SelectionRecap } from '@/components/booking/selection-recap';
import { StepServices } from '@/components/booking/step-services';
import { StepStaff } from '@/components/booking/step-staff';
import { StepTime } from '@/components/booking/step-time';
import type { BookableSalon, BookingDay } from '@/components/booking/types';
import { WizardHeader } from '@/components/booking/wizard-header';
import { useAvailability } from '@/hooks/use-availability';
import type { PublicPromotion } from '@/components/public/types';
import { useBookingWizard } from '@/hooks/use-booking-wizard';
import { index } from '@/routes/agenda';
import {
    availability as availabilityRoute,
    create,
} from '@/routes/agenda/bookings';

type Props = {
    salon: BookableSalon;
    days: BookingDay[];
    /** What the salon is running today: the same prices a client is shown. */
    promotions?: PublicPromotion[];
};

const STEPS = ['Servicios', 'Profesional', 'Día y hora', 'Cliente'];
const TITLES = [
    '¿Qué se va a hacer?',
    '¿Con quién?',
    '¿Cuándo?',
    '¿Para quién?',
];

/** The team books for someone who called, wrote or walked in. */
export default function BookingCreate({ salon, days, promotions = [] }: Props) {
    const reduceMotion = useReducedMotion();
    const wizard = useBookingWizard({ salon, persist: false, promotions });
    const firstOpenDay = days.find((day) => !day.closed)?.date ?? null;
    const availability = useAvailability({
        url: availabilityRoute.url(),
        selections: wizard.selections,
        staffId: wizard.staffId,
        refresh: wizard.refresh,
        // Nothing to ask until a professional was chosen.
        date: wizard.step >= 2 ? (wizard.date ?? firstOpenDay) : null,
    });
    const staffName =
        salon.staff.find((person) => person.id === wizard.staffId)?.name ??
        null;
    const offset = reduceMotion ? 0 : 32 * wizard.direction;

    return (
        <>
            <Head title="Nueva cita" />
            <WizardHeader
                title="Nueva cita"
                exit={{ href: index(), label: 'Volver a la agenda' }}
                steps={STEPS}
                step={wizard.step}
                onBack={() => wizard.goTo(wizard.step - 1)}
            />

            <div className="mx-auto w-full max-w-2xl flex-1 overflow-x-clip px-4 pt-6 pb-10">
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
                        <h1 className="text-2xl font-semibold text-balance">
                            {TITLES[wizard.step]}
                        </h1>
                        {(wizard.step === 1 || wizard.step === 2) && (
                            <SelectionRecap
                                wizard={wizard}
                                staffName={wizard.step === 2 ? staffName : null}
                            />
                        )}
                        {wizard.step === 0 && (
                            <StepServices
                                salon={salon}
                                wizard={wizard}
                                empty="Todavía no hay servicios activos para agendar. Agrégalos en Servicios."
                            />
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
                            <StepClient
                                salon={salon}
                                wizard={wizard}
                                quote={availability.quote}
                            />
                        )}
                    </motion.div>
                </AnimatePresence>
            </div>

            {wizard.step === 0 && wizard.chosen.length > 0 && (
                <SelectionBar wizard={wizard} inline />
            )}
        </>
    );
}

BookingCreate.layout = {
    breadcrumbs: [
        { title: 'Agenda', href: index() },
        { title: 'Nueva cita', href: create() },
    ],
};
