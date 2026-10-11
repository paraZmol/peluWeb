import type { ReactNode } from 'react';

type Props = { summary: string; children: ReactNode };

/** Health notes and pictures stay folded until the person asks to see them. */
export function Fold({ summary, children }: Props) {
    return (
        <details className="rounded-lg border bg-surface-2 text-sm">
            <summary className="flex min-h-11 cursor-pointer items-center rounded-lg px-4 font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
                {summary}
            </summary>
            <div className="px-4 pb-4">{children}</div>
        </details>
    );
}
