import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    hasPhoto: boolean;
    hasConsent: boolean;
    processing: boolean;
    /** 0-100 while the file travels; null before and after. */
    percentage: number | null;
    /** The last upload never reached the server. */
    hasNetworkProblem: boolean;
};

/** What is still missing before the picture can be sent, if anything. */
function missing(hasPhoto: boolean, hasConsent: boolean): string | null {
    if (!hasPhoto) {
        return 'Falta elegir la foto';
    }

    return hasConsent ? null : 'Falta marcar la autorización';
}

/** The upload's button with what it waits for, its progress and its failure. */
export function PhotoUploadSubmit({
    hasPhoto,
    hasConsent,
    processing,
    percentage,
    hasNetworkProblem,
}: Props) {
    const hint = missing(hasPhoto, hasConsent);
    const percent = Math.round(percentage ?? 0);

    return (
        <div className="flex flex-col gap-2">
            {hasNetworkProblem && (
                <p role="alert" className="text-sm text-destructive-foreground">
                    No se pudo subir. Revisa tu conexión y vuelve a intentarlo;
                    la foto sigue elegida.
                </p>
            )}
            {processing && (
                <div
                    role="progressbar"
                    aria-label="Subiendo la foto"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={percent}
                    className="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className="h-full origin-left rounded-full bg-primary transition-transform"
                        style={{ transform: `scaleX(${percent / 100})` }}
                    />
                </div>
            )}
            <Button
                type="submit"
                disabled={processing || hint !== null}
                aria-describedby={hint ? 'client-photo-missing' : undefined}
                // Switched off it must still read as a button, not as a gap.
                className="h-12 disabled:bg-muted disabled:text-muted-foreground disabled:opacity-100 sm:h-10"
            >
                {processing && <Spinner />}
                {processing ? `Subiendo… ${percent} %` : 'Guardar foto'}
            </Button>
            {hint && (
                <p
                    id="client-photo-missing"
                    className="text-center text-sm text-muted-foreground"
                >
                    {hint}
                </p>
            )}
        </div>
    );
}
