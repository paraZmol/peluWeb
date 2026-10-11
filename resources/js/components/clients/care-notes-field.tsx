import { ShieldCheck } from 'lucide-react';
import type { ClientNotesForm } from '@/hooks/use-client-notes-form';
import {
    careWithdrawalNotice,
    formatLongDay,
    isCareAuthorizedFromAccount,
    isCareLockedByThePerson,
} from '@/lib/clients';
import { CarePaperConsent } from './care-paper-consent';
import { NotesField } from './notes-field';
import type { ClientFile } from './types';
import { WarningNote } from './warning-note';

type Props = {
    client: ClientFile;
    form: ClientNotesForm;
    consentText: string;
    maxLength: number;
};

/**
 * Allergies and reactions are health data: the field only opens with the
 * person's authorization (a signed sheet, or given from their own account)
 * and closes if it is withdrawn.
 */
export function CareNotesField({
    client,
    form,
    consentText,
    maxLength,
}: Props) {
    const { data, setData, errors } = form;
    const consent = client.careConsent;
    const withdrawal = careWithdrawalNotice(client);
    const isLockedByThePerson = isCareLockedByThePerson(client);
    const isFromAccount =
        consent !== null && isCareAuthorizedFromAccount(client);

    return (
        <div className="flex flex-col gap-4 border-t pt-5">
            {withdrawal !== null && <WarningNote>{withdrawal}</WarningNote>}

            {consent !== null &&
                isFromAccount && (
                    // Theirs to give and to take back: nothing to tick here.
                    <div className="flex flex-col gap-1 text-sm">
                        <p className="flex items-start gap-2">
                            <ShieldCheck
                                className="mt-0.5 size-4 shrink-0 text-success"
                                aria-hidden
                            />
                            <span>
                                {client.name} lo autorizó desde su cuenta el{' '}
                                {formatLongDay(consent.date)}.
                            </span>
                        </p>
                        <p className="pl-6 text-muted-foreground">
                            Solo {client.name} puede retirarla, desde su cuenta
                            (Mis datos).
                        </p>
                    </div>
                )}
            {/* A sheet opens the field unless they took it back themselves. */}
            {!isFromAccount && !isLockedByThePerson && (
                <CarePaperConsent
                    client={client}
                    form={form}
                    consentText={consentText}
                />
            )}

            <NotesField
                id="client-care-notes"
                label="Alergias y cuidado"
                help="Alergias, sensibilidades y reacciones a productos. Lo ven quien dirige el salón y quien atiende a esta persona. La persona también lo ve desde su cuenta."
                value={data.care_notes}
                onChange={(value) => setData('care_notes', value)}
                maxLength={maxLength}
                error={errors.care_notes}
                lockedReason={
                    isLockedByThePerson
                        ? `Solo ${client.name} puede volver a autorizarlo, desde su cuenta (Mis datos).`
                        : data.care_consent
                          ? undefined
                          : 'Se habilita cuando marques que la hoja está firmada.'
                }
                placeholder="Ej. Se irrita con el tinte negro; hacer prueba de parche antes"
            />
        </div>
    );
}
