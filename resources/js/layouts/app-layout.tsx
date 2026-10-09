import { BrandTheme } from '@/components/salon/brand-theme';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    template,
    children,
}: AppLayoutProps) {
    const { currentSalon } = useCurrentSalon();

    // The owner's panel wears the salon's own brand color. A page that is
    // wholly another salon's (one booking of a client) brings that salon's
    // template, so the shell around it does not stay in the platform's look.
    return (
        <BrandTheme
            brand={template ? template.brand : (currentSalon?.brand ?? null)}
            theme={template?.theme}
            className={template && 'bg-background text-foreground'}
        >
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </BrandTheme>
    );
}
