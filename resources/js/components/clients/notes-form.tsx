import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useClientNotesForm } from '@/hooks/use-client-notes-form';
import { cn } from '@/lib/utils';
import { CareEraseDialog } from './care-erase-dialog';
import { CareNotesField } from './care-notes-field';
import { NotesField } from './notes-field';
import type { ClientFile } from './types';

type Props = {
    client: ClientFile;
    /** Health notes are only for the owner and whoever attends the person. */
    canSeeCare: boolean;
    careConsentText: string;
    maxLength: number;
    className?: string;
};

/** What the team keeps in mind to attend this person, saved in one go. */
export function NotesForm({
    client,
    canSeeCare,
    careConsentText,
    maxLength,
    className,
}: Props) {
    const form = useClientNotesForm(client, canSeeCare);
    const { data, setData, errors, processing } = form;

    return (
        <section
            aria-labelledby="client-notes-title"
            className={cn(
                'rounded-xl border bg-card p-5 shadow-card',
                className,
            )}
        >
            <form
                onSubmit={form.submit}
                noValidate
                className="flex flex-col gap-5"
            >
                <div>
                    <h2
                        id="client-notes-title"
                        className="text-lg font-semibold"
                    >
                        Notas del equipo
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Lo que conviene recordar para atender bien a{' '}
                        {client.name}.
                    </p>
                </div>

                <NotesField
                    id="client-notes"
                    label="Preferencias"
                    help="Cómo le gusta que le atiendan. No anotes datos de salud aquí."
                    value={data.notes}
                    onChange={(value) => setData('notes', value)}
                    maxLength={maxLength}
                    error={errors.notes}
                />
                <NotesField
                    id="client-formula-notes"
                    label="Fórmula y técnica"
                    help="Solo productos, tonos y tiempos. No anotes alergias aquí."
                    value={data.formula_notes}
                    onChange={(value) => setData('formula_notes', value)}
                    maxLength={maxLength}
                    error={errors.formula_notes}
                />
                {canSeeCare && (
                    <CareNotesField
                        client={client}
                        form={form}
                        consentText={careConsentText}
                        maxLength={maxLength}
                    />
                )}

                <Button
                    type="submit"
                    disabled={processing}
                    className="h-12 sm:h-10 sm:self-end"
                >
                    {processing && <Spinner />}
                    Guardar notas
                </Button>
            </form>

            <CareEraseDialog
                open={form.isConfirmingErase}
                clientName={client.name}
                processing={processing}
                onConfirm={form.confirmErase}
                onClose={form.cancelErase}
            />
        </section>
    );
}
