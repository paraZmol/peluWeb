import { ChevronRight, Tag, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { PromoTag } from '@/components/public/promo-tag';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { useInitials } from '@/hooks/use-initials';
import { staffPromotionHint, staffWithPromo } from '@/lib/promo-staff';
import { cn } from '@/lib/utils';

type Props = { wizard: BookingWizard };

const PORTRAIT =
    'flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-portrait bg-brand-soft text-brand-text';

export function StepStaff({ wizard }: Props) {
    const getInitials = useInitials();
    const chosenIds = wizard.chosen.map((service) => service.id);
    // Promotions of the chosen services that hold only with some of the team.
    const withPromo = staffWithPromo(wizard.promotions, chosenIds);
    const hint = staffPromotionHint(wizard.promotions, chosenIds);

    if (wizard.eligibleStaff.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                Nadie del equipo hace todos esos servicios juntos. Vuelve atrás
                y resérvalos por separado.
            </p>
        );
    }

    const option = (
        key: string,
        staffId: number | null,
        portrait: ReactNode,
        name: string,
        detail: string | null,
    ) => (
        <li key={key}>
            <button
                type="button"
                onClick={() => wizard.chooseStaff(staffId)}
                className={cn(
                    'flex min-h-22 w-full items-center gap-4 rounded-xl p-3 pr-4 text-left skin-card transition-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring active:scale-[0.98]',
                    // Already picked, e.g. by the promotion it came from.
                    staffId !== null &&
                        staffId === wizard.staffId &&
                        'ring-2 ring-primary',
                )}
            >
                {portrait}
                <span className="min-w-0 flex-1">
                    <span className="block font-semibold break-words">
                        {name}
                        {staffId !== null && withPromo.has(staffId) && (
                            <PromoTag label="con promo" className="ml-2" />
                        )}
                    </span>
                    {detail && (
                        <span className="block text-sm break-words text-muted-foreground">
                            {detail}
                        </span>
                    )}
                </span>
                <ChevronRight
                    className="size-5 shrink-0 text-brand-text"
                    aria-hidden
                />
            </button>
        </li>
    );

    return (
        // Two columns only where the column itself is wide (public desktop).
        <div className="@container flex flex-col gap-4">
            {hint && (
                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                    <Tag
                        className="mt-0.5 size-4 shrink-0 text-brand-text"
                        aria-hidden
                    />
                    {hint}
                </p>
            )}
            <ul className="grid gap-2.5 @2xl:grid-cols-2">
                {wizard.eligibleStaff.length > 1 &&
                    option(
                        'anyone',
                        null,
                        <span aria-hidden className={PORTRAIT}>
                            <Users className="size-6" />
                        </span>,
                        'Quien esté disponible',
                        'Verás más horarios para elegir',
                    )}
                {wizard.eligibleStaff.map((person) =>
                    option(
                        String(person.id),
                        person.id,
                        <span aria-hidden className={PORTRAIT}>
                            {person.photoUrl ? (
                                <img
                                    src={person.photoUrl}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            ) : (
                                <span className="display-type text-xl">
                                    {getInitials(person.name)}
                                </span>
                            )}
                        </span>,
                        person.name,
                        person.title,
                    ),
                )}
            </ul>
        </div>
    );
}
