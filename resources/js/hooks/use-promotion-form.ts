import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type {
    Promotion,
    PromotionCardKind,
    PromotionContext,
    PromotionFields,
} from '@/components/promotions/types';
import {
    newPromotionFields,
    occasionDefaults,
    promotionPayload,
    savedPromotionFields,
} from '@/lib/promotion-cards';
import { autoTitle, previewOf } from '@/lib/promotion-preview';
import { store, update } from '@/routes/promotions';

/** What the sheet was opened for: a new one from a card, or one to edit. */
export type PromotionTarget = {
    card: PromotionCardKind;
    promotion?: Promotion;
};

function initialFields(
    { card, promotion }: PromotionTarget,
    context: PromotionContext,
): PromotionFields {
    if (!promotion) {
        return newPromotionFields(card, context.today);
    }

    const saved = savedPromotionFields(promotion, promotion.name);
    // A title the system wrote is left empty, so it follows what changes.
    const isOwnTitle = autoTitle(saved, context.services) !== promotion.name;

    return promotion.isLocked || isOwnTitle ? saved : { ...saved, name: '' };
}

/**
 * What is being typed for one promotion, its live preview and how it is
 * sent: "Publicar" is the same form with one more field.
 */
export function usePromotionForm(
    target: PromotionTarget,
    context: PromotionContext,
    onSaved: () => void,
) {
    const form = useForm<PromotionFields>(initialFields(target, context));
    const [hasNetworkProblem, setHasNetworkProblem] = useState(false);
    const { data, setData } = form;

    const send = (publish: boolean) => {
        form.transform((fields) => promotionPayload(fields, publish));
        form.submit(target.promotion ? update(target.promotion.id) : store(), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setHasNetworkProblem(false),
            onNetworkError: () => setHasNetworkProblem(true),
            onSuccess: onSaved,
        });
    };

    return {
        data,
        setData,
        errors: form.errors as Partial<Record<string, string>>,
        processing: form.processing,
        isDirty: form.isDirty,
        hasNetworkProblem,
        preview: previewOf(data, context),
        /** What the title will be if the owner writes none. */
        writtenTitle: autoTitle(data, context.services),
        toggleService: (id: number) =>
            setData(
                'services',
                data.services.includes(id)
                    ? data.services.filter((one) => one !== id)
                    : [...data.services, id],
            ),
        /** Every service, except the one being given away. */
        pickAllServices: () =>
            setData(
                'services',
                context.services
                    .map((service) => service.id)
                    .filter(
                        (id) =>
                            data.mechanic !== 'gift' ||
                            id !== data.gift_service_id,
                    ),
            ),
        toggleStaff: (id: number) =>
            setData(
                'staff',
                data.staff.includes(id)
                    ? data.staff.filter((one) => one !== id)
                    : [...data.staff, id],
            ),
        toggleWeekday: (day: number) =>
            setData(
                'weekdays',
                data.weekdays.includes(day)
                    ? data.weekdays.filter((one) => one !== day)
                    : [...data.weekdays, day].sort((a, b) => a - b),
            ),
        /** A campaign brings its name and its dates. */
        pickOccasion: (occasion: string) =>
            setData({
                ...data,
                occasion,
                overline: '',
                ...occasionDefaults(occasion, context.today),
            }),
        saveDraft: () => send(false),
        publish: () => send(true),
    };
}

export type PromotionForm = ReturnType<typeof usePromotionForm>;
