import { Button } from '@/components/ui/button';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    wizard: BookingWizard;
    /** Inside the app shell: stick to the content column, not to the window. */
    inline?: boolean;
};

/** Running total and the way forward, fixed in the thumb zone. */
export function SelectionBar({ wizard, inline = false }: Props) {
    const count = wizard.chosen.length;

    return (
        <div
            className={cn(
                'bottom-0 z-20 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur',
                inline ? 'sticky' : 'fixed inset-x-0',
            )}
        >
            <div className="mx-auto flex w-full max-w-2xl items-center gap-3 px-4 py-3">
                <p className="min-w-0 flex-1 text-sm" role="status">
                    <span className="block text-muted-foreground">
                        {count} {count === 1 ? 'servicio' : 'servicios'}
                    </span>
                    <span className="text-base font-semibold tabular-nums">
                        <span className="text-sm font-normal text-muted-foreground">
                            desde{' '}
                        </span>
                        {formatPrice(wizard.estimate)}
                    </span>
                </p>
                <Button
                    size="lg"
                    className="h-12 rounded-full px-8"
                    disabled={
                        wizard.chosen.length === 0 || wizard.hasOnlyAddons
                    }
                    onClick={() => wizard.goTo(1)}
                >
                    Continuar
                </Button>
            </div>
        </div>
    );
}
