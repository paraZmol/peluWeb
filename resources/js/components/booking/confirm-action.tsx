import { Link } from '@inertiajs/react';
import { Spinner } from '@/components/ui/spinner';
import { PRIMARY_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { resume } from '@/routes/salons/booking';
import { notice } from '@/routes/verification';
import { STICKY_ACTION } from './sticky-action';
import type { Viewer } from './types';

type Props = {
    viewer: Viewer;
    salonSlug: string;
    sending: boolean;
    onConfirm: () => void;
};

/**
 * The last step's one action: confirm, or first sign in (guests) or verify
 * the e-mail (new accounts). What was chosen is kept meanwhile.
 */
export function ConfirmAction({
    viewer,
    salonSlug,
    sending,
    onConfirm,
}: Props) {
    if (viewer.state === 'guest') {
        return (
            <div className="flex flex-col gap-2">
                {/* A full page load: the server sends guests to sign in and back. */}
                <a href={resume.url(salonSlug)} className={PRIMARY_ACTION}>
                    Entrar para confirmar
                </a>
                <p className="text-center text-sm text-muted-foreground">
                    Si no tienes cuenta, podrás crearla ahí mismo. Guardamos lo
                    que elegiste.
                </p>
            </div>
        );
    }

    if (viewer.state === 'unverified') {
        return (
            <Link href={notice()} className={PRIMARY_ACTION}>
                Verifica tu correo para confirmar
            </Link>
        );
    }

    return (
        <div className={STICKY_ACTION}>
            <button
                type="button"
                className={cn(PRIMARY_ACTION, 'w-full')}
                disabled={sending}
                onClick={onConfirm}
            >
                {sending && <Spinner />}
                Confirmar reserva
            </button>
        </div>
    );
}
