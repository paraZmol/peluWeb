import type { Availability, Selection } from '@/components/booking/types';
import { useFetched } from '@/hooks/use-fetched';

type Query = {
    /** The availability endpoint: the salon's public one, or the team's. */
    url: string;
    selections: Selection[];
    staffId: number | null;
    date: string | null;
    /** Change it to ask again for the same day. */
    refresh?: number;
};

export const SLOTS_FAILURE =
    'No pudimos cargar los horarios. Revisa tu conexión.';

function queryString({ selections, staffId, date, refresh }: Query): string {
    const params = new URLSearchParams({ date: date ?? '' });

    if (refresh) {
        params.set('r', String(refresh));
    }

    selections.forEach((selection, index) => {
        params.set(`services[${index}][id]`, String(selection.serviceId));

        if (selection.variantId !== null) {
            params.set(
                `services[${index}][variant]`,
                String(selection.variantId),
            );
        }
    });

    if (staffId !== null) {
        params.set('staff', String(staffId));
    }

    return params.toString();
}

/**
 * Free start times of one day for the current selection. The server
 * decides what this viewer may book; the browser only shows it.
 */
export function useAvailability(query: Query) {
    const { data, error, isLoading, retry } = useFetched<Availability>(
        query.date ? `${query.url}?${queryString(query)}` : null,
        SLOTS_FAILURE,
    );

    return {
        slots: data?.slots ?? null,
        quote: data?.quote ?? null,
        error,
        isLoading,
        retry,
    };
}
