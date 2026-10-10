import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import type { StaffCommission } from '@/components/commissions/types';
import { ruleMode } from '@/lib/commissions';
import type { RuleMode } from '@/lib/commissions';
import { update } from '@/routes/commissions/rules';

/** What is being typed for one service: only the chosen mode is sent. */
export type ServiceRuleDraft = {
    id: number;
    mode: RuleMode;
    percent: string;
    amount: string;
};

type Fields = { percent: string; services: ServiceRuleDraft[] };

const MISSING_VALUE = 'Escribe un valor o elige «Usa el general».';

/** The field a mode fills in; none when the service uses the general rule. */
const valueOf = (service: ServiceRuleDraft): string | null =>
    service.mode === 'general' ? null : service[service.mode].trim();

/**
 * One professional's rules while they are edited, and how they are sent.
 * The server validates every field; errors come back as `services.N.field`.
 */
export function useCommissionRulesForm(
    staff: StaffCommission,
    onSaved: () => void,
) {
    const form = useForm<Fields>({
        percent: String(staff.percent),
        services: staff.services.map((service) => ({
            id: service.id,
            mode: ruleMode(service),
            percent: service.percent === null ? '' : String(service.percent),
            amount: service.amount ?? '',
        })),
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const setService = (index: number, patch: Partial<ServiceRuleDraft>) =>
        form.setData(
            'services',
            form.data.services.map((service, position) =>
                position === index ? { ...service, ...patch } : service,
            ),
        );

    form.transform((data) => ({
        percent: data.percent.trim(),
        services: data.services.map((service) =>
            service.mode === 'general'
                ? { id: service.id }
                : { id: service.id, [service.mode]: valueOf(service) },
        ),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.clearErrors();
        // A blank value would be saved as "uses the general rule" in silence.
        const blanks = form.data.services.flatMap((service, index) =>
            valueOf(service) === ''
                ? [`services.${index}.${service.mode}`]
                : [],
        );

        if (blanks.length > 0) {
            form.setError(
                Object.fromEntries(
                    blanks.map((field) => [field, MISSING_VALUE]),
                ) as never,
            );

            return;
        }

        form.submit(update(staff.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onSaved,
        });
    };

    return {
        data: form.data,
        setPercent: (percent: string) => form.setData('percent', percent),
        setService,
        /** The complaint about one service, whichever field it is about. */
        serviceError: (index: number) =>
            errors[`services.${index}.percent`] ??
            errors[`services.${index}.amount`] ??
            errors[`services.${index}.id`],
        errors,
        processing: form.processing,
        submit,
    };
}
