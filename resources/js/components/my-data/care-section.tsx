import { ShieldCheck } from 'lucide-react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { formatLongDay } from '@/lib/clients';
import { careStatus } from '@/lib/my-data';
import { withdraw } from '@/routes/my-data';
import { AuthorizeDialog } from './authorize-dialog';
import { Fold } from './fold';
import type { MyDataFile } from './types';

/**
 * "Alergias y cuidado": whether this salon may keep the person's allergies,
 * what it wrote, and the switch to give the authorization or take it back.
 */
export function CareSection({ file }: { file: MyDataFile }) {
    const { care, salon, clientId } = file;
    const titleId = `my-data-care-${clientId}`;
    const status = careStatus(care);

    return (
        <section
            aria-labelledby={titleId}
            className="flex min-w-0 flex-col gap-3"
        >
            <h3 id={titleId} className="text-base font-semibold">
                Alergias y cuidado
            </h3>

            {status === null ? (
                <>
                    {care.withdrawnOn !== null && (
                        <p className="text-sm">
                            Retiraste esta autorización el{' '}
                            {formatLongDay(care.withdrawnOn)}.
                        </p>
                    )}
                    {salon.isClosed && (
                        // Nothing new is authorized to a salon that left.
                        <p className="text-sm text-muted-foreground">
                            {salon.name} no guarda alergias tuyas.
                        </p>
                    )}
                    {!salon.isClosed && (
                        <p className="text-sm text-muted-foreground">
                            {salon.name} solo puede anotar tus alergias si lo
                            autorizas. Es voluntario: si no, te atienden igual.
                        </p>
                    )}
                    {!salon.isClosed && (
                        <AuthorizeDialog
                            clientId={clientId}
                            kind="care"
                            triggerLabel="Autorizar"
                            title={`Autorizar a ${salon.name} a guardar mis alergias`}
                            text={care.text}
                            acceptLabel={`Soy mayor de 18 años, leí lo anterior y autorizo a ${salon.name} a guardar estos datos de salud.`}
                        />
                    )}
                </>
            ) : (
                <>
                    <p className="flex items-start gap-2 text-sm">
                        <ShieldCheck
                            className="mt-0.5 size-4 shrink-0 text-success"
                            aria-hidden
                        />
                        <span>{status}</span>
                    </p>
                    <Fold summary="Ver lo anotado">
                        {care.notes === null ? (
                            <p className="text-muted-foreground">
                                El salón todavía no anotó nada.
                            </p>
                        ) : (
                            <p className="leading-relaxed break-words whitespace-pre-wrap">
                                {care.notes}
                            </p>
                        )}
                    </Fold>
                    <ConfirmRemoveDialog
                        triggerLabel="Retirar autorización"
                        trigger={
                            <Button
                                variant="outline"
                                className="h-11 text-destructive-foreground sm:h-9 sm:self-start"
                            >
                                Retirar autorización
                            </Button>
                        }
                        title="¿Retirar la autorización?"
                        description={`${salon.name} dejará de guardar tus alergias y se borrará lo anotado. Avísales en persona antes de tu próximo servicio.`}
                        confirmLabel="Retirar y borrar"
                        form={withdraw.form([clientId, 'care'])}
                    />
                </>
            )}
        </section>
    );
}
