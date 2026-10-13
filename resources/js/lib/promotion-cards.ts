import type {
    Promotion,
    PromotionCardKind,
    PromotionFields,
    PromotionService,
} from '@/components/promotions/types';
import { addDays } from './promotions';

/*
 | The four cards a promotion starts from (docs/promociones.md, "Fichas"):
 | what each one proposes before the owner types anything.
 */

export const DEFAULT_PERCENT = 20;

/** How long a promotion runs unless its card says otherwise. */
const DEFAULT_DAYS = 14;

const QUIET_HOURS = {
    days: 30,
    weekdays: [1, 2, 3],
    from: '10:00',
    to: '16:00',
};

/** A campaign starts this many days before its date. */
const SEASON_LEAD_DAYS = 10;

/** The occasion the owner names with their own words. */
export const OTHER_OCCASION = 'other';

type Occasion = {
    key: string;
    label: string;
    /** Its date in a given year, "YYYY-MM-DD". */
    day: (year: number) => string;
};

const fixed = (month: number, day: number) => (year: number) =>
    `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

/** The nth Sunday of a month: Mother's and Father's Day move every year. */
const nthSunday = (month: number, nth: number) => (year: number) => {
    const firstWeekday = new Date(Date.UTC(year, month - 1, 1)).getUTCDay();
    const firstSunday = 1 + ((7 - firstWeekday) % 7);

    return fixed(month, firstSunday + (nth - 1) * 7)(year);
};

export const OCCASIONS: Occasion[] = [
    { key: 'valentine', label: 'San Valentín', day: fixed(2, 14) },
    { key: 'back-to-school', label: 'Regreso a clases', day: fixed(3, 1) },
    { key: 'mothers-day', label: 'Día de la Madre', day: nthSunday(5, 2) },
    { key: 'fathers-day', label: 'Día del Padre', day: nthSunday(6, 3) },
    { key: 'independence', label: 'Fiestas Patrias', day: fixed(7, 28) },
    { key: 'halloween', label: 'Halloween', day: fixed(10, 31) },
    { key: 'christmas', label: 'Navidad y Año Nuevo', day: fixed(12, 25) },
];

/** The next time an occasion comes, today included. */
export function nextOccurrence(occasion: Occasion, today: string): string {
    const year = Number(today.slice(0, 4));
    const thisYear = occasion.day(year);

    return thisYear >= today ? thisYear : occasion.day(year + 1);
}

/** The occasion that comes first from today. */
export function upcomingOccasion(today: string): Occasion {
    return [...OCCASIONS].sort((a, b) =>
        nextOccurrence(a, today).localeCompare(nextOccurrence(b, today)),
    )[0];
}

/**
 * The name and the dates a campaign proposes: from ten days before and up
 * to the day. Nothing for "Otra": the owner writes both.
 */
export function occasionDefaults(
    key: string,
    today: string,
): Pick<PromotionFields, 'overline' | 'starts_on' | 'ends_on'> | null {
    const occasion = OCCASIONS.find((one) => one.key === key);

    if (!occasion) {
        return null;
    }

    const day = nextOccurrence(occasion, today);
    const start = addDays(day, -SEASON_LEAD_DAYS);

    return {
        overline: occasion.label,
        starts_on: start < today ? today : start,
        ends_on: day,
    };
}

/** What a new promotion says before the owner changes anything. */
export function newPromotionFields(
    card: PromotionCardKind,
    today: string,
): PromotionFields {
    const isQuiet = card === 'quiet_hours';
    const occasion = card === 'season' ? upcomingOccasion(today) : null;

    return {
        card,
        mechanic: card === 'gift' ? 'gift' : 'discount',
        services: [],
        benefit: 'percent',
        percent: String(DEFAULT_PERCENT),
        amount: '',
        gift_service_id: null,
        starts_on: today,
        ends_on: addDays(today, isQuiet ? QUIET_HOURS.days : DEFAULT_DAYS),
        weekdays: isQuiet ? QUIET_HOURS.weekdays : [],
        from_time: isQuiet ? QUIET_HOURS.from : '',
        to_time: isQuiet ? QUIET_HOURS.to : '',
        quota: '',
        once_per_client: false,
        name: '',
        occasion: occasion?.key ?? '',
        overline: '',
        ...(occasion ? occasionDefaults(occasion.key, today) : {}),
    };
}

/**
 * A saved promotion back in the form. `name` is what the owner typed, so
 * a title the system wrote stays empty and is written again on saving.
 */
export function savedPromotionFields(
    promotion: Promotion,
    name: string,
): PromotionFields {
    const { form } = promotion;
    const occasion = OCCASIONS.find((one) => one.label === promotion.overline);

    return {
        card: promotion.card,
        mechanic: promotion.mechanic,
        services: form.services,
        benefit: form.amount === null ? 'percent' : 'amount',
        percent: form.percent === null ? '' : String(form.percent),
        amount: form.amount ?? '',
        gift_service_id: form.gift_service_id,
        starts_on: promotion.startsOn,
        ends_on: promotion.endsOn,
        weekdays: form.weekdays ?? [],
        from_time: form.from_time ?? '',
        to_time: form.to_time ?? '',
        quota: form.quota === null ? '' : String(form.quota),
        once_per_client: form.once_per_client,
        name,
        occasion:
            promotion.card === 'season'
                ? (occasion?.key ?? OTHER_OCCASION)
                : '',
        overline: promotion.overline ?? '',
    };
}

/** The form as the server expects it: empty text becomes "not set". */
export function promotionPayload(fields: PromotionFields, publish: boolean) {
    const isGift = fields.mechanic === 'gift';
    const isPercent = !isGift && fields.benefit === 'percent';
    const isAmount = !isGift && fields.benefit === 'amount';
    const text = (value: string) => (value.trim() === '' ? null : value.trim());

    return {
        card: fields.card,
        mechanic: fields.mechanic,
        services: fields.services,
        percent: isPercent ? text(fields.percent) : null,
        amount: isAmount ? text(fields.amount.replace(',', '.')) : null,
        gift_service_id: isGift ? fields.gift_service_id : null,
        starts_on: fields.starts_on,
        ends_on: fields.ends_on,
        weekdays: fields.weekdays.length === 0 ? null : fields.weekdays,
        from_time: text(fields.from_time),
        to_time: text(fields.to_time),
        quota: text(fields.quota),
        once_per_client: fields.once_per_client,
        name: text(fields.name),
        overline: fields.card === 'season' ? text(fields.overline) : null,
        ...(publish ? { publish: true } : {}),
    };
}

/** What can be given away: add-ons first, then the shortest. */
export function giftCandidates(
    services: PromotionService[],
): PromotionService[] {
    return services
        .filter((service) => service.canBeGift)
        .sort(
            (a, b) =>
                Number(b.isAddon) - Number(a.isAddon) || a.minutes - b.minutes,
        );
}

export type CardOffer = {
    card: PromotionCardKind;
    title: string;
    pitch: string;
    /** Written with the salon's own services; null when it has none to use. */
    example: string | null;
};

/** The four cards, each with an example made of the salon's own services. */
export function cardOffers(
    services: PromotionService[],
    today: string,
): CardOffer[] {
    const main = services.find((service) => !service.isAddon) ?? services[0];
    const gift = giftCandidates(services).find(
        (service) => service.id !== main?.id,
    );
    const discount = main ? `${DEFAULT_PERCENT} % menos en ${main.name}` : null;

    return [
        {
            card: 'discount',
            title: 'Descuento',
            pitch: 'Un porcentaje o un monto menos en los servicios que elijas.',
            example: discount,
        },
        {
            card: 'gift',
            title: 'Servicio de regalo',
            pitch: 'Un servicio corto sin costo al reservar otro.',
            example:
                main && gift ? `${gift.name} gratis con tu ${main.name}` : null,
        },
        {
            card: 'quiet_hours',
            title: 'Horas flojas',
            pitch: 'Un descuento solo en los días y horas con menos citas.',
            example:
                discount &&
                `${discount}, de lunes a miércoles de ${QUIET_HOURS.from} a ${QUIET_HOURS.to}`,
        },
        {
            card: 'season',
            title: 'Campaña de temporada',
            pitch: 'Una fecha especial con su nombre y sus días ya puestos.',
            example:
                discount && `${upcomingOccasion(today).label} · ${discount}`,
        },
    ];
}
