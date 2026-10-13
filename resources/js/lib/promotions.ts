import type {
    Promotion,
    PromotionMechanic,
} from '@/components/promotions/types';
import { fromCents } from './money';

/*
 | The words and the numbers of a promotion, as the server writes them
 | (App\Domain\Promotions\PromotionCopy and PromotionTerms), so the preview
 | of one that is not saved yet says what the saved one will say.
 */

const WEEKDAYS = [
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
    'domingo',
];

const MONTHS = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

/** With more services than this, the title says "y más" after the first two. */
const NAMES_IN_TITLE = 2;

/** Prices with a promotion are rounded down to this. */
const ROUNDING_CENTS = 10;

export type CopyTerms = {
    mechanic: PromotionMechanic;
    percent: number | null;
    amountCents: number | null;
    /** "2026-10-05". */
    startsOn: string;
    endsOn: string;
    /** 1 = Monday … 7 = Sunday; null for every day. */
    weekdays: number[] | null;
    /** "10:00"; null for any hour. */
    fromTime: string | null;
    toTime: string | null;
};

/** "A", "A y B", "A, B y C". */
function list(items: string[]): string {
    const head = items.slice(0, -1);
    const last = items.at(-1) ?? '';

    return head.length === 0 ? last : `${head.join(', ')} y ${last}`;
}

/** 1000 → "10", 1050 → "10.50". */
export function soles(cents: number): string {
    return cents % 100 === 0 ? String(cents / 100) : fromCents(cents);
}

/** "2026-10-05" → "5 de octubre". */
function longDay(date: string): string {
    const [, month, day] = date.split('-').map(Number);

    return `${day} de ${MONTHS[month - 1]}`;
}

/** "20 % menos en Corte", "S/ 10 menos en Manicure", "Rasurado gratis con tu Corte". */
export function promotionTitle(
    terms: CopyTerms,
    serviceNames: string[],
    giftName?: string | null,
): string {
    const services =
        serviceNames.length > NAMES_IN_TITLE
            ? `${serviceNames.slice(0, NAMES_IN_TITLE).join(', ')} y más`
            : list(serviceNames);

    if (terms.mechanic === 'gift') {
        return `${giftName} gratis con tu ${services}`;
    }

    return terms.percent !== null
        ? `${terms.percent} % menos en ${services}`
        : `S/ ${soles(terms.amountCents ?? 0)} menos en ${services}`;
}

/** "lunes, martes y miércoles, de 10:00 a 16:00"; null when it names neither. */
export function promotionWhen(terms: CopyTerms): string | null {
    const days =
        terms.weekdays === null || terms.weekdays.length === 0
            ? null
            : list(
                  [...terms.weekdays]
                      .sort((a, b) => a - b)
                      .map((day) => WEEKDAYS[day - 1]),
              );
    const hours =
        terms.fromTime === null || terms.toTime === null
            ? null
            : `de ${terms.fromTime} a ${terms.toTime}`;

    return days !== null && hours !== null
        ? `${days}, ${hours}`
        : (days ?? hours);
}

/** What it is valid for, said in full and the same way every time. */
export function promotionFinePrint(
    terms: CopyTerms,
    serviceNames: string[],
    quota: number | null,
    isOncePerClient: boolean,
): string {
    const when = promotionWhen(terms);

    return [
        `Válido para citas del ${longDay(terms.startsOn)} al ${longDay(terms.endsOn)}.`,
        `Aplica a: ${serviceNames.join(', ')}.`,
        when === null ? null : `Solo ${when}.`,
        quota === null
            ? 'Sin límite de cupos, sujeto a horarios disponibles.'
            : `Cupos: ${quota}.`,
        isOncePerClient ? 'Una por cliente.' : null,
        terms.mechanic === 'gift'
            ? 'El servicio de regalo se realiza en la misma cita y no es canjeable por dinero ni por otro servicio.'
            : null,
        'Con reserva previa. No acumulable con otras promociones. Precios en soles con IGV incluido.',
    ]
        .filter((part) => part !== null)
        .join(' ');
}

/** Rounding must not turn a discount into a gift. */
function roundedDown(cents: number): number {
    const rounded = cents - (cents % ROUNDING_CENTS);

    return rounded > 0 ? rounded : cents;
}

/** What a service costs with a percentage off. */
export function percentPrice(priceCents: number, percent: number): number {
    return roundedDown(Math.floor((priceCents * (100 - percent)) / 100));
}

/**
 * What a service costs with a fixed amount off; its normal price when it
 * does not cost more than the amount ("S/ 10 less" never makes it free).
 */
export function amountPrice(priceCents: number, amountCents: number): number {
    return priceCents <= amountCents
        ? priceCents
        : roundedDown(priceCents - amountCents);
}

/** The deposit asked on a total, rounded to the céntimo like the server. */
export function depositCents(totalCents: number, percent: number): number {
    return Math.floor((totalCents * percent + 50) / 100);
}

/** "2026-10-07" + 14 → "2026-10-21". Calendar days, no time zone involved. */
export function addDays(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}

/** "2026-10-07" → "7 oct". */
export function shortDay(date: string): string {
    const [, month, day] = date.split('-').map(Number);

    return `${day} ${MONTHS[month - 1].slice(0, 3)}`;
}

/** "7 oct – 21 oct". */
export function dateRange(startsOn: string, endsOn: string): string {
    return `${shortDay(startsOn)} – ${shortDay(endsOn)}`;
}

type Shared = Pick<
    Promotion,
    'name' | 'overline' | 'startsOn' | 'endsOn' | 'shareUrl'
>;

/** "Halloween · 20 % menos en Corte": the campaign goes before the title. */
export function fullTitle(promotion: Pick<Shared, 'name' | 'overline'>) {
    return promotion.overline
        ? `${promotion.overline} · ${promotion.name}`
        : promotion.name;
}

/** Opens WhatsApp with the title and the link, to pick who gets it. */
export function whatsappShareUrl(promotion: Shared): string {
    const text = `${fullTitle(promotion)}\n${promotion.shareUrl}`;

    return `https://wa.me/?text=${encodeURIComponent(text)}`;
}

/** A caption to paste under a post: title, dates and where to book. */
export function instagramText(promotion: Shared): string {
    return [
        fullTitle(promotion),
        `Del ${longDay(promotion.startsOn)} al ${longDay(promotion.endsOn)}.`,
        `Reserva aquí: ${promotion.shareUrl}`,
    ].join('\n');
}
