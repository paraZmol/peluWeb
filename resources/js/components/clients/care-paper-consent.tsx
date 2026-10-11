import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import type { ClientNotesForm } from '@/hooks/use-client-notes-form';
import { formatLongDay } from '@/lib/clients';
import { cn } from '@/lib/utils';
import { CareConsentNotice, PrintConsentButton } from './care-consent';
import type { ClientFile } from './types';
import { WarningNote } from './warning-note';

type Props = { client: ClientFile; form: ClientNotesForm; consentText: string };

/**
 * The authorization on paper: the sheet to print, and the box the team
 * ticks once it is signed. Unticking a recorded one withdraws it.
 */
export function CarePaperConsent({ client, form, consentText }: Props) {
    const { data, setData, errors } = form;
    const consent = client.careConsent;
    const isWithdrawing = consent !== null && !data.care_consent;

    return (
        <>
            {consent === null ? (
                <CareConsentNotice text={consentText} />
            ) : (
                <div className="flex flex-col gap-2">
                    <p className="flex items-start gap-2 text-sm">
                        <ShieldCheck
                            className="mt-0.5 size-4 shrink-0 text-success"
                            aria-hidden
                        />
                        <span>
                            Autorización registrada el{' '}
                            {formatLongDay(consent.date)}
                            {consent.by && ` por ${consent.by}`}
                        </span>
                    </p>
                    <PrintConsentButton label="Reimprimir la hoja" />
                </div>
            )}

            <div className="grid gap-1">
                <label
                    htmlFor="client-care-consent"
                    className="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-sm"
                >
                    <Checkbox
                        id="client-care-consent"
                        checked={data.care_consent}
                        onCheckedChange={(checked) =>
                            setData('care_consent', checked === true)
                        }
                        aria-invalid={Boolean(errors.care_consent)}
                        aria-describedby={cn(
                            'client-care-consent-help',
                            errors.care_consent && 'client-care-consent-error',
                        )}
                        className="mt-0.5 size-5"
                    />
                    <span>
                        Declaro que {client.name} firmó la autorización impresa
                        y que el salón la conserva.
                    </span>
                </label>
                <p
                    id="client-care-consent-help"
                    className="pl-8 text-sm text-muted-foreground"
                >
                    Marca esto solo cuando ya tengas la hoja firmada.
                </p>
                <InputError
                    id="client-care-consent-error"
                    message={errors.care_consent}
                />
            </div>

            {isWithdrawing && (
                // It shows up on unticking: said at once, not on the next read.
                <WarningNote role="alert">
                    Al guardar se retira la autorización y se borra lo anotado
                    en «Alergias y cuidado». No se puede deshacer.
                </WarningNote>
            )}
        </>
    );
}
