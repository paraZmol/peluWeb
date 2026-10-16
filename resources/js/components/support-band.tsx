import { Form, usePage } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatTime } from '@/lib/format';
import { end } from '@/routes/support';

/**
 * Says loud and clear that the platform admin is looking at a salon for
 * support: nothing can be changed, and the way out is always at hand.
 */
export function SupportBand() {
    const { support } = usePage().props;

    if (!support) {
        return null;
    }

    const endsAt = new Date(support.endsAt);
    const until = formatTime(
        `${String(endsAt.getHours()).padStart(2, '0')}:${String(endsAt.getMinutes()).padStart(2, '0')}`,
    );

    return (
        <div
            role="status"
            className="flex flex-wrap items-center gap-3 bg-foreground px-4 py-2 text-sm text-background"
        >
            <LifeBuoy className="size-4 shrink-0" aria-hidden />
            <p className="min-w-0 flex-1">
                <span className="font-semibold">Modo soporte</span> en{' '}
                {support.salonName}: puedes ver todo, sin hacer cambios. Se
                cierra solo a las {until}.
            </p>
            <Form {...end.form()}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={processing}
                    >
                        Salir del modo soporte
                    </Button>
                )}
            </Form>
        </div>
    );
}
