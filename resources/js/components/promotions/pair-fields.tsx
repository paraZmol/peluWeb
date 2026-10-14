import InputError from '@/components/input-error';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import { pairCandidates, quietHoursFields } from '@/lib/promotion-cards';
import { PAIR_FREE, PAIR_HALF } from '@/lib/promotions';
import { cn } from '@/lib/utils';
import { ServiceChecklist } from './service-checklist';
import type { PromotionService } from './types';
import { WhenFields } from './when-fields';

const BENEFITS = [
    {
        percent: PAIR_HALF,
        label: 'Segundo a mitad de precio',
        hint: 'Vale en cualquier horario.',
    },
    {
        percent: PAIR_FREE,
        label: '2x1, el segundo gratis',
        hint: 'Solo en los días y horas que quieras llenar.',
    },
];

type Props = { form: PromotionForm; services: PromotionService[] };

/**
 * "Ven con alguien": the services two people can book together, and what
 * the second one gets. Free only on the days and hours the owner names.
 */
export function PairFields({ form, services }: Props) {
    const { data, setData, errors } = form;
    const isFree = Number(data.percent) === PAIR_FREE;
    // Free needs days and hours: it opens with the quiet ones proposed.
    const choose = (percent: number) =>
        setData({
            ...data,
            percent: String(percent),
            ...(percent === PAIR_FREE && data.weekdays.length === 0
                ? quietHoursFields()
                : {}),
        });

    return (
        <>
            <ServiceChecklist
                name="services"
                legend="¿En qué servicios?"
                hint="De precio fijo. Vale cualquiera de los que marques, el mismo para las dos personas."
                services={pairCandidates(services)}
                selected={data.services}
                onToggle={form.toggleService}
                error={errors.services}
            />

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    ¿Qué se lleva la segunda persona?
                </legend>
                {BENEFITS.map((benefit) => (
                    <label key={benefit.percent} className="cursor-pointer">
                        <input
                            type="radio"
                            name="pair-benefit"
                            value={benefit.percent}
                            checked={Number(data.percent) === benefit.percent}
                            onChange={() => choose(benefit.percent)}
                            className="peer sr-only"
                        />
                        <span
                            className={cn(
                                'flex min-h-14 flex-col justify-center rounded-xl border bg-card px-4 py-2.5 text-sm transition-colors',
                                'peer-checked:border-primary peer-checked:bg-primary/10 peer-focus-visible:ring-2 peer-focus-visible:ring-ring',
                            )}
                        >
                            <span className="font-medium">{benefit.label}</span>
                            <span className="text-muted-foreground">
                                {benefit.hint}
                            </span>
                        </span>
                    </label>
                ))}
                <InputError message={errors.percent} />
            </fieldset>

            {isFree && <WhenFields form={form} required />}
        </>
    );
}
