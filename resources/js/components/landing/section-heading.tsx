import { cn } from '@/lib/utils';
import { Reveal } from './reveal';

type Props = {
    eyebrow: string;
    title: string;
    description?: string;
    align?: 'center' | 'start';
};

export function SectionHeading({
    eyebrow,
    title,
    description,
    align = 'center',
}: Props) {
    return (
        <Reveal
            className={cn(
                'flex max-w-2xl flex-col gap-3',
                align === 'center' && 'mx-auto items-center text-center',
            )}
        >
            <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                {eyebrow}
            </p>
            <h2 className="font-display text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                {title}
            </h2>
            {description && (
                <p className="text-base text-pretty text-muted-foreground md:text-lg">
                    {description}
                </p>
            )}
        </Reveal>
    );
}
