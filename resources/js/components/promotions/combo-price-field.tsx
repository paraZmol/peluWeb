import { AmountInput } from '@/components/accounting/amount-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import { formatCents } from '@/lib/format';
import { fromCents, toCents } from '@/lib/money';
import {
    comboNormalCents,
    comboRange,
    isComboComplete,
    suggestedComboCents,
} from '@/lib/promotion-combo';
import type { PromotionService } from './types';

type Props = { form: PromotionForm; services: PromotionService[] };

/**
 * What the services cost together. Beside it, what they cost apart and the
 * prices it may take, so the owner never has to work it out.
 */
export function ComboPriceField({ form, services }: Props) {
    const { data, setData, errors } = form;
    const chosen = services.filter((service) =>
        data.services.includes(service.id),
    );
    const isReady = isComboComplete(chosen);
    const normal = comboNormalCents(chosen);
    const { lowest, highest } = comboRange(normal);
    const suggested = suggestedComboCents(normal);
    const typed = toCents(data.combo_price);
    const saves = typed !== null && typed > 0 ? normal - typed : null;

    return (
        <div className="grid gap-2">
            <Label htmlFor="promotion-combo-price">Precio del combo</Label>
            <AmountInput
                id="promotion-combo-price"
                value={data.combo_price}
                onChange={(price) => setData('combo_price', price)}
                aria-invalid={Boolean(errors.combo_price)}
                aria-describedby="promotion-combo-price-hint"
                className="w-40 text-lg"
            />
            <div
                id="promotion-combo-price-hint"
                aria-live="polite"
                className="flex flex-col gap-1 text-sm text-muted-foreground"
            >
                {isReady ? (
                    <>
                        <p className="tabular-nums">
                            <span className="font-semibold text-foreground">
                                Por separado {formatCents(normal)}.
                            </span>{' '}
                            Puede ir de {formatCents(lowest)} a{' '}
                            {formatCents(highest)}.
                        </p>
                        {saves !== null && saves > 0 && (
                            <p className="tabular-nums">
                                Ahorran {formatCents(saves)} (
                                {Math.round((saves * 100) / normal)} %).
                            </p>
                        )}
                    </>
                ) : (
                    <p>
                        Elige 2 o 3 servicios y verás cuánto cuestan por
                        separado.
                    </p>
                )}
            </div>
            {isReady && typed !== suggested && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-fit tabular-nums"
                    onClick={() => setData('combo_price', fromCents(suggested))}
                >
                    Usar {formatCents(suggested)} (unos 10 % menos)
                </Button>
            )}
            <InputError message={errors.combo_price} />
        </div>
    );
}
