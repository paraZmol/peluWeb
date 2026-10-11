import type { MyDataCare } from '@/components/my-data/types';
import { ACCOUNT_METHOD, formatLongDay } from './clients';

/** Since when the care notes are authorized and how; null while they are not. */
export function careStatus(care: MyDataCare): string | null {
    if (!care.isAuthorized || care.since === null) {
        return null;
    }

    const day = formatLongDay(care.since);

    return care.method === ACCOUNT_METHOD
        ? `Autorizaste el ${day}`
        : `Firmaste una hoja en el salón el ${day}`;
}

/** 1 → "1 foto", 3 → "3 fotos". */
export function photosCountLabel(count: number): string {
    return count === 1 ? '1 foto' : `${count} fotos`;
}

/** What taking the pictures back erases, said before doing it. */
export function photosEraseWarning(count: number, salonName: string): string {
    if (count === 0) {
        return `${salonName} ya no podrá guardar fotos de tus servicios.`;
    }

    return count === 1
        ? `Se borrará la foto que ${salonName} guarda de tus servicios. No se puede recuperar.`
        : `Se borrarán las ${count} fotos que ${salonName} guarda de tus servicios. No se pueden recuperar.`;
}

/** Why the salon keeps no pictures after the person took them back. */
export function photosBlockedNotice(
    withdrawnOn: string | null,
    salonName: string,
): string {
    const when =
        withdrawnOn === null ? '' : ` el ${formatLongDay(withdrawnOn)}`;

    return `Retiraste tu autorización${when}: ${salonName} no puede guardar fotos tuyas.`;
}
