import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { PublicService } from '../types';

type Props = {
    service: PublicService;
    /** Online booking wizard; falls back to the service's WhatsApp message. */
    bookingUrl: string | null;
    className: string;
    children: ReactNode;
};

/** "Reservar" for one service: straight into the wizard with it chosen. */
export function ServiceBookLink({
    service,
    bookingUrl,
    className,
    children,
}: Props) {
    if (bookingUrl) {
        return (
            <Link
                href={`${bookingUrl}?servicio=${service.id}`}
                aria-label={`Reservar ${service.name}`}
                className={className}
            >
                {children}
            </Link>
        );
    }

    if (!service.whatsappUrl) {
        return null;
    }

    return (
        <a
            href={service.whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={`Reservar ${service.name} por WhatsApp`}
            className={className}
        >
            {children}
        </a>
    );
}
