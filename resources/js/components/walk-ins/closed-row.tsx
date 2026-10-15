import { Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useWalkInMove } from '@/hooks/use-walk-in-move';
import type { WalkIn } from './types';

type Props = {
    walkIn: WalkIn;
    onMoved: (announcement: string) => void;
};

/**
 * Someone already attended or gone today. Closed by mistake, they go back
 * to the line; the server refuses it on another day or with the line full.
 */
export function ClosedRow({ walkIn, onMoved }: Props) {
    const { isBusy, move } = useWalkInMove(walkIn, onMoved);

    return (
        <li aria-busy={isBusy} className="flex items-center gap-3 px-4 py-2">
            <div className="min-w-0 flex-1 py-1">
                <p className="min-w-0 break-words">{walkIn.name}</p>
                <p className="text-muted-foreground">{walkIn.statusLabel}</p>
            </div>
            <Button
                variant="outline"
                className="h-11 shrink-0"
                disabled={isBusy}
                aria-label={`Volver a la fila: ${walkIn.name}`}
                onClick={() => move('waiting')}
            >
                <Undo2 aria-hidden /> Volver a la fila
            </Button>
        </li>
    );
}
