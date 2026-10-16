import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { LegalFooter } from '@/components/legal/legal-footer';
import { SubscriptionNotice } from '@/components/subscription/subscription-notice';
import { SupportBand } from '@/components/support-band';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <SupportBand />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <SubscriptionNotice />
                {children}
                <LegalFooter className="mt-auto pb-[calc(1.5rem+env(safe-area-inset-bottom))]" />
            </AppContent>
        </AppShell>
    );
}
