import { Link } from '@inertiajs/react';
import {
    CalendarCheck,
    Instagram,
    MessageCircle,
    Navigation,
    Share2,
} from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

type Props = {
    salonName: string;
    bookingUrl: string | null;
    whatsappUrl: string | null;
    instagramUrl: string | null;
    directionsUrl: string | null;
};

async function share(salonName: string): Promise<void> {
    const url = window.location.href;

    if (typeof navigator.share === 'function') {
        try {
            await navigator.share({ title: salonName, url });
        } catch {
            // Closing the share sheet rejects too; there is nothing to report.
        }

        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Enlace copiado');
    } catch {
        toast.error('No pudimos copiar el enlace');
    }
}

/** Fixed to the bottom (thumb zone) on mobile, inline on desktop. */
export function StickyActions({
    salonName,
    bookingUrl,
    whatsappUrl,
    instagramUrl,
    directionsUrl,
}: Props) {
    return (
        <div className="fixed inset-x-0 bottom-0 z-30 border-t bg-background pb-[env(safe-area-inset-bottom)] backdrop-blur md:static md:border-0 md:bg-transparent md:pb-0 md:backdrop-blur-none">
            <div className="mx-auto flex w-full max-w-3xl items-center gap-2 px-4 py-3 md:py-0">
                {bookingUrl && (
                    <Button
                        asChild
                        size="lg"
                        className="h-12 min-w-0 flex-1 rounded-full md:h-11 md:flex-none"
                    >
                        <Link href={bookingUrl}>
                            <CalendarCheck aria-hidden />
                            <span className="truncate">Reservar</span>
                        </Link>
                    </Button>
                )}
                {bookingUrl && whatsappUrl && (
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-12 shrink-0 rounded-full md:size-11"
                    >
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Escribir por WhatsApp"
                        >
                            <MessageCircle aria-hidden />
                        </a>
                    </Button>
                )}
                {!bookingUrl && whatsappUrl && (
                    <Button
                        asChild
                        size="lg"
                        className="h-12 min-w-0 flex-1 rounded-full md:h-11 md:flex-none"
                    >
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <MessageCircle aria-hidden />
                            <span className="truncate">
                                Reservar por WhatsApp
                            </span>
                        </a>
                    </Button>
                )}
                {/* Without WhatsApp the page still needs a clear next step. */}
                {!bookingUrl && !whatsappUrl && directionsUrl && (
                    <Button
                        asChild
                        size="lg"
                        className="h-12 min-w-0 flex-1 rounded-full md:h-11 md:flex-none"
                    >
                        <a
                            href={directionsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Navigation aria-hidden />
                            <span className="truncate">Cómo llegar</span>
                        </a>
                    </Button>
                )}
                {instagramUrl && (
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-12 shrink-0 rounded-full md:size-11"
                    >
                        <a
                            href={instagramUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Ver en Instagram"
                        >
                            <Instagram aria-hidden />
                        </a>
                    </Button>
                )}
                <Button
                    variant="outline"
                    size="icon"
                    className="size-12 shrink-0 rounded-full md:size-11"
                    aria-label="Compartir"
                    onClick={() => void share(salonName)}
                >
                    <Share2 aria-hidden />
                </Button>
            </div>
        </div>
    );
}
