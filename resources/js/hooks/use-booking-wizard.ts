import { useEffect, useState } from 'react';
import { sumAmounts } from '@/lib/format';
import type {
    BookableSalon,
    BookableService,
    Selection,
    Slot,
} from '@/components/booking/types';

export const MAX_SERVICES = 5;
export const STEPS = ['Servicios', 'Profesional', 'Día y hora', 'Confirmar'];

type State = {
    step: number;
    selections: Selection[];
    /** null = whoever is free. */
    staffId: number | null;
    date: string | null;
    slot: Slot | null;
    note: string;
};

const EMPTY: State = {
    step: 0,
    selections: [],
    staffId: null,
    date: null,
    slot: null,
    note: '',
};

function storageKey(slug: string): string {
    return `peluweb:reserva:${slug}`;
}

/** What was chosen before signing in. Never trusted: the server re-checks. */
function restore(slug: string): State | null {
    try {
        const saved = window.sessionStorage.getItem(storageKey(slug));

        return saved ? { ...EMPTY, ...(JSON.parse(saved) as State) } : null;
    } catch {
        return null;
    }
}

type Options = {
    salon: BookableSalon;
    preselectedServiceId?: number | null;
    /** Coming back from signing in: pick up where the visitor left. */
    resume?: boolean;
    /** Keep the choices in the browser, to survive signing in. */
    persist?: boolean;
};

export function useBookingWizard({
    salon,
    preselectedServiceId = null,
    resume = false,
    persist = true,
}: Options) {
    const services = salon.serviceGroups.flatMap((group) => group.services);
    const find = (id: number): BookableService | undefined =>
        services.find((service) => service.id === id);

    const [state, setState] = useState<State>(() => {
        const preselected = find(preselectedServiceId ?? 0);

        return {
            ...EMPTY,
            selections:
                preselected && preselected.variants.length === 0
                    ? [{ serviceId: preselected.id, variantId: null }]
                    : [],
        };
    });
    // Forward/back, so the step transition slides the right way.
    const [direction, setDirection] = useState(1);

    // After mount, not in the initial state: the server render has no storage.
    useEffect(() => {
        const saved = resume ? restore(salon.slug) : null;

        if (saved) {
            setState(saved);
        }
    }, [resume, salon.slug]);

    useEffect(() => {
        if (!persist) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                storageKey(salon.slug),
                JSON.stringify(state),
            );
        } catch {
            // Private mode without storage: the wizard still works.
        }
    }, [state, salon.slug, persist]);

    const patch = (changes: Partial<State>) =>
        setState((current) => ({ ...current, ...changes }));

    const goTo = (step: number) => {
        setDirection(step > state.step ? 1 : -1);
        patch({ step });
    };

    const isSelected = (serviceId: number) =>
        state.selections.some((s) => s.serviceId === serviceId);

    /** Changing services invalidates the professional and the time chosen. */
    const setSelections = (selections: Selection[]) =>
        patch({ selections, staffId: null, slot: null });

    const toggleService = (service: BookableService) => {
        if (isSelected(service.id)) {
            setSelections(
                state.selections.filter((s) => s.serviceId !== service.id),
            );
        } else if (state.selections.length < MAX_SERVICES) {
            setSelections([
                ...state.selections,
                {
                    serviceId: service.id,
                    variantId: service.variants[0]?.id ?? null,
                },
            ]);
        }
    };

    const chooseVariant = (serviceId: number, variantId: number) =>
        setSelections(
            state.selections.map((s) =>
                s.serviceId === serviceId ? { ...s, variantId } : s,
            ),
        );

    const chosen = state.selections
        .map((s) => find(s.serviceId))
        .filter((service): service is BookableService => service !== undefined);

    return {
        ...state,
        direction,
        services,
        chosen,
        // Only who performs every chosen service can take the visit.
        eligibleStaff: salon.staff.filter((person) =>
            chosen.every((service) => service.staffIds.includes(person.id)),
        ),
        // Lowest price it could cost: the chosen option, else the base price.
        estimate: sumAmounts(
            state.selections.map((selection) => {
                const service = find(selection.serviceId);
                const variant = service?.variants.find(
                    (v) => v.id === selection.variantId,
                );

                return variant?.price ?? service?.price ?? '0';
            }),
        ),
        hasOnlyAddons:
            chosen.length > 0 && chosen.every((service) => service.isAddon),
        isSelected,
        toggleService,
        chooseVariant,
        goTo,
        chooseStaff: (staffId: number | null) => {
            setDirection(1);
            patch({ staffId, slot: null, step: 2 });
        },
        chooseDate: (date: string) => patch({ date, slot: null }),
        chooseSlot: (slot: Slot) => {
            setDirection(1);
            patch({ slot, step: 3 });
        },
        setNote: (note: string) => patch({ note }),
        clear: () => {
            try {
                window.sessionStorage.removeItem(storageKey(salon.slug));
            } catch {
                // Nothing to clear.
            }
        },
    };
}

export type BookingWizard = ReturnType<typeof useBookingWizard>;
