import { LockKeyhole } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { CashDialog } from './cash-dialog';
import { OpenCashForm } from './open-cash-form';

type Props = {
    /** What was counted at the last close; none before the first one. */
    lastCountedCash: string | null;
};

/** Nothing is being counted: the only thing to do is open the till. */
export function ClosedCashCard({ lastCountedCash }: Props) {
    return (
        <section
            aria-label="Estado de la caja"
            className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-card"
        >
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <LockKeyhole className="size-5" aria-hidden />
                </span>
                <div>
                    <h2 className="font-semibold">La caja está cerrada</h2>
                    <p className="text-sm text-muted-foreground">
                        Ábrela al empezar el día para saber cuánto efectivo debe
                        haber al cerrar. Puedes cobrar aunque esté cerrada.
                    </p>
                </div>
            </div>
            <CashDialog
                trigger={<Button className="h-12 sm:h-10">Abrir caja</Button>}
                title="Abrir caja"
                description="Anota con cuánto efectivo empieza la caja."
            >
                <OpenCashForm lastCountedCash={lastCountedCash} />
            </CashDialog>
        </section>
    );
}
