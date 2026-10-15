import type { ServiceOffer } from '@/lib/promo-offers';
import type { PublicService } from '../types';

export type ServiceRowProps = {
    service: PublicService;
    /** Online booking wizard; falls back to WhatsApp when null. */
    bookingUrl: string | null;
    /** Label of the group it belongs to (picks its icon). */
    group: string;
    /** Its price with the salon's best promotion, when one lowers it. */
    offer?: ServiceOffer;
};
