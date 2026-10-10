import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import { CommissionDialog } from './commission-dialog';
import { RulesForm } from './rules-form';
import type { StaffCommission } from './types';

type Props = { staff: StaffCommission };

/** What one professional earns: only the owner gets to open it. */
export function RulesDialog({ staff }: Props) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <CommissionDialog
            open={isOpen}
            onOpenChange={setIsOpen}
            trigger={
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        aria-label={`Reglas de ${staff.name}`}
                        className="h-11 shrink-0 sm:h-9"
                    >
                        <SlidersHorizontal aria-hidden /> Reglas
                    </Button>
                </DialogTrigger>
            }
            title={`Comisión de ${staff.name}`}
            description="Un porcentaje general y, si hace falta, otra regla para algún servicio."
        >
            <RulesForm staff={staff} onSaved={() => setIsOpen(false)} />
        </CommissionDialog>
    );
}
