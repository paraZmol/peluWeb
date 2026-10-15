import { Link } from '@inertiajs/react';
import {
    BookHeart,
    CalendarClock,
    CalendarDays,
    ChartColumn,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { index as agenda } from '@/routes/agenda';
import { index as cash } from '@/routes/cash';
import { index as clients } from '@/routes/clients';
import { index as reports } from '@/routes/reports';
import { mine as mySchedule } from '@/routes/staff/schedule';
import { index as team } from '@/routes/team';

type Shortcut = {
    title: string;
    text: string;
    href: ReturnType<typeof agenda>;
    icon: LucideIcon;
};

/**
 * The first screen of who works in a salon: one card per thing their
 * permissions open, the same rules as the menu.
 */
export function PanelShortcuts() {
    const { currentSalon, isOwner, can } = useCurrentSalon();

    if (!currentSalon) {
        return null;
    }

    const shortcuts: (Shortcut | false)[] = [
        {
            title: 'Agenda de hoy',
            text: 'Las citas del día y lo que falta confirmar.',
            href: agenda(),
            icon: CalendarDays,
        },
        currentSalon.hasStaffProfile && {
            title: 'Mi horario',
            text: 'Los días y horas en que atiendes.',
            href: mySchedule(),
            icon: CalendarClock,
        },
        (can('manage_vip_clients') || can('view_all_schedules')) && {
            title: 'Clientes',
            text: 'Busca a una persona y abre su ficha.',
            href: clients(),
            icon: BookHeart,
        },
        can('manage_cash') && {
            title: 'Caja',
            text: 'Lo cobrado hoy y el cierre del día.',
            href: cash(),
            icon: Wallet,
        },
        can('view_reports') && {
            title: 'Reportes',
            text: 'Ingresos, servicios y ocupación.',
            href: reports(),
            icon: ChartColumn,
        },
        isOwner && {
            title: 'Tu equipo',
            text: `Invita a quienes atienden en ${currentSalon.name}.`,
            href: team(),
            icon: Users,
        },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {shortcuts
                .filter((shortcut) => shortcut !== false)
                .map(({ title, text, href, icon: Icon }) => (
                    <Link
                        key={title}
                        href={href}
                        className="flex flex-col gap-2 rounded-xl border bg-card p-5 transition-colors hover:bg-accent"
                    >
                        <Icon className="size-5 text-primary" aria-hidden />
                        <h2 className="font-semibold">{title}</h2>
                        <p className="text-sm text-muted-foreground">{text}</p>
                    </Link>
                ))}
        </div>
    );
}
