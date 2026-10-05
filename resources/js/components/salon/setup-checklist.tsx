import { Link } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

export type SetupStep = {
    key: string;
    label: string;
    done: boolean;
    href: string;
};

/**
 * "Complete your salon" guide on the dashboard. Hidden once every step is
 * done, so it never becomes noise.
 */
export function SetupChecklist({ steps }: { steps: SetupStep[] }) {
    const done = steps.filter((step) => step.done).length;

    if (done === steps.length) {
        return null;
    }

    return (
        <section
            aria-labelledby="setup-title"
            className="flex flex-col gap-4 rounded-xl border bg-card p-5"
        >
            <div className="flex flex-col gap-2">
                <h2 id="setup-title" className="font-semibold">
                    Completa tu salón · {done} de {steps.length}
                </h2>
                <div
                    className="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={steps.length}
                    aria-valuenow={done}
                >
                    <div
                        className="h-full rounded-full bg-primary transition-[width] duration-500"
                        style={{ width: `${(done / steps.length) * 100}%` }}
                    />
                </div>
                <p className="text-sm text-muted-foreground">
                    Una página completa genera mucha más confianza en quien te
                    visita por primera vez.
                </p>
            </div>
            <ul className="flex flex-col">
                {steps.map((step) => (
                    <li key={step.key}>
                        <Link
                            href={step.href}
                            className={cn(
                                'flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm hover:bg-accent',
                                step.done &&
                                    'text-muted-foreground line-through',
                            )}
                        >
                            {step.done ? (
                                <CheckCircle2 className="size-5 shrink-0 text-success" />
                            ) : (
                                <Circle className="size-5 shrink-0 text-muted-foreground" />
                            )}
                            <span className="flex-1">{step.label}</span>
                            {!step.done && (
                                <ChevronRight className="size-4 text-muted-foreground" />
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
