import { Link } from '@inertiajs/react';
import {
    BookHeart,
    CalendarClock,
    CalendarDays,
    ChartColumn,
    Contact,
    HandCoins,
    Ticket,
    LayoutGrid,
    ReceiptText,
    Scissors,
    ShieldCheck,
    Star,
    Store,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { SalonSwitcher } from '@/components/salon/salon-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { dashboard } from '@/routes';
import { index as agenda } from '@/routes/agenda';
import { index as myBookings } from '@/routes/bookings';
import { index as cash } from '@/routes/cash';
import { index as clients } from '@/routes/clients';
import { index as commissions } from '@/routes/commissions';
import { index as expenses } from '@/routes/expenses';
import { index as myData } from '@/routes/my-data';
import { index as reports } from '@/routes/reports';
import { index as reviews } from '@/routes/reviews';
import { edit as editSalon } from '@/routes/salon';
import { index as services } from '@/routes/services';
import { index as staff } from '@/routes/staff';
import { mine as mySchedule } from '@/routes/staff/schedule';
import { index as team } from '@/routes/team';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { currentSalon, isOwner, can } = useCurrentSalon();

    const mainNavItems: NavItem[] = [
        { title: 'Panel', href: dashboard(), icon: LayoutGrid },
        ...(currentSalon
            ? [{ title: 'Agenda', href: agenda(), icon: CalendarDays }]
            : []),
        ...(currentSalon?.hasStaffProfile
            ? [
                  {
                      title: 'Mi horario',
                      href: mySchedule(),
                      icon: CalendarClock,
                  },
              ]
            : []),
        ...(can('manage_salon_profile')
            ? [{ title: 'Mi salón', href: editSalon(), icon: Store }]
            : []),
        ...(can('manage_services')
            ? [{ title: 'Servicios', href: services(), icon: Scissors }]
            : []),
        ...(can('manage_vip_clients') || can('view_all_schedules')
            ? [{ title: 'Clientes', href: clients(), icon: BookHeart }]
            : []),
        // Same permission as "Mi salón": the server asks for `update` on the salon.
        ...(can('manage_salon_profile')
            ? [{ title: 'Reseñas', href: reviews(), icon: Star }]
            : []),
        ...(can('manage_cash')
            ? [
                  { title: 'Caja', href: cash(), icon: Wallet },
                  { title: 'Gastos', href: expenses(), icon: ReceiptText },
                  {
                      title: 'Comisiones',
                      href: commissions(),
                      icon: HandCoins,
                  },
              ]
            : []),
        ...(can('view_reports')
            ? [{ title: 'Reportes', href: reports(), icon: ChartColumn }]
            : []),
        ...(isOwner
            ? [
                  { title: 'Profesionales', href: staff(), icon: Contact },
                  { title: 'Equipo', href: team(), icon: Users },
              ]
            : []),
        // Everyone can also be a client of some salon.
        { title: 'Mis reservas', href: myBookings(), icon: Ticket },
        { title: 'Mis datos', href: myData(), icon: ShieldCheck },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                {currentSalon ? (
                    <SalonSwitcher />
                ) : (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={dashboard()} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
