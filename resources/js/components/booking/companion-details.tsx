import { UserCheck } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import type { Companion, CompanionVoice } from '@/lib/companion';
import {
    COMPANION_COPY,
    MAX_COMPANION_NAME,
    SAME_STAFF_NOTE,
} from '@/lib/companion';
import type { BookableService } from './types';
import { VariantChips } from './variant-chips';

type Props = {
    wizard: BookingWizard;
    service: BookableService;
    companion: Companion;
    voice: CompanionVoice;
    /** Opened by a tap (not by the page loading): the name gets the focus. */
    autoFocus: boolean;
};

/** Who comes along: only their name and, when there is one, their option. */
export function CompanionDetails({
    wizard,
    service,
    companion,
    voice,
    autoFocus,
}: Props) {
    const copy = COMPANION_COPY[voice];
    const [isTouched, setIsTouched] = useState(false);
    const isMissing = isTouched && companion.name.trim() === '';

    return (
        <div className="grid gap-2">
            <Label htmlFor="companion-name">{copy.question}</Label>
            <Input
                id="companion-name"
                required
                autoFocus={autoFocus}
                autoComplete="off"
                maxLength={MAX_COMPANION_NAME}
                value={companion.name}
                onChange={(event) =>
                    wizard.setCompanion({ name: event.target.value })
                }
                onBlur={() => setIsTouched(true)}
                aria-invalid={isMissing || undefined}
                aria-describedby={
                    isMissing
                        ? 'companion-name-error companion-name-hint'
                        : 'companion-name-hint'
                }
                className="h-11"
            />
            {isMissing && (
                <p
                    id="companion-name-error"
                    role="alert"
                    className="text-sm font-medium text-destructive-foreground"
                >
                    {copy.missing}.
                </p>
            )}
            <p
                id="companion-name-hint"
                className="text-sm text-muted-foreground"
            >
                {copy.hint}
            </p>
            {service.variants.length > 0 && (
                <>
                    <p className="mt-1 text-sm font-medium">Su opción</p>
                    <VariantChips
                        label={`Opción de ${service.name} para ${copy.variantFor}`}
                        variants={service.variants}
                        value={companion.variantId}
                        onChange={(variantId) =>
                            wizard.setCompanion({ variantId })
                        }
                    />
                </>
            )}
            <p className="mt-1 flex items-start gap-2 text-sm text-muted-foreground">
                <UserCheck className="mt-0.5 size-4 shrink-0" aria-hidden />
                {SAME_STAFF_NOTE}
            </p>
        </div>
    );
}
