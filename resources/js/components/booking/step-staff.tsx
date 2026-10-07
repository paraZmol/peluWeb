import { ChevronRight, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { useInitials } from '@/hooks/use-initials';

type Props = { wizard: BookingWizard };

export function StepStaff({ wizard }: Props) {
    const getInitials = useInitials();

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
        avatar: ReactNode,
        name: string,
        detail: string | null,
    ) => (
        <li key={key}>
            <button
                type="button"
                onClick={() => wizard.chooseStaff(staffId)}
                className="flex min-h-18 w-full items-center gap-4 rounded-xl border bg-card p-4 text-left transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {avatar}
                <span className="min-w-0 flex-1">
                    <span className="block font-medium break-words">
                        {name}
                    </span>
                    {detail && (
                        <span className="block text-sm break-words text-muted-foreground">
                            {detail}
                        </span>
                    )}
                </span>
                <ChevronRight
                    className="size-5 shrink-0 text-muted-foreground"
                    aria-hidden
                />
            </button>
        </li>
    );

    return (
        <ul className="flex flex-col gap-2">
            {wizard.eligibleStaff.length > 1 &&
                option(
                    'anyone',
                    null,
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
                        <Users className="size-5" aria-hidden />
                    </span>,
                    'Quien esté disponible',
                    'Verás más horarios para elegir',
                )}
            {wizard.eligibleStaff.map((person) =>
                option(
                    String(person.id),
                    person.id,
                    <Avatar className="size-12">
                        <AvatarImage
                            src={person.photoUrl ?? undefined}
                            alt=""
                        />
                        <AvatarFallback>
                            {getInitials(person.name)}
                        </AvatarFallback>
                    </Avatar>,
                    person.name,
                    person.title,
                ),
            )}
        </ul>
    );
}
