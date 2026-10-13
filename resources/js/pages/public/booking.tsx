import { Head } from '@inertiajs/react';
import { SelectionBar } from '@/components/booking/selection-bar';
import { SelectionRecap } from '@/components/booking/selection-recap';
import { StepConfirm } from '@/components/booking/step-confirm';
import { StepFrame } from '@/components/booking/step-frame';
import { StepServices } from '@/components/booking/step-services';
import { StepStaff } from '@/components/booking/step-staff';
import { StepTime } from '@/components/booking/step-time';
import { STEP_TITLES, stepSubtitle } from '@/components/booking/step-texts';
import type {
    BookableSalon,
    BookingDay,
    RepeatBooking,
    Viewer,
} from '@/components/booking/types';
import { WizardHeader } from '@/components/booking/wizard-header';
import type { PublicPromotion } from '@/components/public/types';
import { BrandTheme } from '@/components/salon/brand-theme';
import { ThemeBackdrop } from '@/components/salon/theme-backdrop';
import { useAvailability } from '@/hooks/use-availability';
import { STEPS, useBookingWizard } from '@/hooks/use-booking-wizard';
import { usePageTemplate } from '@/hooks/use-page-template';
import { askedDay } from '@/lib/waitlist';
import { show as salonPage } from '@/routes/salons';
import { availability as availabilityRoute } from '@/routes/salons/booking';

type Props = {
    salon: BookableSalon;
    days: BookingDay[];
    isOpen: boolean;
    viewer: Viewer;
    preselectedServiceId: number | null;
    repeat: RepeatBooking | null;
    promotions?: PublicPromotion[];
};

export default function Booking({
    salon,
    days,
    isOpen,
    viewer,
    preselectedServiceId,
    repeat,
    promotions = [],
}: Props) {
    const search = typeof window === 'undefined' ? '' : window.location.search;
    const wizard = useBookingWizard({
        salon,
        preselectedServiceId,
        repeat,
        resume: new URLSearchParams(search).has('continuar'),
        startDate: askedDay(search, days),
        promotions,
    });
    const firstOpenDay = days.find((day) => !day.closed)?.date ?? null;
    const availability = useAvailability({
        url: availabilityRoute.url(salon.slug),
        selections: wizard.selections,
        staffId: wizard.staffId,
        refresh: wizard.refresh,
        // Nothing to ask until a professional was chosen.
        date: wizard.step >= 2 ? (wizard.date ?? firstOpenDay) : null,
    });
    const staff =
        wizard.step === 2
            ? salon.staff.find((person) => person.id === wizard.staffId)
            : undefined;
    // Choosing who and when: what was picked stays at hand.
    const hasRecap = wizard.step === 1 || wizard.step === 2;

    usePageTemplate(salon);

    return (
        <BrandTheme
            brand={salon.brand}
            theme={salon.theme}
            className="min-h-svh bg-background text-foreground"
        >
            <Head title={`Reservar en ${salon.name}`} />
            <ThemeBackdrop />
            <WizardHeader
                title={salon.name}
                logoUrl={salon.logoUrl}
                wide
                exit={{
                    href: salonPage(salon.slug),
                    label: `Volver a ${salon.name}`,
                }}
                steps={STEPS}
                step={wizard.step}
                onBack={() => wizard.goTo(wizard.step - 1)}
            />

            <main className="mx-auto w-full max-w-5xl overflow-x-clip px-4 pt-6 pb-32 sm:pt-10">
                {!isOpen ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Este salón no está recibiendo reservas en línea por
                        ahora.
                    </p>
                ) : (
                    <StepFrame
                        step={wizard.step}
                        direction={wizard.direction}
                        title={STEP_TITLES[wizard.step]}
                        subtitle={stepSubtitle(
                            wizard.step,
                            staff?.name ?? null,
                        )}
                        narrow={wizard.step === 3}
                        recap={
                            hasRecap && (
                                <SelectionRecap
                                    wizard={wizard}
                                    staffName={staff?.name ?? null}
                                    staffPhotoUrl={staff?.photoUrl}
                                />
                            )
                        }
                    >
                        {wizard.step === 0 && (
                            <StepServices salon={salon} wizard={wizard} />
                        )}
                        {wizard.step === 1 && <StepStaff wizard={wizard} />}
                        {wizard.step === 2 && (
                            <StepTime
                                days={days}
                                wizard={wizard}
                                availability={availability}
                                waitlist={{ salonSlug: salon.slug, viewer }}
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
                    </StepFrame>
                )}
            </main>

            {isOpen && wizard.step === 0 && wizard.chosen.length > 0 && (
                <SelectionBar wizard={wizard} wide />
            )}
        </BrandTheme>
    );
}
