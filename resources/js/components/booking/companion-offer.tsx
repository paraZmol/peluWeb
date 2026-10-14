import { Users } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { MAX_COMPANION_NAME } from '@/lib/companion';
import { pairPercent } from '@/lib/promo-offers';
import { PAIR_FREE } from '@/lib/promotions';
import type { BookableService } from './types';
import { VariantChips } from './variant-chips';

type Props = { wizard: BookingWizard; service: BookableService };

const BUTTON =
    'inline-flex h-11 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold transition-transform duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-ring active:scale-[0.97] motion-reduce:transition-none';

/**
 * Under the one service chosen, when a "Ven con alguien" takes it: one tap
 * books it for two, and then only the other person's name (and their
 * option) is asked. The server prices both.
 */
export function CompanionOffer({ wizard, service }: Props) {
    const { pair, companion } = wizard;

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
                        {pair?.when && ` · solo ${pair.when}`}
                    </span>
                </p>
                <button
                    type="button"
                    aria-pressed={companion !== null}
                    onClick={wizard.toggleCompanion}
                    className={`${BUTTON} ${companion ? 'skin-chip' : 'skin-primary'}`}
                >
                    {companion ? 'Quitar' : 'Ven con alguien'}
                </button>
            </div>

            {companion && (
                <div className="grid gap-2">
                    <Label htmlFor="companion-name">
                        ¿Cómo se llama quien te acompaña?
                    </Label>
                    <Input
                        id="companion-name"
                        required
                        autoComplete="off"
                        maxLength={MAX_COMPANION_NAME}
                        value={companion.name}
                        onChange={(event) =>
                            wizard.setCompanion({ name: event.target.value })
                        }
                        aria-describedby="companion-name-hint"
                        className="h-11"
                    />
                    <p
                        id="companion-name-hint"
                        className="text-sm text-muted-foreground"
                    >
                        Nombre de quien te acompaña: solo el nombre o como
                        prefiera que lo llamen. Lo verán el salón y tú,
                        únicamente para esta cita. Avísale que lo compartes.
                    </p>
                    {service.variants.length > 0 && (
                        <p className="mt-1 text-sm font-medium">Su opción</p>
                    )}
                    {service.variants.length > 0 && (
                        <VariantChips
                            label={`Opción de ${service.name} para quien te acompaña`}
                            variants={service.variants}
                            value={companion.variantId}
                            onChange={(variantId) =>
                                wizard.setCompanion({ variantId })
                            }
                        />
                    )}
                    <p className="text-sm font-semibold text-brand-text">
                        Les atiende la misma persona, uno después del otro.
                    </p>
                </div>
            )}
        </div>
    );
}
