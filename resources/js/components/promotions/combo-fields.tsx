import type { PromotionForm } from '@/hooks/use-promotion-form';
import { COMBO_MAX_SERVICES, comboCandidates } from '@/lib/promotion-combo';
import { ComboPriceField } from './combo-price-field';
import { ServiceChecklist } from './service-checklist';
import type { PromotionService } from './types';

type Props = { form: PromotionForm; services: PromotionService[] };

/**
 * A combo: two or three services that go together, and what they cost
 * together. No percentage, no amount, no gift.
 */
export function ComboFields({ form, services }: Props) {
    const { data, errors } = form;
    const isFull = data.services.length >= COMBO_MAX_SERVICES;

    return (
        <>
            <ServiceChecklist
                name="services"
                legend="¿Qué servicios van juntos?"
                hint="Elige 2 o 3. Se hacen en la misma cita, con la misma persona."
                services={comboCandidates(services)}
                selected={data.services}
                onToggle={form.toggleService}
                blocked={
                    isFull
                        ? {
                              ids: services
                                  .map((service) => service.id)
                                  .filter((id) => !data.services.includes(id)),
                              reason: `Un combo lleva hasta ${COMBO_MAX_SERVICES} servicios`,
                          }
                        : undefined
                }
                error={errors.services}
            />
            <ComboPriceField form={form} services={services} />
        </>
    );
}
