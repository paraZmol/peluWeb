import { Link } from '@inertiajs/react';
import {
    BookHeart,
    CalendarClock,
    CalendarDays,
    Contact,
    Ticket,
    LayoutGrid,
    ReceiptText,
    Scissors,
    Store,
    Users,
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
import { index as clients } from '@/routes/clients';
import { index as expenses } from '@/routes/expenses';
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
        ...(can('manage_vip_clients')
            ? [{ title: 'Clientes', href: clients(), icon: BookHeart }]
            : []),
        ...(can('manage_cash')
            ? [{ title: 'Gastos', href: expenses(), icon: ReceiptText }]
            : []),
        ...(isOwner
            ? [
                  { title: 'Profesionales', href: staff(), icon: Contact },
                  { title: 'Equipo', href: team(), icon: Users },
              ]
            : []),
        // Everyone can also be a client of some salon.
        { title: 'Mis reservas', href: myBookings(), icon: Ticket },
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
