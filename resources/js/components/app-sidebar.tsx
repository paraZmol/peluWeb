import { Link } from '@inertiajs/react';
import { LayoutGrid, Scissors, Store, Users } from 'lucide-react';
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
import { edit as editSalon } from '@/routes/salon';
import { index as services } from '@/routes/services';
import { index as team } from '@/routes/team';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { currentSalon, isOwner, can } = useCurrentSalon();

    const mainNavItems: NavItem[] = [
        { title: 'Panel', href: dashboard(), icon: LayoutGrid },
        ...(can('manage_salon_profile')
            ? [{ title: 'Mi salón', href: editSalon(), icon: Store }]
            : []),
        ...(can('manage_services')
            ? [{ title: 'Servicios', href: services(), icon: Scissors }]
            : []),
        ...(isOwner ? [{ title: 'Equipo', href: team(), icon: Users }] : []),
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
