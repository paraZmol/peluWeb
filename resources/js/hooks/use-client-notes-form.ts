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
    const [isConfirmingErase, setIsConfirmingErase] = useState(false);
    const { data, setData } = form;

    const send = () => {
        form.transform((fields) => notesPayload(fields, canSeeCare));
        form.submit(update(client.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Nothing is kept without the authorization: neither here.
                if (!data.care_consent) {
                    setData('care_notes', '');
                }
            },
            onFinish: () => setIsConfirmingErase(false),
        });
    };

    return {
        data,
        setData,
        errors: form.errors,
        processing: form.processing,
        isDirty: form.isDirty,
        isConfirmingErase,
        confirmErase: send,
        cancelErase: () => setIsConfirmingErase(false),
        submit: (event: FormEvent) => {
            event.preventDefault();

            if (erasesSavedCare(client, data, canSeeCare)) {
                setIsConfirmingErase(true);

                return;
            }

            send();
        },
    };
}

export type ClientNotesForm = ReturnType<typeof useClientNotesForm>;
