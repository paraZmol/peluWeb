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

/** "Ven con alguien": the second one free (the other choice is half price). */
export const PAIR_FREE = 100;

export const PAIR_HALF = 50;

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
    /** "Primera visita": only for someone who never had a booking at the salon. */
    newClientsOnly: boolean;
    /**
     * A combo: what it is announced at (App\Domain\Promotions\ComboPrice);
     * its saving is `amountCents`.
     */
    combo?: ComboAnnounced | null;
};

/** "desde" when an option or a professional's own price may change it. */
export type ComboAnnounced = { priceCents: number; isFrom: boolean };

/** "A", "A y B", "A, B y C". */
export function list(items: string[]): string {
    const head = items.slice(0, -1);
    const last = items.at(-1) ?? '';

    return head.length === 0 ? last : `${head.join(', ')} y ${last}`;
}

/** 1000 → "10", 1050 → "10.50". */
export function soles(cents: number): string {
    return cents % 100 === 0 ? String(cents / 100) : fromCents(cents);
}

/** The service given away: its name, its normal price and how long it takes. */
export type GiftFacts = { name: string; priceCents: number; minutes: number };

/**
 * What the fine print says besides the terms: whose it is and the limits
 * the salon set on it (App\Domain\Promotions\PromotionFacts).
 */
export type PromotionFacts = {
    salonName: string;
    /** Bookings it takes; null = no limit. */
    quota: number | null;
    isOncePerClient: boolean;
    /** The salon asks for a deposit: the price is held until it is due. */
    hasDeposit: boolean;
    gift?: GiftFacts | null;
    /** Only with these professionals, in the team's order; empty = all of it. */
    staffNames?: string[];
};

/**
 * "del 5 al 18 de octubre de 2026"; with the month, and the year, of each
 * end when they differ.
 */
export function promotionPeriod(startsOn: string, endsOn: string): string {
    const [fromYear, fromMonth, fromDay] = startsOn.split('-').map(Number);
    const [toYear, toMonth, toDay] = endsOn.split('-').map(Number);
    const to = `${toDay} de ${MONTHS[toMonth - 1]} de ${toYear}`;

    if (fromYear !== toYear) {
        return `del ${fromDay} de ${MONTHS[fromMonth - 1]} de ${fromYear} al ${to}`;
    }

    return fromMonth !== toMonth
        ? `del ${fromDay} de ${MONTHS[fromMonth - 1]} al ${to}`
        : `del ${fromDay} al ${to}`;
}

/**
 * "20 % menos en Corte", "S/ 10 menos en Manicure", "Rasurado gratis con
 * tu Corte", "Corte + Barba por S/ 45" ("Combo Corte + Barba" without its
 * price, the name a combo is stored under); "… en tu primera visita" when
 * only newcomers get it.
 */
export function promotionTitle(
    terms: CopyTerms,
    serviceNames: string[],
    giftName?: string | null,
): string {
    const services =
        serviceNames.length > NAMES_IN_TITLE
            ? `${serviceNames.slice(0, NAMES_IN_TITLE).join(', ')} y más`
            : list(serviceNames);
    let title: string;

    if (terms.mechanic === 'combo' && !terms.combo) {
        // The name it is stored under: prices change, a name must not lie.
        title = `Combo ${serviceNames.join(' + ')}`;
    } else if (terms.mechanic === 'combo') {
        title = `${serviceNames.join(' + ')}${terms.combo?.isFrom ? ' desde' : ' por'} S/ ${soles(terms.combo?.priceCents ?? 0)}`;
    } else if (terms.mechanic === 'pair') {
        title =
            terms.percent === PAIR_FREE
                ? `2x1 en ${services}`
                : `Segundo ${services} a mitad de precio`;
    } else if (terms.mechanic === 'gift') {
        title = `${giftName} gratis con tu ${services}`;
    } else if (terms.percent !== null) {
        title = `${terms.percent} % menos en ${services}`;
    } else {
        title = `S/ ${soles(terms.amountCents ?? 0)} menos en ${services}`;
    }

    return terms.newClientsOnly ? `${title} en tu primera visita` : title;
}

/** Who may use it beyond the dates: newcomers only, or once each. */
function whoMayUseIt(terms: CopyTerms, facts: PromotionFacts): string | null {
    if (terms.newClientsOnly) {
        return `Solo para tu primera reserva en ${facts.salonName}: no vale si ya tuviste una cita allí, aunque no hayas asistido, ni si tienes otra reservada. Las citas canceladas no cuentan. Lo comprobamos con tu cuenta y tu teléfono.`;
    }

    return facts.isOncePerClient ? 'Una por cliente.' : null;
}

/** "Solo con Ana: al reservar, elige a esa persona."; null for all of the team. */
export function staffTerms(staffNames: string[] = []): string | null {
    if (staffNames.length === 0) {
        return null;
    }

    return `Solo con ${list(staffNames)}: al reservar, elige a ${staffNames.length === 1 ? 'esa persona.' : 'una de estas personas.'}`;
}

/** "lunes, martes y miércoles"; null for every day. */
function daysOf(terms: CopyTerms): string | null {
    return terms.weekdays === null || terms.weekdays.length === 0
        ? null
        : list(
              [...terms.weekdays]
                  .sort((a, b) => a - b)
                  .map((day) => WEEKDAYS[day - 1]),
          );
}

/** The days and the hours, each when it is named; null with neither. */
function daysAndHours(
    terms: CopyTerms,
    hours: (from: string, to: string) => string,
): string | null {
    const days = daysOf(terms);
    const between =
        terms.fromTime === null || terms.toTime === null
            ? null
            : hours(terms.fromTime, terms.toTime);

    return days !== null && between !== null
        ? `${days}, ${between}`
        : (days ?? between);
}

/** "lunes, martes y miércoles, de 10:00 a 16:00"; null when it names neither. */
export function promotionWhen(terms: CopyTerms): string | null {
    return daysAndHours(terms, (from, to) => `de ${from} a ${to}`);
}

/** What the gift is worth and what taking it means for the visit. */
function giftTerms({ name, priceCents, minutes }: GiftFacts): string {
    return `El regalo (${name}, precio normal S/ ${soles(priceCents)}) se hace en la misma cita y alarga la cita ${minutes} min. No se cambia por dinero ni por otro servicio.`;
}

/** That a combo is for its services together, and what changes its price. */
function comboTerms(terms: CopyTerms, serviceNames: string[]): string {
    return (
        `El precio del combo es por ${list(serviceNames)} juntos, en la misma cita y atendidos por una sola persona del equipo. ` +
        `Si eliges una opción o un profesional con un precio más alto, pagas esa diferencia y sigues ahorrando S/ ${soles(terms.amountCents ?? 0)}. ` +
        'Si quitas uno de los servicios, el combo no aplica y los demás se cobran a su precio normal.'
    );
}

/** Two people, one service each, one after the other; who pays what. */
function pairTerms(terms: CopyTerms): string {
    return (
        'Vale para dos personas que reservan juntas, en una sola reserva, el mismo servicio, atendidas una después de otra por la misma persona del equipo. ' +
        `Si eligen opciones de distinto precio, se cobra entero el de mayor precio y el otro ${terms.percent === PAIR_FREE ? 'es gratis' : 'va a mitad de precio'}. ` +
        'Al reservar se pide solo el nombre de quien acompaña. ' +
        'Si llega una sola persona, la promoción no aplica: se cobra a precio normal solo el servicio de quien vino y la seña pagada se descuenta de ese monto. ' +
        'El beneficio no se cambia por dinero ni se pasa a otra cita.'
    );
}

/** What an amount off says: it is given once, whatever is booked. */
function savingTerms(terms: CopyTerms, serviceNames: string[]): string | null {
    if (terms.mechanic === 'combo') {
        return comboTerms(terms, serviceNames);
    }

    if (terms.mechanic === 'pair') {
        return pairTerms(terms);
    }

    return terms.amountCents !== null && serviceNames.length > 1
        ? `El descuento de S/ ${soles(terms.amountCents)} se aplica una sola vez por reserva.`
        : null;
}

/**
 * What it is valid for, said in full and the same way every time: whose it
 * is, how long it runs, how many it takes and every limit the engine applies.
 */
export function promotionFinePrint(
    terms: CopyTerms,
    serviceNames: string[],
    facts: PromotionFacts,
): string {
    // By when the visit starts: that is how the engine reads the hours.
    const when = daysAndHours(
        terms,
        (from, to) => `en citas que empiecen entre las ${from} y las ${to}`,
    );

    return [
        `Promoción de ${facts.salonName}.`,
        `Válido para citas ${promotionPeriod(terms.startsOn, terms.endsOn)}.`,
        `Aplica a: ${serviceNames.join(', ')}.`,
        when === null ? null : `Solo ${when}.`,
        facts.quota === null
            ? 'Sin límite de cupos: vale para todas las citas que se reserven dentro de estas fechas, mientras haya horarios libres.'
            : `${quotaLabel(facts.quota)}.`,
        staffTerms(facts.staffNames),
        whoMayUseIt(terms, facts),
        savingTerms(terms, serviceNames),
        terms.mechanic === 'gift' && facts.gift ? giftTerms(facts.gift) : null,
        'Si cambias tu cita, la promoción se mantiene solo si la nueva fecha y hora cumplen estas condiciones.',
        facts.hasDeposit
            ? 'El precio con promoción se reserva hasta el plazo para enviar la seña.'
            : null,
        'Con reserva previa. No se suma a otras promociones: si tu cita cumple más de una, se aplica la de mayor ahorro. Precios finales en soles, con impuestos incluidos.',
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

type Titled = Pick<Promotion, 'name' | 'overline'>;

/** What the words to share a promotion are written from. */
export type SharedPromotion = Titled &
    Pick<Promotion, 'period' | 'shareUrl' | 'summary'> & {
        form: Pick<Promotion['form'], 'quota'>;
    };

/** "Halloween · 20 % menos en Corte": the campaign goes before the title. */
export function fullTitle(promotion: Titled): string {
    return promotion.overline
        ? `${promotion.overline} · ${promotion.name}`
        : promotion.name;
}

/** "Cupos: 30 reservas"; null without a limit. */
export function quotaLabel(quota: number | null): string | null {
    if (quota === null) {
        return null;
    }

    return `Cupos: ${quota} ${quota === 1 ? 'reserva' : 'reservas'}`;
}

/**
 * What goes with a shared promotion wherever it is posted: its title, how
 * long it runs, how many it takes and where its conditions are.
 */
function shareText(promotion: SharedPromotion): string {
    const quota = quotaLabel(promotion.form.quota);

    return [
        fullTitle(promotion),
        // Under a title of the salon's own, what it does.
        promotion.summary === null ? null : `${promotion.summary}.`,
        `Válido ${promotion.period}.`,
        quota === null ? null : `${quota}.`,
        `Condiciones y reserva: ${promotion.shareUrl}`,
    ]
        .filter((line) => line !== null)
        .join('\n');
}

/** Opens WhatsApp with the words and the link, to pick who gets it. */
export function whatsappShareUrl(promotion: SharedPromotion): string {
    return `https://wa.me/?text=${encodeURIComponent(shareText(promotion))}`;
}

/** A caption to paste under a post. */
export function instagramText(promotion: SharedPromotion): string {
    return shareText(promotion);
}
