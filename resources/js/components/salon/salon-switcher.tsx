import { Link, router } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Scissors } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { update as switchSalon } from '@/routes/current-salon';
import { create as createSalon } from '@/routes/salons';

const roleLabels = { owner: 'Dueño', staff: 'Personal' } as const;

export function SalonSwitcher() {
    const { currentSalon, salons } = useCurrentSalon();

    if (!currentSalon) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            data-test="salon-switcher"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <Scissors className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {currentSalon.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {roleLabels[currentSalon.role]}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Tus salones
                        </DropdownMenuLabel>
                        {salons.map((salon) => (
                            <DropdownMenuItem
                                key={salon.id}
                                onSelect={() =>
                                    salon.id !== currentSalon.id &&
                                    router.visit(switchSalon(salon.id))
                                }
                            >
                                <span className="flex-1 truncate">
                                    {salon.name}
                                </span>
                                {salon.id === currentSalon.id && (
                                    <Check className="size-4" />
                                )}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={createSalon()}>
                                <Plus className="size-4" />
                                Crear otro salón
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
