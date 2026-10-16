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
            className="sticky top-0 z-40 flex flex-wrap items-center gap-x-4 gap-y-2 bg-foreground px-6 py-2.5 text-sm text-background md:px-4 print:hidden"
        >
            <p className="flex min-w-0 flex-1 basis-64 items-start gap-2.5 text-pretty">
                <LifeBuoy
                    className="mt-0.5 size-4 shrink-0"
                    strokeWidth={1.75}
                    aria-hidden
                />
                <span className="min-w-0">
                    <span className="font-semibold">Modo soporte</span> en{' '}
                    {support.salonName}: puedes ver todo, sin hacer cambios (se
                    cierra solo a las {until})
                </span>
            </p>
            <Form {...end.form()} className="max-sm:w-full">
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={processing}
                        className="max-sm:h-11 max-sm:w-full"
                    >
                        Salir del modo soporte
                    </Button>
                )}
            </Form>
        </div>
    );
}
