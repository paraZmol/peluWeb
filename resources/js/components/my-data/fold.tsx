import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = { summary: string; children: ReactNode };

/** Health notes and pictures stay folded until the person asks to see them. */
export function Fold({ summary, children }: Props) {
    return (
        <details className="group rounded-lg border bg-surface-2 text-sm">
            <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-details-marker]:hidden">
                {summary}
                {/* A flex summary loses its marker: this says it opens. */}
                <ChevronDown
                    className="size-4 shrink-0 text-muted-foreground transition-transform duration-150 group-open:rotate-180 motion-reduce:transition-none"
                    aria-hidden
                />
            </summary>
            <div className="border-t px-4 py-4">{children}</div>
        </details>
    );
}
