import type {
    Subscription,
    SubscriptionState,
} from '@/components/subscription/types';

const dayMonthFormat = new Intl.DateTimeFormat('es-PE', {
    day: 'numeric',
    month: 'long',
});

const dayMonthYearFormat = new Intl.DateTimeFormat('es-PE', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/**
 * "2026-11-06" → "6 de noviembre"; the year is added only when it is not
 * this one ("5 de octubre de 2027"). Built from parts so no timezone shifts it.
 */
export function formatPlanDate(
    date: string,
    thisYear = new Date().getFullYear(),
): string {
    const [year, month, day] = date.split('-').map(Number);
    const format = year === thisYear ? dayMonthFormat : dayMonthYearFormat;

    return format.format(new Date(year, month - 1, day));
}

/** 30 → "faltan 30 días", 1 → "falta 1 día", 0 → "es el último día". */
export function daysLeftText(daysLeft: number): string {
    if (daysLeft <= 0) {
        return 'es el último día';
    }

    return daysLeft === 1 ? 'falta 1 día' : `faltan ${daysLeft} días`;
}

/** 1 → "1 mes", 12 → "12 meses". */
export function monthsText(months: number): string {
    return months === 1 ? '1 mes' : `${months} meses`;
}

/** 1 → "1 profesional con agenda", 6 → "Hasta 6 profesionales con agenda". */
export function staffLimitText(maxStaff: number): string {
    return maxStaff === 1
        ? '1 profesional con agenda'
        : `Hasta ${maxStaff} profesionales con agenda`;
}

type Standing = Pick<
    Subscription,
    'state' | 'coveredUntil' | 'daysLeft' | 'graceEndsOn'
> & { planName: string };

/** The headline of "Mi plan": where the salon stands, in plain words. */
export function standingHeadline(standing: Standing): string {
    const { state, coveredUntil, daysLeft, graceEndsOn } = standing;
    const until = coveredUntil ? formatPlanDate(coveredUntil) : null;

    if (state === 'trial' && until !== null && daysLeft !== null) {
        return `Tu prueba gratis termina el ${until} (${daysLeftText(daysLeft)}).`;
    }

    if (state === 'active') {
        return until === null || daysLeft === null
            ? 'Tu salón está al día.'
            : `Tu plan está pagado hasta el ${until} (${daysLeftText(daysLeft)}).`;
    }

    if (state === 'grace' && until !== null && graceEndsOn !== null) {
        return `Tu plan venció el ${until}; tienes hasta el ${formatPlanDate(graceEndsOn)} para pagar sin que el salón se pause.`;
    }

    if (state === 'suspended') {
        return 'La plataforma suspendió tu salón.';
    }

    return until === null
        ? 'Tu salón está en pausa: el pago venció y pasó el plazo de gracia.'
        : `Tu salón está en pausa: el plan venció el ${until} y pasó el plazo de gracia.`;
}

/** The line under the headline: what the state means day to day. */
export function standingDetail(standing: Standing): string {
    switch (standing.state) {
        case 'trial':
            return `Hasta entonces usas el plan ${standing.planName} completo, sin pagar nada.`;
        case 'active':
            return 'Todo funciona con normalidad. Te avisamos por correo antes de que venza.';
        case 'grace':
            return 'Mientras tanto todo sigue funcionando: tu equipo trabaja y tus clientes reservan.';
        case 'read_only':
            return 'Puedes ver todo y exportar tus datos, pero no crear, editar ni cobrar, y las reservas en línea están en pausa. No se borró nada: al registrar tu pago, el salón vuelve a funcionar al momento.';
        case 'suspended':
            return 'Puedes ver todo, pero no hacer cambios. No se borró nada. Escríbenos para saber cómo reactivarlo.';
    }
}

/** How long before the end a running trial or plan starts to remind. */
export const REMIND_DAYS = 7;

export type SubscriptionNotice = {
    message: string;
    /** Read only and suspended interrupt; the rest are polite warnings. */
    isBlocking: boolean;
    /** Only the owner can pay: everyone else gets no link. */
    showsPlanLink: boolean;
};

type NoticeInput = {
    state: SubscriptionState;
    daysLeft: number | null;
    isOwner: boolean;
};

/** The banner over every salon screen, or null when there is nothing to say. */
export function subscriptionNotice({
    state,
    daysLeft,
    isOwner,
}: NoticeInput): SubscriptionNotice | null {
    if (state === 'read_only' || state === 'suspended') {
        return {
            message: isOwner
                ? blockedOwnerMessage(state)
                : 'El salón está en pausa: puedes ver todo, pero no hacer cambios. Habla con quien administra el salón.',
            isBlocking: true,
            showsPlanLink: isOwner,
        };
    }

    if (!isOwner) {
        return null;
    }

    if (state === 'grace') {
        return {
            message:
                'Tu pago está vencido. Paga pronto para que el salón no se pause.',
            isBlocking: false,
            showsPlanLink: true,
        };
    }

    if (daysLeft === null || daysLeft > REMIND_DAYS) {
        return null;
    }

    const when = daysLeft <= 0 ? 'hoy' : `en ${daysLeftCount(daysLeft)}`;

    return {
        message:
            state === 'trial'
                ? `Tu prueba gratis termina ${when}.`
                : `Tu plan vence ${when}. Paga a tiempo para seguir sin cortes.`,
        isBlocking: false,
        showsPlanLink: true,
    };
}

function daysLeftCount(daysLeft: number): string {
    return daysLeft === 1 ? '1 día' : `${daysLeft} días`;
}

function blockedOwnerMessage(state: 'read_only' | 'suspended'): string {
    return state === 'read_only'
        ? 'Tu salón está en pausa: puedes ver y exportar todo, pero no hacer cambios ni recibir reservas en línea. Al pagar se reactiva al momento.'
        : 'Tu salón está suspendido: puedes ver todo, pero no hacer cambios. Escríbenos para reactivarlo.';
}
