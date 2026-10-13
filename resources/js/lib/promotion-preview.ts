import type {
    PromotionContext,
    PromotionFields,
    PromotionService,
} from '@/components/promotions/types';
import { toCents } from './money';
import {
    amountPrice,
    depositCents,
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

/** What the form says so far, in the words of the copy; null while incomplete. */
function termsOf(fields: PromotionFields): CopyTerms | null {
    const isGift = fields.mechanic === 'gift';
    const isPercent = !isGift && fields.benefit === 'percent';
    const percent = isPercent ? Number(fields.percent) : null;
    const amountCents =
        isGift || isPercent ? null : (toCents(fields.amount) ?? 0);

    if (
        (percent !== null && !(Number.isInteger(percent) && percent > 0)) ||
        amountCents === 0 ||
        fields.starts_on === '' ||
        fields.ends_on === ''
    ) {
        return null;
    }

    return {
        mechanic: fields.mechanic,
        percent,
        amountCents,
        startsOn: fields.starts_on,
        endsOn: fields.ends_on,
        weekdays: fields.weekdays.length === 0 ? null : fields.weekdays,
        fromTime: fields.from_time || null,
        toTime: fields.to_time || null,
    };
}

/** The title the system would write for the form as it is; null while incomplete. */
export function autoTitle(
    fields: PromotionFields,
    services: PromotionService[],
): string | null {
    const terms = termsOf(fields);
    const chosen = picked(fields, services);
    const gift = services.find(
        (service) => service.id === fields.gift_service_id,
    );

    if (!terms || chosen.length === 0 || (terms.mechanic === 'gift' && !gift)) {
        return null;
    }

    return promotionTitle(
        terms,
        chosen.map((service) => service.name),
        gift?.name,
    );
}

function deposit(totalCents: number, percent: number): string[] {
    return percent > 0
        ? [`Seña: S/ ${soles(depositCents(totalCents, percent))}.`]
        : [];
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
    { services, depositPercent }: PromotionContext,
): PromotionPreview | null {
    const terms = termsOf(fields);
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
    const lines = gift ? giftLines(chosen[0], gift) : chosen.map(priced);
    const [first] = lines;
    const example = chosen.length > 1 ? [`Ejemplo con ${first.name}:`] : [];
    const summary = gift
        ? [
              `El cliente paga S/ ${soles(first.normalCents)} por ${first.name} y recibe ${gift.name} (S/ ${soles(cents(gift))}) sin costo.`,
              'Quien atiende no comisiona por el servicio de regalo.',
          ]
        : [
              `El cliente paga S/ ${soles(first.promoCents)} en vez de S/ ${soles(first.normalCents)}.`,
              `Quien atiende comisiona sobre S/ ${soles(first.promoCents)}.`,
          ];
    const once =
        terms.amountCents !== null && chosen.length > 1
            ? ['El monto se descuenta una sola vez por reserva.']
            : [];

    return {
        overline:
            fields.card === 'season' ? fields.overline.trim() || null : null,
        title: fields.name.trim() || title,
        lines,
        finePrint: promotionFinePrint(
            terms,
            names,
            fields.quota.trim() === '' ? null : Number(fields.quota),
            fields.once_per_client,
        ),
        summary: [
            ...example,
            ...summary,
            ...deposit(first.promoCents, depositPercent),
            ...once,
        ],
    };
}
