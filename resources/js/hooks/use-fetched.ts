import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * JSON from a GET endpoint, reloaded whenever the URL changes. A null URL
 * means "nothing to ask yet".
 */
export function useFetched<T>(url: string | null, failure: string) {
    const http = useHttp<Record<string, never>, T>();
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<string>();
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (url === null) {
            return;
        }

        let isCurrent = true;
        setData(null);
        setError(undefined);

        http.get(url)
            .then((response) => {
                if (isCurrent) {
                    setData(response);
                }
            })
            .catch(() => {
                if (isCurrent) {
                    setError(failure);
                }
            });

        return () => {
            isCurrent = false;
        };
        // `http` is a new object every render; the URL is the input.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, attempt]);

    return {
        data: url === null ? null : data,
        error,
        isLoading: url !== null && data === null && error === undefined,
        retry: () => setAttempt((count) => count + 1),
    };
}
