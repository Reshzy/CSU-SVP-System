import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { TableQueryFilters } from './types';
import { compactQuery } from './utils';

type VisitOptions = {
    debounceMs?: number;
};

export function useInertiaTable(visitUrl: string | undefined, debounceMs = 300) {
    const [isLoading, setIsLoading] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => {
        return () => {
            if (timer.current !== undefined) {
                window.clearTimeout(timer.current);
            }
        };
    }, []);

    const visit = (filters: TableQueryFilters, options: VisitOptions = {}) => {
        if (!visitUrl) {
            return;
        }

        const wait = options.debounceMs ?? 0;
        const query = compactQuery(filters);

        const send = () => {
            router.get(visitUrl, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            });
        };

        if (timer.current !== undefined) {
            window.clearTimeout(timer.current);
        }

        if (wait > 0) {
            timer.current = window.setTimeout(send, wait);
            return;
        }

        send();
    };

    return { isLoading, visit, debounceMs };
}
