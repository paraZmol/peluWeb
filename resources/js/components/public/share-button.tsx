import { Share2 } from 'lucide-react';
import { toast } from 'sonner';

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

type Props = { salonName: string; className: string };

/** The phone's share sheet, or the link copied where there is none. */
export function ShareButton({ salonName, className }: Props) {
    return (
        <button
            type="button"
            className={className}
            aria-label="Compartir"
            onClick={() => void share(salonName)}
        >
            <Share2 aria-hidden />
        </button>
    );
}
