import { cn } from '@/lib/utils';

/**
 * Typography for a legal text rendered on the server from our own
 * Markdown (raw HTML in it is stripped there). Tokens only.
 */
const PROSE_CLASS = cn(
    'text-[15px] leading-relaxed text-foreground',
    '[&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-balance first:[&_h2]:mt-0',
    '[&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:font-semibold',
    '[&_p]:my-3 [&_p]:text-pretty',
    '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5',
    '[&_ol]:my-3 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-5',
    '[&_strong]:font-semibold',
    '[&_a]:font-medium [&_a]:break-words [&_a]:text-brand-text [&_a]:underline [&_a]:underline-offset-4',
    '[&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-[13px]',
    '[&_hr]:my-10 [&_hr]:border-border',
    // A wide table scrolls on its own instead of the whole page on a phone.
    '[&_table]:my-4 [&_table]:block [&_table]:max-w-full [&_table]:border-collapse [&_table]:overflow-x-auto [&_table]:text-sm',
    '[&_th]:border-b [&_th]:border-border-strong [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold',
    '[&_td]:border-b [&_td]:border-border [&_td]:px-3 [&_td]:py-2 [&_td]:align-top',
);

export function LegalProse({ html }: { html: string }) {
    return (
        <div
            className={PROSE_CLASS}
            // Server-rendered from the repository's own text, HTML stripped.
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
