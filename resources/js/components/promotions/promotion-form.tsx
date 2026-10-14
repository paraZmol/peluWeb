import { usePromotionForm } from '@/hooks/use-promotion-form';
import type { PromotionTarget } from '@/hooks/use-promotion-form';
import { giftCandidates } from '@/lib/promotion-cards';
import { PAIR_FREE } from '@/lib/promotions';
import { BenefitField } from './benefit-field';
import { ComboFields } from './combo-fields';
import { DateFields } from './date-fields';
import { MoreOptions } from './more-options';
import { OccasionField } from './occasion-field';
import { PairFields } from './pair-fields';
import { PromotionFormFooter } from './promotion-form-footer';
import { PromotionPreviewPanel } from './promotion-preview';
import { ServiceChecklist } from './service-checklist';
import type { PromotionContext } from './types';
import { WhenFields } from './when-fields';

type Props = {
    target: PromotionTarget;
    context: PromotionContext;
    onSaved: () => void;
};

/**
 * Fills in one promotion: two or three fields per card, the rest folded,
 * and the card the client will read beside it. The server validates all.
 */
export function PromotionForm({ target, context, onSaved }: Props) {
    const form = usePromotionForm(target, context, onSaved);
    const { data, setData, errors } = form;
    const { services, limits, today } = context;
    const isGift = data.mechanic === 'gift';
    const isCombo = data.mechanic === 'combo';
    const isPair = data.mechanic === 'pair';
    const isQuietHours = data.card === 'quiet_hours';
    // Days and hours already in the form: "Horas flojas" and the 2x1 free.
    const hasWhen =
        isQuietHours || (isPair && Number(data.percent) === PAIR_FREE);
    const isDraft = !target.promotion || target.promotion.state === 'draft';

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
                <div className="flex flex-col gap-6">
                    {data.card === 'season' && <OccasionField form={form} />}

                    {isPair && <PairFields form={form} services={services} />}
                    {isCombo && <ComboFields form={form} services={services} />}
                    {!isPair && !isCombo && (
                        <>
                            <ServiceChecklist
                                name="services"
                                legend={
                                    isGift
                                        ? 'Al reservar…'
                                        : '¿En qué servicios?'
                                }
                                hint={
                                    isGift
                                        ? 'Basta con cualquiera de los que marques.'
                                        : undefined
                                }
                                services={services}
                                selected={data.services}
                                onToggle={form.toggleService}
                                onPickAll={form.pickAllServices}
                                blocked={
                                    isGift && data.gift_service_id !== null
                                        ? {
                                              ids: [data.gift_service_id],
                                              reason: 'Es el regalo',
                                          }
                                        : undefined
                                }
                                error={errors.services}
                            />

                            {isGift ? (
                                <ServiceChecklist
                                    single
                                    name="gift_service_id"
                                    legend="…regalamos"
                                    hint="Un servicio con un solo precio. Se hace en la misma cita."
                                    services={giftCandidates(services)}
                                    selected={
                                        data.gift_service_id === null
                                            ? []
                                            : [data.gift_service_id]
                                    }
                                    onToggle={(id) =>
                                        setData('gift_service_id', id)
                                    }
                                    blocked={{
                                        ids: data.services,
                                        reason: 'Ya está entre los que dan el regalo',
                                    }}
                                    error={errors.gift_service_id}
                                />
                            ) : (
                                <BenefitField form={form} limits={limits} />
                            )}
                        </>
                    )}

                    {isQuietHours && <WhenFields form={form} required />}

                    <DateFields
                        form={form}
                        today={today}
                        maxDays={limits.maxDays}
                    />

                    <MoreOptions
                        form={form}
                        hidesWhen={hasWhen}
                        team={context.team}
                    />
                </div>

                <div className="lg:sticky lg:top-0">
                    <PromotionPreviewPanel
                        preview={form.preview}
                        savedFinePrint={
                            form.isDirty
                                ? undefined
                                : target.promotion?.finePrint
                        }
                    />
                </div>
            </div>

            <PromotionFormFooter form={form} isDraft={isDraft} />
        </form>
    );
}
