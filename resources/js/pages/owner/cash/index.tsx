import { Head } from '@inertiajs/react';
import { CashHistory } from '@/components/cash/cash-history';
import { ClosedCashCard } from '@/components/cash/closed-cash-card';
import { MethodList } from '@/components/cash/method-list';
import { OpenCashCard } from '@/components/cash/open-cash-card';
import type { CashSession } from '@/components/cash/types';
import { index } from '@/routes/cash';

type Props = {
    /** The open session with what moved; null while the till is closed. */
    session: CashSession | null;
    /** The last closed sessions, newest first. */
    history: CashSession[];
};

export default function Cash({ session, history }: Props) {
    return (
        <>
            <Head title="Caja" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Caja</h1>
                    <p className="text-sm text-muted-foreground">
                        Abre la caja al empezar, ciérrala al terminar y mira si
                        el efectivo cuadra.
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <div className="lg:sticky lg:top-6">
                        {session ? (
                            <OpenCashCard session={session} />
                        ) : (
                            <ClosedCashCard
                                lastCountedCash={
                                    history[0]?.countedCash ?? null
                                }
                            />
                        )}
                    </div>

                    <div className="flex min-w-0 flex-col gap-6">
                        {session && <MethodList methods={session.methods} />}
                        <CashHistory history={history} />
                    </div>
                </div>
            </div>
        </>
    );
}

Cash.layout = {
    breadcrumbs: [{ title: 'Caja', href: index() }],
};
