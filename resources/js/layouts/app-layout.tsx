import { BrandTheme } from '@/components/salon/brand-theme';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { currentSalon } = useCurrentSalon();

    // The owner's panel wears the salon's own brand color.
    return (
        <BrandTheme brand={currentSalon?.brand ?? null}>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </BrandTheme>
    );
}
