import { BadgeCheck, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';
import { PHONE_MOCKUP } from './content';
import type { Appointment } from './content';

function AppointmentRow({ appointment }: { appointment: Appointment }) {
    const isNow = appointment.isNow === true;

    return (
        <li
            className={cn(
                'flex items-center gap-3 rounded-2xl border p-3',
                isNow
                    ? 'border-transparent bg-primary text-primary-foreground shadow-md'
                    : 'bg-card',
            )}
        >
            <span className="w-11 shrink-0 text-sm font-semibold tabular-nums">
                {appointment.time}
            </span>
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">
                    {appointment.service}
                </span>
                <span className="flex items-center justify-between gap-2">
                    <span
                        className={cn(
                            'truncate text-xs',
                            isNow
                                ? 'text-primary-foreground/80'
                                : 'text-muted-foreground',
                        )}
                    >
                        {appointment.client}
                    </span>
                    {isNow && (
                        <span className="shrink-0 rounded-full bg-primary-foreground/20 px-2 py-1 text-[0.6875rem] leading-none font-semibold">
                            {PHONE_MOCKUP.nowLabel}
                        </span>
                    )}
                </span>
            </span>
        </li>
    );
}

/** Illustrative phone built with markup (not an image) so it follows the theme tokens. */
export function PhoneMockup() {
    return (
        <div className="relative w-full max-w-[19rem]" aria-hidden>
            <div className="rounded-[2.75rem] border bg-card p-2.5 shadow-2xl shadow-primary/10">
                <div className="flex flex-col gap-4 rounded-[2.25rem] border bg-background px-4 pt-3 pb-7">
                    <div className="mx-auto h-1.5 w-20 rounded-full bg-muted" />

                    <div className="flex items-end justify-between px-1">
                        <p className="font-display text-3xl font-semibold">
                            {PHONE_MOCKUP.title}
                        </p>
                        <p className="pb-1 text-sm text-muted-foreground">
                            {PHONE_MOCKUP.date}
                        </p>
                    </div>

                    <ul className="flex flex-col gap-2.5">
                        {PHONE_MOCKUP.appointments.map((appointment) => (
                            <AppointmentRow
                                key={appointment.time}
                                appointment={appointment}
                            />
                        ))}
                    </ul>

                    <p className="flex items-center gap-1.5 px-1 text-xs text-muted-foreground">
                        <Clock className="size-3.5" />
                        {PHONE_MOCKUP.nextLabel}
                    </p>
                </div>
            </div>

            <div className="absolute -bottom-6 -left-2 flex items-center gap-2.5 rounded-2xl border bg-card px-3.5 py-2.5 shadow-lg sm:-left-10">
                <BadgeCheck className="size-5 text-success" />
                <div className="flex flex-col">
                    <span className="text-xs text-muted-foreground">
                        {PHONE_MOCKUP.depositLabel}
                    </span>
                    <span className="text-sm font-semibold tabular-nums">
                        {PHONE_MOCKUP.depositAmount}
                    </span>
                </div>
            </div>
        </div>
    );
}
