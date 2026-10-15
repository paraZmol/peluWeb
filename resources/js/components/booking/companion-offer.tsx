import { Users } from 'lucide-react';
import { useState } from 'react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import type { CompanionVoice } from '@/lib/companion';
import { pairPercent } from '@/lib/promo-offers';
import { shortWhen } from '@/lib/promo-when';
import { PAIR_FREE } from '@/lib/promotions';
import { CompanionDetails } from './companion-details';
import type { BookableService } from './types';

type Props = {
    wizard: BookingWizard;
    service: BookableService;
    /** The person booking reads "te acompaña"; the team, "viene con la persona". */
    voice?: CompanionVoice;
};

const BUTTON =
    'inline-flex h-11 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold transition-transform duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-ring active:scale-[0.97] motion-reduce:transition-none';

/**
 * Under the one service chosen, when a "Ven con alguien" takes it: one tap
 * books it for two, and then only the other person's name (and their
 * option) is asked. The server prices both.
 */
export function CompanionOffer({ wizard, service, voice = 'client' }: Props) {
    const { pair, companion } = wizard;
    const [wasOpenedByTap, setWasOpenedByTap] = useState(false);

    if (!pair && !companion) {
        return null;
    }

    // Without a promotion running (repeating a visit for two), both pay.
    const percent = pair ? pairPercent(pair) : null;
    const offer =
        percent === null
            ? 'Vienen dos, cada uno a precio normal'
            : percent === PAIR_FREE
              ? '2x1: vienen dos, el segundo gratis'
              : 'Vienen dos, el segundo a mitad de precio';
    const when = shortWhen(pair?.when ?? null);
    const toggle = () => {
        setWasOpenedByTap(companion === null);
        wizard.toggleCompanion();
    };

    return (
        <div className="mx-4 mb-4 flex flex-col gap-4 rounded-lg bg-card p-3 shadow-card">
            <div className="flex flex-wrap items-center gap-3">
                <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text"
                >
                    <Users className="size-5" />
                </span>
                <p className="min-w-40 flex-1 text-sm">
                    <strong className="block text-base">Ven con alguien</strong>
                    <span className="text-muted-foreground">
                        {offer}
                        {when && ` · solo ${when}`}
                    </span>
                </p>
                <button
                    type="button"
                    onClick={toggle}
                    className={`${BUTTON} ${companion ? 'skin-chip' : 'skin-primary'}`}
                >
                    {companion ? 'Quitar acompañante' : 'Añadir acompañante'}
                </button>
            </div>

            {companion && (
                <CompanionDetails
                    wizard={wizard}
                    service={service}
                    companion={companion}
                    voice={voice}
                    autoFocus={wasOpenedByTap}
                />
            )}
        </div>
    );
}
