import type { Option } from '@/types';

/** Mirrors config('legal.provider'): empty strings until it is filled in. */
export type ComplaintProvider = {
    name: string;
    ruc: string;
    address: string;
};

/** A complaint kind with its one-line help (App\Enums\ComplaintKind). */
export type ComplaintKindOption = Option & { help: string };

/** What Public\ComplaintController::create sends. */
export type ComplaintFormOptions = {
    kinds: ComplaintKindOption[];
    documents: Option[];
    itemTypes: Option[];
};

/** What the receipt page shows (Public\ComplaintController::show). */
export type ComplaintReceipt = {
    code: string;
    /** "reclamo" or "queja", already lower case. */
    kind: string;
    email: string;
    dueOn: string;
    responseDays: number;
};
