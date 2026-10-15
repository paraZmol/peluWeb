import { FlaskConical, Heart, ShieldCheck, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { attendingBrief } from '@/lib/clients';
import type { BriefLine } from '@/lib/clients';
import { cn } from '@/lib/utils';
import type { ClientFile } from './types';

const ICONS: Record<BriefLine['key'], LucideIcon> = {
    care: ShieldCheck,
    formula: FlaskConical,
    notes: Heart,
};

type Props = { client: ClientFile; canSeeCare: boolean };

/**
 * What to read first with the person in the chair. Each line jumps to its
 * field; an allergy stands out by its icon and word, not only by color.
 */
export function BeforeAttending({ client, canSeeCare }: Props) {
    const lines = attendingBrief(client, canSeeCare);

    if (lines.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="client-brief-title"
            className="overflow-hidden rounded-xl border bg-card shadow-card"
        >
            <h2
                id="client-brief-title"
                className="border-b bg-surface-2 px-4 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Antes de atender
            </h2>
            <ul className="divide-y lg:grid lg:auto-cols-fr lg:grid-flow-col lg:divide-x lg:divide-y-0">
                {lines.map((line) => {
                    const Icon = line.isAlert ? TriangleAlert : ICONS[line.key];

                    return (
                        <li key={line.key} className="min-w-0">
                            <a
                                href={line.href}
                                className={cn(
                                    'flex h-full min-h-11 items-start gap-3 px-4 py-3 text-sm outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset',
                                    line.isAlert && 'bg-warning/10',
                                )}
                            >
                                <Icon
                                    className={cn(
                                        'mt-0.5 size-4 shrink-0',
                                        line.isAlert
                                            ? 'text-warning'
                                            : 'text-muted-foreground',
                                    )}
                                    aria-hidden
                                />
                                <span className="min-w-0">
                                    <span
                                        className={cn(
                                            'block text-xs font-semibold',
                                            !line.isAlert &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {line.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'break-words',
                                            // A withdrawal is read whole.
                                            !line.isNotice && 'line-clamp-2',
                                            line.isAlert && 'font-medium',
                                        )}
                                    >
                                        {line.text}
                                    </span>
                                </span>
                            </a>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
