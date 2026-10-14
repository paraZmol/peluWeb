import { ArrowRight } from 'lucide-react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatPrice } from '@/lib/format';
import { PRIMARY_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';

type Props = {
    wizard: BookingWizard;
    /** Inside the app shell: stick to the content column, not to the window. */
    inline?: boolean;
    /** Width of the content it lines up with. */
    wide?: boolean;
};

/** Running total and the way forward, fixed in the thumb zone. */
export function SelectionBar({ wizard, inline = false, wide = false }: Props) {
    // Who comes along has a service of their own.
    const count = wizard.chosen.length + (wizard.companion ? 1 : 0);

    return (
        <div
            className={cn(
                'bottom-0 z-20 pb-[env(safe-area-inset-bottom)] skin-dock',
                inline ? 'sticky' : 'fixed inset-x-0',
            )}
        >
            <div
                className={cn(
                    'mx-auto flex w-full items-center gap-3 px-4 py-3',
                    wide ? 'max-w-5xl' : 'max-w-2xl',
                )}
            >
                <p className="min-w-0 flex-1 text-sm" role="status">
                    <span className="block text-muted-foreground">
                        {wizard.needsCompanionName
                            ? 'Falta el nombre de quien te acompaña'
                            : `${count} ${count === 1 ? 'servicio' : 'servicios'}`}
                    </span>
                    <span className="text-lg font-bold tabular-nums">
                        <span className="text-sm font-normal text-muted-foreground">
                            desde{' '}
                        </span>
                        {formatPrice(wizard.estimate)}
                    </span>
                </p>
                <button
                    type="button"
                    className={cn(PRIMARY_ACTION, 'px-8')}
                    disabled={
                        wizard.chosen.length === 0 ||
                        wizard.hasOnlyAddons ||
                        wizard.needsCompanionName
                    }
                    onClick={() => wizard.goTo(1)}
                >
                    Continuar <ArrowRight aria-hidden />
                </button>
            </div>
        </div>
    );
}
