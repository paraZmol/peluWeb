import { ShieldCheck, TriangleAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import type { ClientNotesForm } from '@/hooks/use-client-notes-form';
import { formatLongDay } from '@/lib/clients';
import { cn } from '@/lib/utils';
import { CareConsentNotice, PrintConsentButton } from './care-consent';
import { NotesField } from './notes-field';
import type { ClientFile } from './types';

type Props = {
    client: ClientFile;
    form: ClientNotesForm;
    consentText: string;
    maxLength: number;
};

/**
 * Allergies and reactions are health data: the field only opens once the
 * person's signed authorization is declared, and closes if it is withdrawn.
 */
export function CareNotesField({
    client,
    form,
    consentText,
    maxLength,
}: Props) {
    const { data, setData, errors } = form;
    const consent = client.careConsent;
    const isWithdrawing = consent !== null && !data.care_consent;

    return (
        <div className="flex flex-col gap-4 border-t pt-5">
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
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-lg bg-warning/10 p-3 text-sm"
                >
                    <TriangleAlert
                        className="mt-0.5 size-4 shrink-0 text-warning"
                        aria-hidden
                    />
                    <span>
                        Al guardar se retira la autorización y se borra lo
                        anotado en «Alergias y cuidado». No se puede deshacer.
                    </span>
                </p>
            )}

            <NotesField
                id="client-care-notes"
                label="Alergias y cuidado"
                help="Alergias, sensibilidades y reacciones a productos. Solo lo ve quien dirige el salón y quien atiende a esta persona."
                value={data.care_notes}
                onChange={(value) => setData('care_notes', value)}
                maxLength={maxLength}
                error={errors.care_notes}
                lockedReason={
                    data.care_consent
                        ? undefined
                        : 'Se habilita cuando marques que la hoja está firmada.'
                }
                placeholder="Ej. Se irrita con el tinte negro; hacer prueba de parche antes"
            />
        </div>
    );
}
