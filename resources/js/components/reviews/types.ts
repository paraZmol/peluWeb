export type ReviewMode = 'hidden' | 'private' | 'public';

export type ReviewModeOption = {
    value: ReviewMode;
    label: string;
    hint: string;
};

/** A review as anyone may read it: signed with the first name only. */
export type PublicReview = {
    id: number;
    rating: number;
    comment: string | null;
    author: string;
    /** "Y-m-d" on the salon's clock. */
    date: string;
    reply: string | null;
    repliedOn: string | null;
};

/** What the salon's public page shows; the salon decides whether it does. */
export type PublicReviews = {
    /** One decimal; null while there are none. */
    average: number | null;
    count: number;
    items: PublicReview[];
};

/** A review as the salon's team reads it, with what only they see. */
export type OwnerReview = PublicReview & {
    staffName: string;
    /** Reported and still waiting for the platform to look at it. */
    isReported: boolean;
    /** The platform took it down. */
    isHidden: boolean;
    /** Reported, looked at and left up. */
    wasKept: boolean;
    /** False when written while reviews were private: it never goes public. */
    isPublishable: boolean;
};

export type OwnerReviews = {
    average: number | null;
    count: number;
    items: OwnerReview[];
};

export type ReviewLimits = { reply: number; report: number };

/** The review of one booking, as its author sees it. */
export type MyReview = {
    rating: number;
    comment: string | null;
    reply: string | null;
};
