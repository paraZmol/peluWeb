import { useEffect, useState } from 'react';
import { formatTime, sumAmounts } from '@/lib/format';
import type {
    BookableSalon,
    BookableService,
    RepeatBooking,
    Selection,
    Slot,
} from '@/components/booking/types';

export const MAX_SERVICES = 5;
export const STEPS = ['Servicios', 'Profesional', 'Día y hora', 'Confirmar'];
/** Where the day and the time are chosen. */
const TIME_STEP = 2;

type State = {
    step: number;
    selections: Selection[];
    /** null = whoever is free. */
    staffId: number | null;
    date: string | null;
    slot: Slot | null;
    note: string;
    /** Shown above the times, e.g. after the chosen one was taken. */
    notice: string | null;
    /** Bumped to ask for the free times again. */
    refresh: number;
    /** When it was last touched (ms), to drop stale saved choices. */
    savedAt: number;
};

/** Saved choices older than this are not worth restoring. */
const RESTORE_WINDOW_MS = 30 * 60 * 1000;

const EMPTY: State = {
    step: 0,
    selections: [],
    staffId: null,
    date: null,
    slot: null,
    note: '',
    notice: null,
    refresh: 0,
    savedAt: 0,
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
    /** "Repetir cita": start with what was booked before. */
    repeat?: RepeatBooking | null;
    /** Coming back from signing in: pick up where the visitor left. */
    resume?: boolean;
    /** Keep the choices in the browser, to survive signing in. */
    persist?: boolean;
    /** A link that asks for a day ("?fecha="): the times start on it. */
    startDate?: string | null;
};

export function useBookingWizard({
    salon,
    preselectedServiceId = null,
    repeat = null,
    resume = false,
    persist = true,
    startDate = null,
}: Options) {
    const services = salon.serviceGroups.flatMap((group) => group.services);
    const find = (id: number): BookableService | undefined =>
        services.find((service) => service.id === id);

    const [state, setState] = useState<State>(() => {
        const repeated = (repeat?.selections ?? []).flatMap((selection) => {
            const service = find(selection.serviceId);

            return service ? [{ service, variantId: selection.variantId }] : [];
        });

        if (repeated.length > 0) {
            // Straight to choosing a time when the same person still does it all.
            const staffId = repeated.every(({ service }) =>
                service.staffIds.includes(repeat?.staffId ?? 0),
            )
                ? (repeat?.staffId ?? null)
                : null;

            return {
                ...EMPTY,
                selections: repeated.map(({ service, variantId }) => ({
                    serviceId: service.id,
                    // The option booked then; a service that has options
                    // always needs one.
                    variantId: variantId ?? service.variants[0]?.id ?? null,
                })),
                staffId,
                step: staffId === null ? 1 : 2,
            };
        }

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
    // Also without `resume`: the phone's back button leaves the wizard, and
    // coming back should not mean starting over. A link that asks for
    // something specific (a service, repeating a booking) wins.
    useEffect(() => {
        const asksForSomething =
            preselectedServiceId !== null || repeat !== null;
        const saved = persist && !asksForSomething ? restore(salon.slug) : null;
        const isFresh =
            saved !== null && Date.now() - saved.savedAt < RESTORE_WINDOW_MS;

        if (saved && (resume || isFresh)) {
            setState({ ...saved, notice: null });
        }

        // The day asked for wins over the one kept, and over a time already
        // picked on another day.
        if (startDate !== null) {
            setState((current) =>
                current.date === startDate
                    ? current
                    : {
                          ...current,
                          date: startDate,
                          slot: null,
                          step: Math.min(current.step, TIME_STEP),
                      },
            );
        }
        // Only on arrival.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resume, salon.slug]);

    useEffect(() => {
        if (!persist) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                storageKey(salon.slug),
                JSON.stringify({ ...state, savedAt: Date.now() }),
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
        chooseDate: (date: string) => patch({ date, slot: null, notice: null }),
        /** The chosen time was taken meanwhile: back to the fresh list. */
        slotTaken: () => {
            setDirection(-1);
            setState((current) => ({
                ...current,
                step: 2,
                slot: null,
                refresh: current.refresh + 1,
                notice: current.slot
                    ? `Alguien tomó las ${formatTime(current.slot.time)} mientras confirmabas. Elige otro horario.`
                    : 'Ese horario se acaba de ocupar. Elige otro.',
            }));
        },
        chooseSlot: (slot: Slot) => {
            setDirection(1);
            patch({ slot, step: 3, notice: null });
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
