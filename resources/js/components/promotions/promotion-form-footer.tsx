import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { PromotionForm } from '@/hooks/use-promotion-form';

export const NETWORK_PROBLEM =
    'No pudimos guardar: revisa tu conexión e inténtalo otra vez. Lo que escribiste sigue aquí.';

type Props = {
    form: PromotionForm;
    /** Not published yet: it can be kept as a draft or published now. */
    isDraft: boolean;
    /** Another way out, before the main buttons ("Duplicar"). */
    children?: ReactNode;
};

/** The actions of the form, always in reach under what scrolls. */
export function PromotionFormFooter({ form, isDraft, children }: Props) {
    return (
        <div className="flex flex-col gap-3 border-t bg-background px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
            {form.hasNetworkProblem && (
                <p role="alert" className="text-sm text-destructive-foreground">
                    {NETWORK_PROBLEM}
                </p>
            )}
            <div className="flex flex-wrap gap-2 sm:justify-end [&_button]:h-12 [&_button]:flex-1 sm:[&_button]:h-10 sm:[&_button]:flex-none">
                {children}
                {isDraft ? (
                    <>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={form.processing}
                        >
                            Guardar borrador
                        </Button>
                        <Button
                            type="button"
                            disabled={form.processing}
                            onClick={form.publish}
                        >
                            {form.processing && <Spinner />}
                            Publicar
                        </Button>
                    </>
                ) : (
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Guardar cambios
                    </Button>
                )}
            </div>
        </div>
    );
}
