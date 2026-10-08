import { useEffect, useState } from 'react';

/**
 * JSON from a GET endpoint of this app, reloaded whenever the URL changes.
 * A null URL means "nothing to ask yet".
 *
 * A plain `fetch`, on purpose: these lookups (free times, client
 * suggestions) run while forms are being sent, and must stay out of the way
 * of Inertia's own visits. One still in flight when a form came back with
 * validation errors used to swallow those errors.
 */
export function useFetched<T>(url: string | null, failure: string) {
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<string>();
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (url === null) {
            return;
        }

        const request = new AbortController();
        setData(null);
        setError(undefined);

        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: request.signal,
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<T>)
                    : Promise.reject(new Error(String(response.status))),
            )
            .then(setData)
            .catch(() => {
                if (!request.signal.aborted) {
                    setError(failure);
                }
            });

        return () => request.abort();
    }, [url, attempt, failure]);

    return {
        data: url === null ? null : data,
        error,
        isLoading: url !== null && data === null && error === undefined,
        retry: () => setAttempt((count) => count + 1),
    };
}
