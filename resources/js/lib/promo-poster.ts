import type {
    Promotion,
    PromotionService,
} from '@/components/promotions/types';
import { comboIsFrom } from './promotion-combo';
import type { PromotionPreview } from './promotion-preview';
import { list, PAIR_FREE, quotaLabel, soles } from './promotions';

/*
 | The words of the image to post a promotion on Instagram. Like every piece
 | that is shared, it says how long it runs (with the year), its places when
 | it has a limit and where its conditions are (docs/legal, P17).
 */

/**
 * The offer in big, under the normal price crossed out: "S/ 28" after
 * "S/ 35", "Todo por S/ 45", "2x1", "Gratis".
 */
export type PosterBenefit = {
    /** The normal price, crossed out; null when nothing is crossed out. */
    normal: string | null;
    headline: string;
    /** What it is on, or what it saves. */
    caption: string | null;
};

export type PosterContent = {
    salonName: string;
    overline: string | null;
    title: string;
    /** The system's line under a title of the salon's own. */
    subtitle: string | null;
    benefit: PosterBenefit | null;
    /** With whom, when, how long and how many: each one a line. */
    facts: string[];
    link: string;
};

export type PosterSource = {
    promotion: Promotion;
    /** The card as the form previews it; null when it cannot say it. */
    preview: PromotionPreview | null;
    services: PromotionService[];
    /** Who it is limited to, in the team's order; empty = all of the team. */
    staffNames: string[];
    salonName: string;
};

const money = (cents: number) => `S/ ${soles(cents)}`;

/** The offer as a price: the first line of the preview says it. */
function benefitOf({
    promotion,
    preview,
    services,
}: PosterSource): PosterBenefit | null {
    const [first] = preview?.lines ?? [];

    if (!preview || !first) {
        return null;
    }

    const chosen = services.filter((service) =>
        promotion.form.services.includes(service.id),
    );
    const from = chosen.some((service) => service.hasOptions) ? 'desde ' : '';

    if (promotion.mechanic === 'combo') {
        return {
            normal: money(first.normalCents),
            headline: `Todo ${comboIsFrom(chosen) ? 'desde' : 'por'} ${money(first.promoCents)}`,
            caption: `${first.name}, juntos: ahorras ${money(first.normalCents - first.promoCents)}`,
        };
    }

    if (promotion.mechanic === 'pair') {
        const total = (pick: 'normalCents' | 'promoCents') =>
            preview.lines.reduce((sum, line) => sum + line[pick], 0);

        return {
            normal: money(total('normalCents')),
            headline:
                promotion.form.percent === PAIR_FREE
                    ? '2x1'
                    : '2.º a mitad de precio',
            caption: `Los dos por ${money(total('promoCents'))}: ${first.name} para ti y para quien te acompaña`,
        };
    }

    if (promotion.mechanic === 'gift') {
        const gift = preview.lines.find((line) => line.isGift);

        return {
            normal: gift ? money(gift.normalCents) : null,
            headline: 'Gratis',
            caption: `${gift?.name ?? 'Un regalo'} con tu ${first.name}`,
        };
    }

    // The title already says how much less: in big, what it costs.
    return {
        normal: money(first.normalCents),
        headline: `${from}${money(first.promoCents)}`,
        caption: chosen.length > 1 ? `Ejemplo: ${first.name}` : first.name,
    };
}

/** "lunes, de 10:00 a 16:00" → "Solo lunes, de 10:00 a 16:00". */
const only = (when: string) => `Solo ${when}`;

/** Everything the image says, line by line, from the saved promotion. */
export function posterContent(source: PosterSource): PosterContent {
    const { promotion, staffNames, salonName } = source;
    const quota = quotaLabel(promotion.form.quota);

    return {
        salonName,
        overline: promotion.overline,
        title: promotion.name,
        subtitle: promotion.summary,
        benefit: benefitOf(source),
        facts: [
            staffNames.length > 0 ? `Solo con ${list(staffNames)}` : null,
            promotion.when === null ? null : only(promotion.when),
            `Válido para citas ${promotion.period}`,
            quota,
        ].filter((fact) => fact !== null),
        link: `Condiciones y reserva: ${promotion.shareUrl.replace(/^https?:\/\//, '')}`,
    };
}

/** "promocion-12.png": what the downloaded image is called. */
export function posterFileName(promotion: Pick<Promotion, 'id'>): string {
    return `promocion-${promotion.id}.png`;
}
