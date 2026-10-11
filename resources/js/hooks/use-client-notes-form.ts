import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type { ClientFile, ClientNotesFields } from '@/components/clients/types';
import {
    erasesSavedCare,
    initialNotesFields,
    notesPayload,
} from '@/lib/clients';
import { update } from '@/routes/clients/notes';

/**
 * What the team is writing on a client's file and how it is sent. Saving
 * without the authorization erases the care notes on the file for good,
 * so that save waits for a confirmation.
 */
export function useClientNotesForm(client: ClientFile, canSeeCare: boolean) {
    const form = useForm<ClientNotesFields>(initialNotesFields(client));
    // "save": the box was unticked. "withdraw": the person asked the salon to.
    const [eraseStep, setEraseStep] = useState<'save' | 'withdraw' | null>(
        null,
    );
    // The request never arrived: what was typed is still here to retry.
    const [hasNetworkProblem, setHasNetworkProblem] = useState(false);
    const { data, setData } = form;

    const send = (hasConsent = data.care_consent) => {
        form.transform((fields) =>
            notesPayload({ ...fields, care_consent: hasConsent }, canSeeCare),
        );
        form.submit(update(client.id), {
            preserveScroll: true,
            onStart: () => setHasNetworkProblem(false),
            onNetworkError: () => setHasNetworkProblem(true),
            onSuccess: () => {
                // Nothing is kept without the authorization: neither here.
                if (!hasConsent) {
                    setData((fields) => ({
                        ...fields,
                        care_consent: false,
                        care_notes: '',
                    }));
                }
            },
            onFinish: () => setEraseStep(null),
        });
    };

    return {
        data,
        setData,
        errors: form.errors,
        processing: form.processing,
        isDirty: form.isDirty,
        hasNetworkProblem,
        isConfirmingErase: eraseStep !== null,
        confirmErase: () =>
            send(eraseStep === 'withdraw' ? false : data.care_consent),
        cancelErase: () => setEraseStep(null),
        /** An authorization given from the account, taken back at the salon. */
        withdrawCare: () => setEraseStep('withdraw'),
        submit: (event: FormEvent) => {
            event.preventDefault();

            if (erasesSavedCare(client, data, canSeeCare)) {
                setEraseStep('save');

                return;
            }

            send();
        },
    };
}

export type ClientNotesForm = ReturnType<typeof useClientNotesForm>;
