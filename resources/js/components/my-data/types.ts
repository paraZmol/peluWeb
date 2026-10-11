/** What an authorization covers: the health notes or the pictures. */
export type ConsentKind = 'care' | 'photos';

export type MyDataCare = {
    isAuthorized: boolean;
    /** "2026-10-07" on the salon's calendar. */
    since: string | null;
    /** "account": given from this account; anything else, a sheet signed at the salon. */
    method: string | null;
    notes: string | null;
    withdrawnOn: string | null;
    /** false: the salon recorded the withdrawal. */
    withdrawnByThePerson: boolean;
    /** What the person agrees to, word for word. */
    text: string;
    /** The sentence beside the box they tick: part of what is recorded. */
    acceptLabel: string;
};

export type MyDataPhoto = {
    id: number;
    /** "Antes" or "Después". */
    kindLabel: string;
    date: string;
    /** Private: served only to the person and to who attends them. */
    url: string;
};

export type MyDataPhotos = {
    items: MyDataPhoto[];
    /** In force, even before the salon keeps a first picture. */
    isAuthorized: boolean;
    since: string | null;
    /** "account": given from this account; anything else, in person. Null without one. */
    method: string | null;
    /** The person took them back: the salon cannot add more until they say so. */
    isBlocked: boolean;
    withdrawnOn: string | null;
    text: string;
    acceptLabel: string;
};

/** What one salon keeps of the signed-in person under their authorization. */
export type MyDataFile = {
    clientId: number;
    salon: {
        name: string;
        slug: string;
        /** It closed its account: nothing new to authorize, only to take back. */
        isClosed: boolean;
    };
    care: MyDataCare;
    photos: MyDataPhotos;
};
