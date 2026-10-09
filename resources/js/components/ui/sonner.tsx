import { templateProps } from '@/components/salon/portal-theme';
import { ALWAYS_DARK_THEMES } from '@/components/salon/salon-themes';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useActivePageTemplate } from '@/hooks/use-page-template';
import { cn } from '@/lib/utils';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();
    // On a salon's page the toast wears that salon's template: a template
    // that is always dark must not get a white toast in a light browser.
    const template = useActivePageTemplate();
    const themed = template
        ? templateProps(template.theme, template.brand)
        : null;
    const isAlwaysDark =
        template !== null && ALWAYS_DARK_THEMES.has(template.theme);

    useFlashToast();

    return (
        // Always the same element: swapping it would drop the open toasts.
        <div {...themed} className={cn('contents', themed?.className)}>
            <Sonner
                theme={isAlwaysDark ? 'dark' : appearance}
                className="toaster group"
                position="top-center"
                style={
                    {
                        '--normal-bg': 'var(--popover)',
                        '--normal-text': 'var(--popover-foreground)',
                        '--normal-border': 'var(--border)',
                    } as React.CSSProperties
                }
                {...props}
            />
        </div>
    );
}

export { Toaster };
