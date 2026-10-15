import { CalendarDays, Clock, UserRound } from 'lucide-react';
import { formatPrice } from '@/lib/format';
import { shortWhen } from '@/lib/promo-when';
import { dateRange, list } from '@/lib/promotions';
import { PromotionActions } from './promotion-actions';
import { PromotionStateTag } from './promotion-state-tag';
import type { Promotion, TeamMember } from './types';

type Props = {
    promotion: Promotion;
    /** To name who it is limited to. */
    team: TeamMember[];
    onEdit: (promotion: Promotion) => void;
    onShare: (promotion: Promotion) => void;
};

/** "3 de 10" with a limit, "3" without one. */
function places({ results, form }: Promotion): string {
    return form.quota === null
        ? String(results.placesUsed)
        : `${results.placesUsed} de ${form.quota}`;
}

function Figure({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col-reverse">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-semibold tabular-nums">{value}</dd>
        </div>
    );
}

/** One promotion of the salon: what it says, where it stands, what it brought. */
export function PromotionCard({ promotion, team, onEdit, onShare }: Props) {
    const { results } = promotion;
    const when = shortWhen(promotion.when);
    const staffNames = team
        .filter((person) => promotion.form.staff?.includes(person.id))
        .map((person) => person.name);

    return (
        <li
            aria-label={promotion.name}
            className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-card sm:p-5"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    {promotion.overline && (
                        <p className="text-xs font-bold tracking-[.16em] text-brand-text uppercase">
                            {promotion.overline}
                        </p>
                    )}
                    <h3 className="font-display text-xl leading-snug font-semibold break-words">
                        {promotion.name}
                    </h3>
                    {promotion.summary && (
                        <p className="text-sm font-medium break-words">
                            {promotion.summary}
                        </p>
                    )}
                </div>
                <PromotionStateTag
                    state={promotion.state}
                    label={promotion.stateLabel}
                />
            </div>

            <div className="flex flex-col gap-1 text-sm text-muted-foreground">
                <p className="flex items-center gap-2">
                    <CalendarDays className="size-4 shrink-0" aria-hidden />
                    <span className="tabular-nums">
                        {dateRange(promotion.startsOn, promotion.endsOn)}
                    </span>
                </p>
                {when && (
                    <p className="flex items-start gap-2">
                        <Clock className="mt-0.5 size-4 shrink-0" aria-hidden />
                        <span className="first-letter:uppercase">{when}</span>
                    </p>
                )}
                {staffNames.length > 0 && (
                    <p className="flex items-start gap-2">
                        <UserRound
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        <span>Solo con {list(staffNames)}</span>
                    </p>
                )}
            </div>

            {promotion.state === 'needs_review' && (
                <p className="rounded-lg bg-warning/15 px-3 py-2 text-sm">
                    Uno de sus servicios ya no está disponible, así que dejó de
                    ofrecerse. Duplícala con los servicios de hoy para volver a
                    publicarla.
                </p>
            )}

            <dl className="grid grid-cols-3 gap-3 rounded-lg bg-band p-3">
                <Figure label="Reservas" value={String(results.bookings)} />
                <Figure label="Completadas" value={String(results.completed)} />
                <Figure label="Cupos usados" value={places(promotion)} />
                <Figure label="Cobrado" value={formatPrice(results.charged)} />
                <Figure
                    label="Regalado"
                    value={formatPrice(results.givenAway)}
                />
            </dl>

            <PromotionActions
                promotion={promotion}
                onEdit={() => onEdit(promotion)}
                onShare={() => onShare(promotion)}
            />
        </li>
    );
}
