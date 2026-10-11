import type { MyDataCare, MyDataPhotos } from '@/components/my-data/types';
import { ACCOUNT_METHOD, formatLongDay } from './clients';

/** Since when the care notes are authorized and how; null while they are not. */
export function careStatus(care: MyDataCare, salonName: string): string | null {
    if (!care.isAuthorized || care.since === null) {
        return null;
    }

    const day = formatLongDay(care.since);

    return care.method === ACCOUNT_METHOD
        ? `Autorizaste el ${day}`
        : `${salonName} registró que firmaste una hoja el ${day}. Si no fue así, retira la autorización.`;
}

/**
 * The salon keeps pictures under an authorization the person did not give
 * from this account: it says who recorded it, so they can object. Null otherwise.
 */
export function photosInPersonNotice(
    photos: Pick<MyDataPhotos, 'items' | 'method'>,
    salonName: string,
): string | null {
    if (
        photos.items.length === 0 ||
        photos.method === null ||
        photos.method === ACCOUNT_METHOD
    ) {
        return null;
    }

    return `${salonName} registró que las autorizaste en persona.`;
}

/** Who took the care authorization back, and when; null while it was not. */
export function careWithdrawnNotice(
    care: Pick<MyDataCare, 'withdrawnOn' | 'withdrawnByThePerson'>,
    salonName: string,
): string | null {
    if (care.withdrawnOn === null) {
        return null;
    }

    const day = formatLongDay(care.withdrawnOn);

    return care.withdrawnByThePerson
        ? `Retiraste esta autorización el ${day}.`
        : `${salonName} registró el retiro de esta autorización el ${day}.`;
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
        ? `Se borrará de peluWeb la foto que ${salonName} guarda de tus servicios. No se puede recuperar. Si el salón tiene copias en otro lugar, pídele que las borre.`
        : `Se borrarán de peluWeb las ${count} fotos que ${salonName} guarda de tus servicios. No se pueden recuperar. Si el salón tiene copias en otro lugar, pídele que las borre.`;
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
