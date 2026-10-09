import { cn } from '@/lib/utils';

type Props = {
    title: string;
    subtitle?: string | null;
    className?: string;
};

/** The one question of a step, in the template's display face. */
export function StepHeading({ title, subtitle, className }: Props) {
    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <h1 className="display-type text-title-2">{title}</h1>
            {subtitle && (
                <p className="text-base text-pretty text-muted-foreground">
                    {subtitle}
                </p>
            )}
        </div>
    );
}
