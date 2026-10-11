import { useCallback } from 'react';
import { useSidebar } from '@/components/ui/sidebar';

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
    const { setOpenMobile } = useSidebar();

    return useCallback(() => {
        // Remove pointer-events style from body...
        document.body.style.removeProperty('pointer-events');
        // On a phone the menu is a drawer over the page: choosing closes it.
        setOpenMobile(false);
    }, [setOpenMobile]);
}
