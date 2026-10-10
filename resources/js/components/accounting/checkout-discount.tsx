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
    onDiscount: (amount: string) => void;
};

/**
 * Money taken off the price. Most visits have none, so it starts folded;
 * only who may discount (the cash permission) gets to see it at all.
 */
export function CheckoutDiscount({ discount, onDiscount }: Props) {
    const id = useId();

    return (
        <Collapsible>
            <CollapsibleTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="group -ml-3 h-11 md:h-11"
                >
                    Descuento
                    <ChevronDown
                        aria-hidden
                        className="transition-transform group-data-[state=open]:rotate-180"
                    />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="grid gap-1.5 pt-1">
                <Label htmlFor={`${id}-discount`} className="sr-only">
                    Descuento
                </Label>
                <AmountInput
                    id={`${id}-discount`}
                    value={discount}
                    onChange={onDiscount}
                />
                <p className="text-xs text-muted-foreground">
                    Se resta de lo que falta cobrar de esta cita.
                </p>
            </CollapsibleContent>
        </Collapsible>
    );
}
