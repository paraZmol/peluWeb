import { Link } from '@inertiajs/react';
import { LayoutGrid, Users } from 'lucide-react';
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
import { index as team } from '@/routes/team';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { currentSalon, isOwner } = useCurrentSalon();

    const mainNavItems: NavItem[] = [
        { title: 'Panel', href: dashboard(), icon: LayoutGrid },
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
