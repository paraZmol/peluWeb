import { Link } from '@inertiajs/react';
import { ArrowRight, MessageCircle, Navigation } from 'lucide-react';
import { CHIP_ICON, PRIMARY_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';

type Props = {
    bookingUrl: string | null;
    /** Online booking is paused: WhatsApp asks for a visit, it does not book one. */
    isPaused?: boolean;
    whatsappUrl: string | null;
    directionsUrl: string | null;
};

const MAIN = cn(PRIMARY_ACTION, 'flex-1');

/**
 * The dock: fixed in the thumb zone on phones and tablets; on desktop the
 * booking card in the cover does its job. Without online booking the main
 * action is WhatsApp, and without that, the way to get there.
 */
export function StickyActions({
    bookingUrl,
    isPaused = false,
    whatsappUrl,
    directionsUrl,
}: Props) {
    if (!bookingUrl && !whatsappUrl && !directionsUrl) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-30 pb-[env(safe-area-inset-bottom)] skin-dock lg:hidden">
            <div className="mx-auto flex w-full max-w-3xl items-center gap-3 px-4 py-3">
                {bookingUrl && whatsappUrl && (
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Escribir por WhatsApp"
                        className={CHIP_ICON}
                    >
                        <MessageCircle aria-hidden />
                    </a>
                )}
                {bookingUrl && (
                    <Link href={bookingUrl} className={MAIN}>
                        <span className="truncate">Reservar cita</span>
                        <ArrowRight aria-hidden />
                    </Link>
                )}
                {!bookingUrl && whatsappUrl && (
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={MAIN}
                    >
                        <MessageCircle aria-hidden />
                        <span className="truncate">
                            {isPaused
                                ? 'Pedir cita por WhatsApp'
                                : 'Reservar por WhatsApp'}
                        </span>
                    </a>
                )}
                {!bookingUrl && !whatsappUrl && directionsUrl && (
                    <a
                        href={directionsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={MAIN}
                    >
                        <Navigation aria-hidden />
                        <span className="truncate">Cómo llegar</span>
                    </a>
                )}
            </div>
        </div>
    );
}
