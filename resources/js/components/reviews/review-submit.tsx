import { Globe, Lock } from 'lucide-react';
import { reviewAudienceNotice, reviewSubmitLabel } from '@/lib/reviews';
import { PRIMARY_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { NetworkProblem } from './network-problem';

type Props = {
    salonName: string;
    isPublic: boolean;
    processing: boolean;
    hasNetworkProblem: boolean;
};

/**
 * Who is going to read the review, said right above the button that sends
 * it; the button names what it does ("Publicar" or "Enviar al salón").
 */
export function ReviewSubmit({
    salonName,
    isPublic,
    processing,
    hasNetworkProblem,
}: Props) {
    const AudienceIcon = isPublic ? Globe : Lock;

    return (
        <div className="flex flex-col gap-3">
            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                <AudienceIcon
                    className="mt-0.5 size-4 shrink-0 text-brand-text"
                    aria-hidden
                />
                <span className="min-w-0 break-words">
                    {reviewAudienceNotice(isPublic, salonName)}
                </span>
            </p>
            <NetworkProblem isShown={hasNetworkProblem} />
            <button
                type="submit"
                disabled={processing}
                aria-busy={processing}
                className={cn(PRIMARY_ACTION, 'w-full sm:w-fit')}
            >
                {processing ? 'Enviando…' : reviewSubmitLabel(isPublic)}
            </button>
        </div>
    );
}
