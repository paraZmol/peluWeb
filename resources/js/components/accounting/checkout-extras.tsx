import { ChevronDown } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { AmountInput } from './amount-input';

type Props = {
    discount: string;
    tip: string;
    onDiscount: (amount: string) => void;
    onTip: (amount: string) => void;
};

/** Discount and tip: most visits have neither, so they start folded. */
export function CheckoutExtras({ discount, tip, onDiscount, onTip }: Props) {
    const id = useId();

    return (
        <Collapsible>
            <CollapsibleTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="group -ml-3 h-11 md:h-11"
                >
                    Descuento o propina
                    <ChevronDown
                        aria-hidden
                        className="transition-transform group-data-[state=open]:rotate-180"
                    />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="grid grid-cols-2 gap-3 pt-1">
                <div className="grid gap-1.5">
                    <Label htmlFor={`${id}-discount`}>Descuento</Label>
                    <AmountInput
                        id={`${id}-discount`}
                        value={discount}
                        onChange={onDiscount}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`${id}-tip`}>Propina</Label>
                    <AmountInput
                        id={`${id}-tip`}
                        value={tip}
                        onChange={onTip}
                    />
                </div>
                <p className="col-span-2 text-xs text-muted-foreground">
                    La propina se cobra junto con la cita, pero no cuenta como
                    ingreso del salón.
                </p>
            </CollapsibleContent>
        </Collapsible>
    );
}
