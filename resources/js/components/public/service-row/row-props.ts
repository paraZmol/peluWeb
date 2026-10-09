import type { PublicService } from '../types';

export type ServiceRowProps = {
    service: PublicService;
    /** Online booking wizard; falls back to WhatsApp when null. */
    bookingUrl: string | null;
    /** Label of the group it belongs to (picks its icon). */
    group: string;
};
