import { router } from '@inertiajs/react';
import { Copy, Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePromotionForm } from '@/hooks/use-promotion-form';
import { addDays } from '@/lib/promotions';
import { duplicate } from '@/routes/promotions';
import { MAX_NAME } from './more-options';
import { PromotionFormFooter } from './promotion-form-footer';
import { PromotionPreviewPanel } from './promotion-preview';
import type { Promotion, PromotionContext } from './types';

type Props = {
    promotion: Promotion;
    context: PromotionContext;
    onSaved: () => void;
};

/**
 * A promotion that already has bookings: they were promised what it says,
 * so only a later end, more places and the title can change. Anything
 * else goes through a copy.
 */
export function LockedPromotionForm({ promotion, context, onSaved }: Props) {
    const target = { card: promotion.card, promotion };
    const form = usePromotionForm(target, context, onSaved);
    const { data, setData, errors } = form;
    const savedQuota = promotion.form.quota;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.saveDraft();
            }}
            noValidate
            className="flex min-h-0 flex-1 flex-col"
        >
            <div className="grid min-h-0 flex-1 items-start gap-6 overflow-y-auto px-4 pb-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="flex flex-col gap-5">
                    <p className="flex items-start gap-3 rounded-xl bg-brand-soft p-4 text-sm">
                        <Lock className="mt-0.5 size-4 shrink-0" aria-hidden />
                        Ya tiene reservas, y esas conservan lo prometido. Puedes
                        alargar la fecha de fin, subir los cupos o cambiar el
                        título. Para cambiar lo demás, duplícala.
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="promotion-name">Título</Label>
                        <Input
                            id="promotion-name"
                            value={data.name}
                            maxLength={MAX_NAME}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            aria-invalid={Boolean(errors.name)}
                            className="h-11"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="promotion-ends-on">Hasta</Label>
                        <Input
                            id="promotion-ends-on"
                            type="date"
                            min={promotion.endsOn}
                            max={addDays(
                                promotion.startsOn,
                                context.limits.maxDays,
                            )}
                            value={data.ends_on}
                            onChange={(event) =>
                                setData('ends_on', event.target.value)
                            }
                            aria-invalid={Boolean(errors.ends_on)}
                            className="h-11 w-48"
                        />
                        <InputError message={errors.ends_on} />
                    </div>

                    {savedQuota !== null && (
                        <div className="grid gap-2">
                            <Label htmlFor="promotion-quota">Cupos</Label>
                            <Input
                                id="promotion-quota"
                                type="text"
                                inputMode="numeric"
                                autoComplete="off"
                                value={data.quota}
                                onChange={(event) =>
                                    setData(
                                        'quota',
                                        event.target.value.replace(/\D/g, ''),
                                    )
                                }
                                aria-invalid={Boolean(errors.quota)}
                                aria-describedby="promotion-quota-hint"
                                className="h-11 w-40 tabular-nums"
                            />
                            <p
                                id="promotion-quota-hint"
                                className="text-sm text-muted-foreground"
                            >
                                Hoy son {savedQuota} y van{' '}
                                {promotion.results.placesUsed} usados. Solo se
                                pueden subir.
                            </p>
                            <InputError message={errors.quota} />
                        </div>
                    )}
                </div>

                <div className="lg:sticky lg:top-0">
                    <PromotionPreviewPanel
                        preview={form.preview}
                        savedFinePrint={
                            form.isDirty ? undefined : promotion.finePrint
                        }
                    />
                </div>
            </div>

            <PromotionFormFooter form={form} isDraft={false}>
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={() =>
                        router.post(
                            duplicate.url(promotion.id),
                            {},
                            { preserveScroll: true, onSuccess: onSaved },
                        )
                    }
                >
                    <Copy aria-hidden /> Duplicar
                </Button>
            </PromotionFormFooter>
        </form>
    );
}
