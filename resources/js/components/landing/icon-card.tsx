import type { IconItem } from './content';

/** Card with an icon badge, title and one short paragraph; shared by features and audiences. */
export function IconCard({ item }: { item: IconItem }) {
    const Icon = item.icon;

    return (
        <article className="flex h-full gap-4 rounded-3xl p-5 skin-card sm:flex-col sm:p-6">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand-text">
                <Icon className="size-5" strokeWidth={1.75} aria-hidden />
            </span>
            <div className="flex flex-col gap-1.5 sm:gap-2">
                <h3 className="text-lg font-semibold">{item.title}</h3>
                <p className="text-base text-pretty text-muted-foreground">
                    {item.description}
                </p>
            </div>
        </article>
    );
}
