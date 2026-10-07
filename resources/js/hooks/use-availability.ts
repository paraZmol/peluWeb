import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { Availability, Selection } from '@/components/booking/types';
import { availability } from '@/routes/salons/booking';

type Query = {
    slug: string;
    selections: Selection[];
    staffId: number | null;
    date: string | null;
};

function queryString({ selections, staffId, date }: Query): string {
    const params = new URLSearchParams({ date: date ?? '' });

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
    const http = useHttp<Record<string, never>, Availability>();
    const [result, setResult] = useState<Availability | null>(null);
    const [error, setError] = useState<string>();
    const [attempt, setAttempt] = useState(0);
    const key = query.date ? queryString(query) : null;

    useEffect(() => {
        if (key === null) {
            return;
        }

        let isCurrent = true;
        setResult(null);
        setError(undefined);

        http.get(`${availability.url(query.slug)}?${key}`)
            .then((response) => {
                if (isCurrent) {
                    setResult(response);
                }
            })
            .catch(() => {
                if (isCurrent) {
                    setError(
                        'No pudimos cargar los horarios. Revisa tu conexión.',
                    );
                }
            });

        return () => {
            isCurrent = false;
        };
        // `http` is a new object every render; the query string is the input.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key, attempt]);

    return {
        slots: result?.slots ?? null,
        quote: result?.quote ?? null,
        error,
        isLoading: key !== null && result === null && error === undefined,
        retry: () => setAttempt((count) => count + 1),
    };
}
