import type { ReactNode } from 'react';

type Props = {
    id: string;
    title: string;
    description: string;
    children: ReactNode;
};

/** Card wrapper shared by every block of the "Mi salón" screen. */
export function SettingsSection({ id, title, description, children }: Props) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className="flex scroll-mt-20 flex-col gap-5 rounded-xl border bg-card p-5"
        >
            <div>
                <h2 id={`${id}-title`} className="text-lg font-semibold">
                    {title}
                </h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}
