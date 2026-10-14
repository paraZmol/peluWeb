import type {
    PromotionContext,
    PromotionFields,
    PromotionService,
} from '@/components/promotions/types';
import { toCents } from './money';
import {
    comboIsFrom,
    comboNormalCents,
    isComboComplete,
} from './promotion-combo';
import {
    amountPrice,
    depositCents,
    list,
    PAIR_FREE,
    percentPrice,
    promotionFinePrint,
    promotionTitle,
    soles,
} from './promotions';
import type { CopyTerms } from './promotions';

/** One price of the card: what it costs normally and with the promotion. */
export type PreviewLine = {
    name: string;
    normalCents: number;
    promoCents: number;
    isGift: boolean;
};

export type PromotionPreview = {
    overline: string | null;
    title: string;
    /**
     * What it does, under the title (like the public card's summary): a
     * combo's live price, or the system's title under one of the owner's.
     */
    subtitle: string | null;
    lines: PreviewLine[];
    finePrint: string;
    /** What it means for the salon, sentence by sentence. */
    summary: string[];
};

const cents = (service: PromotionService) => toCents(service.price) ?? 0;

/** The services picked, in the catalogue's order (the title names them so). */
function picked(fields: PromotionFields, services: PromotionService[]) {
    return services.filter((service) => fields.services.includes(service.id));
}

/** When and to whom: the same for every kind. */
function periodOf(fields: PromotionFields) {
    return {
        startsOn: fields.starts_on,
        endsOn: fields.ends_on,
        weekdays: fields.weekdays.length === 0 ? null : fields.weekdays,
        fromTime: fields.from_time || null,
        toTime: fields.to_time || null,
        newClientsOnly: fields.card === 'first_visit',
    };
}

/**
 * A combo saves what its services cost apart less its price; null until
 * it has its services and a price below what they cost apart.
 */
function comboTermsOf(
    fields: PromotionFields,
    chosen: PromotionService[],
): CopyTerms | null {
    const priceCents = toCents(fields.combo_price) ?? 0;
    const normalCents = comboNormalCents(chosen);

    if (
        !isComboComplete(chosen) ||
        priceCents <= 0 ||
        priceCents >= normalCents
    ) {
        return null;
    }

    return {
        mechanic: 'combo',
        percent: null,
        amountCents: normalCents - priceCents,
        ...periodOf(fields),
        combo: { priceCents, isFrom: comboIsFrom(chosen) },
    };
}

/** What the form says so far, in the words of the copy; null while incomplete. */
function termsOf(
    fields: PromotionFields,
    services: PromotionService[],
): CopyTerms | null {
    if (fields.starts_on === '' || fields.ends_on === '') {
        return null;
    }

    if (fields.mechanic === 'combo') {
        return comboTermsOf(fields, picked(fields, services));
    }

    const isGift = fields.mechanic === 'gift';
    const isPercent =
        fields.mechanic === 'pair' || (!isGift && fields.benefit === 'percent');
    const percent = isPercent ? Number(fields.percent) : null;
    const amountCents =
        isGift || isPercent ? null : (toCents(fields.amount) ?? 0);

    if (
        (percent !== null && !(Number.isInteger(percent) && percent > 0)) ||
        amountCents === 0
    ) {
        return null;
    }

    return {
        mechanic: fields.mechanic,
        percent,
        amountCents,
        ...periodOf(fields),
    };
}

/**
 * The title the system would write for the form as it is; null while
 * incomplete. A combo's goes without its price: that is said live.
 */
export function autoTitle(
    fields: PromotionFields,
    services: PromotionService[],
): string | null {
    const terms = termsOf(fields, services);
    const chosen = picked(fields, services);
    const gift = services.find(
        (service) => service.id === fields.gift_service_id,
    );

    if (!terms || chosen.length === 0 || (terms.mechanic === 'gift' && !gift)) {
        return null;
    }

    return promotionTitle(
        { ...terms, combo: null },
        chosen.map((service) => service.name),
        gift?.name,
    );
}

/** "Corte + Barba por S/ 45" under "Combo Corte + Barba" or an own title. */
function subtitleOf(
    terms: CopyTerms,
    names: string[],
    title: string,
    ownTitle: string,
): string | null {
    if (terms.combo) {
        return promotionTitle(terms, names);
    }

    return ownTitle !== '' && ownTitle !== title ? title : null;
}

function deposit(totalCents: number, percent: number): string[] {
    return percent > 0
        ? [`Seña: S/ ${soles(depositCents(totalCents, percent))}.`]
        : [];
}

/** The services together, the price they cost apart crossed out. */
function comboLines(names: string[], terms: CopyTerms): PreviewLine[] {
    const promoCents = terms.combo?.priceCents ?? 0;

    return [
        {
            name: names.join(' + '),
            normalCents: promoCents + (terms.amountCents ?? 0),
            promoCents,
            isGift: false,
        },
    ];
}

function comboSummary(names: string[], line: PreviewLine): string[] {
    return [
        `El cliente paga S/ ${soles(line.promoCents)} por ${list(names)} juntos, en vez de S/ ${soles(line.normalCents)}.`,
        'El ahorro se reparte entre los servicios según su precio. Quien atiende comisiona sobre lo cobrado.',
    ];
}

/** The same service twice: the first paid whole, the second with the benefit. */
function pairLines(service: PromotionService, percent: number): PreviewLine[] {
    return [
        {
            name: service.name,
            normalCents: cents(service),
            promoCents: cents(service),
            isGift: false,
        },
        {
            name: `${service.name} · quien acompaña`,
            normalCents: cents(service),
            promoCents: percentPrice(cents(service), percent),
            isGift: percent === PAIR_FREE,
        },
    ];
}

function pairSummary(lines: PreviewLine[]): string[] {
    const paid = lines.reduce((total, line) => total + line.promoCents, 0);
    const normal = lines.reduce((total, line) => total + line.normalCents, 0);

    return [
        `Dos personas pagan S/ ${soles(paid)} en vez de S/ ${soles(normal)}.`,
        `Quien atiende hace dos servicios y comisiona sobre S/ ${soles(paid)}.`,
    ];
}

function giftLines(main: PromotionService, gift: PromotionService) {
    return [
        {
            name: main.name,
            normalCents: cents(main),
            promoCents: cents(main),
            isGift: false,
        },
        {
            name: gift.name,
            normalCents: cents(gift),
            promoCents: 0,
            isGift: true,
        },
    ];
}

/**
 * The card as the client will read it and what it means for the salon,
 * from the form as it is. Null until there is enough to say something.
 */
export function previewOf(
    fields: PromotionFields,
    { services, depositPercent, salonName }: PromotionContext,
): PromotionPreview | null {
    const terms = termsOf(fields, services);
    const title = autoTitle(fields, services);
    const chosen = picked(fields, services);

    if (!terms || title === null) {
        return null;
    }

    const names = chosen.map((service) => service.name);
    const gift = services.find(
        (service) => service.id === fields.gift_service_id,
    );
    const priced = (service: PromotionService): PreviewLine => ({
        name: service.name,
        normalCents: cents(service),
        promoCents:
            terms.percent !== null
                ? percentPrice(cents(service), terms.percent)
                : amountPrice(cents(service), terms.amountCents ?? 0),
        isGift: false,
    });
    const isCombo = terms.mechanic === 'combo';
    const isPair = terms.mechanic === 'pair';
    let lines = chosen.map(priced);

    if (isCombo) {
        lines = comboLines(names, terms);
    } else if (isPair) {
        lines = pairLines(chosen[0], terms.percent ?? 0);
    } else if (gift) {
        lines = giftLines(chosen[0], gift);
    }

    const [first] = lines;
    const example =
        chosen.length > 1 && !isCombo ? [`Ejemplo con ${first.name}:`] : [];
    const summary = isCombo
        ? comboSummary(names, first)
        : isPair
          ? pairSummary(lines)
          : gift
            ? [
                  `El cliente paga S/ ${soles(first.normalCents)} por ${first.name} y recibe ${gift.name} (S/ ${soles(cents(gift))}) sin costo.`,
                  'Quien atiende no comisiona por el servicio de regalo.',
              ]
            : [
                  `El cliente paga S/ ${soles(first.promoCents)} en vez de S/ ${soles(first.normalCents)}.`,
                  `Quien atiende comisiona sobre S/ ${soles(first.promoCents)}.`,
              ];
    const once =
        terms.amountCents !== null && chosen.length > 1 && !isCombo
            ? ['El monto se descuenta una sola vez por reserva.']
            : [];

    return {
        overline:
            fields.card === 'season' ? fields.overline.trim() || null : null,
        title: fields.name.trim() || title,
        subtitle: subtitleOf(terms, names, title, fields.name.trim()),
        lines,
        finePrint: promotionFinePrint(terms, names, {
            salonName,
            quota: fields.quota.trim() === '' ? null : Number(fields.quota),
            isOncePerClient: fields.once_per_client,
            hasDeposit: depositPercent > 0,
            gift: gift && {
                name: gift.name,
                priceCents: cents(gift),
                minutes: gift.minutes,
            },
        }),
        summary: [
            ...example,
            ...summary,
            ...deposit(
                isPair
                    ? lines.reduce((total, line) => total + line.promoCents, 0)
                    : first.promoCents,
                depositPercent,
            ),
            ...once,
        ],
    };
}
