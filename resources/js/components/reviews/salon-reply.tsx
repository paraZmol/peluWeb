import { CornerDownRight } from 'lucide-react';
import { formatLongDay } from '@/lib/clients';
import { cn } from '@/lib/utils';

type Props = {
    /** "Respuesta de {Salón}". */
    title: string;
    text: string;
    /** "Y-m-d" on the salon's clock, when it is known. */
    repliedOn?: string | null;
    className?: string;
};

/** What the salon answered, set in from the review it answers. */
export function SalonReply({ title, text, repliedOn, className }: Props) {
    return (
        <div
            className={cn(
                'ml-3 flex flex-col gap-1 border-l-2 border-brand-line pl-4 sm:ml-5',
                className,
            )}
        >
            {/* The arrow stays beside the title even when the name wraps. */}
            <p className="flex items-start gap-2 text-sm font-semibold">
                <CornerDownRight
                    className="mt-0.5 size-4 shrink-0 text-brand-text"
                    aria-hidden
                />
                <span className="flex min-w-0 flex-wrap gap-x-2 gap-y-0.5">
                    <span className="min-w-0 break-words">{title}</span>
                    {repliedOn && (
                        <span className="font-normal text-muted-foreground tabular-nums">
                            {formatLongDay(repliedOn)}
                        </span>
                    )}
                </span>
            </p>
            <p className="text-sm break-words whitespace-pre-line text-muted-foreground">
                {text}
            </p>
        </div>
    );
}
