import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    isDirty: boolean;
    processing: boolean;
    /** The last save never reached the server. */
    hasNetworkProblem: boolean;
};

/**
 * The form's one save button. On a phone, with changes not saved yet, it
 * docks at the bottom (thumb zone) so a long file cannot be left without
 * saving; the page reserves the room through `data-unsaved`.
 */
export function NotesSaveBar({
    isDirty,
    processing,
    hasNetworkProblem,
}: Props) {
    return (
        // Keeps the button's place in the form while it is docked.
        <div className="max-sm:min-h-12">
            <div
                data-unsaved={isDirty || undefined}
                className={cn(
                    'flex flex-col gap-2',
                    isDirty &&
                        'max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:z-30 max-sm:border-t max-sm:bg-card max-sm:px-4 max-sm:pt-3 max-sm:pb-[max(0.75rem,env(safe-area-inset-bottom))] max-sm:shadow-float',
                )}
            >
                {hasNetworkProblem && (
                    <p
                        role="alert"
                        className="text-sm text-destructive-foreground"
                    >
                        No se pudo guardar. Revisa tu conexión y vuelve a
                        intentarlo; lo que escribiste sigue aquí.
                    </p>
                )}
                <div
                    className={cn(
                        'flex items-center justify-between gap-3',
                        !isDirty && 'max-sm:flex-col max-sm:items-stretch',
                    )}
                >
                    {isDirty && (
                        <p role="status" className="text-sm font-medium">
                            Hay cambios sin guardar
                        </p>
                    )}
                    <Button
                        type="submit"
                        disabled={processing}
                        className="h-12 sm:ml-auto sm:h-10"
                    >
                        {processing && <Spinner />}
                        Guardar notas
                    </Button>
                </div>
            </div>
        </div>
    );
}
