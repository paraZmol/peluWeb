import { Check, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { BEFORE_AFTER } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

type ColumnProps = {
    title: string;
    items: string[];
    icon: LucideIcon;
    isHighlighted: boolean;
};

function Column({ title, items, icon: Icon, isHighlighted }: ColumnProps) {
    return (
        <div
            className={cn(
                'flex h-full flex-col gap-5 rounded-3xl border p-6 md:p-8',
                isHighlighted
                    ? 'border-brand-line bg-brand-soft shadow-card'
                    : 'bg-muted/60',
            )}
        >
            <h3
                className={cn(
                    'font-display text-2xl font-semibold',
                    !isHighlighted && 'text-muted-foreground',
                )}
            >
                {title}
            </h3>
            <ul className="flex flex-col gap-4">
                {items.map((item) => (
                    <li key={item} className="flex items-start gap-3">
                        <span
                            className={cn(
                                'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full',
                                isHighlighted
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-background text-muted-foreground',
                            )}
                        >
                            <Icon
                                className="size-3.5"
                                strokeWidth={2.5}
                                aria-hidden
                            />
                        </span>
                        <span
                            className={cn(
                                'text-base',
                                isHighlighted
                                    ? 'font-medium'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {item}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function BeforeAfter() {
    return (
        <LandingSection tone="card">
            <SectionHeading
                eyebrow={BEFORE_AFTER.eyebrow}
                title={BEFORE_AFTER.title}
                description={BEFORE_AFTER.description}
            />
            <div className="mt-12 grid gap-4 md:grid-cols-2 md:gap-6">
                <Reveal>
                    <Column
                        title={BEFORE_AFTER.before.title}
                        items={BEFORE_AFTER.before.items}
                        icon={X}
                        isHighlighted={false}
                    />
                </Reveal>
                <Reveal delay={0.1}>
                    <Column
                        title={BEFORE_AFTER.after.title}
                        items={BEFORE_AFTER.after.items}
                        icon={Check}
                        isHighlighted
                    />
                </Reveal>
            </div>
        </LandingSection>
    );
}
