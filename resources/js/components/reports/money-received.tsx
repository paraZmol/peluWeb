import { formatBalance } from '@/lib/cash';
import { ReportSection, SectionEmpty } from './report-section';
import type { MethodAmount } from './types';

/** The money that changed hands, by how it was paid. */
export function MoneyReceived({ methods }: { methods: MethodAmount[] }) {
    const totalCents = methods.reduce((sum, method) => sum + method.cents, 0);

    return (
        <ReportSection
            title="Dinero recibido por método"
            description="Señas y cobros, menos las señas devueltas, el día en que se pagaron. Por eso puede no coincidir con los ingresos."
        >
            {methods.length === 0 ? (
                <SectionEmpty>
                    No se recibió ningún pago en este período.
                </SectionEmpty>
            ) : (
                <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-2 text-sm">
                    {methods.map((method) => (
                        <div key={method.value} className="contents">
                            <dt className="min-w-0 break-words">
                                {method.label}
                            </dt>
                            <dd className="text-right font-medium tabular-nums">
                                {formatBalance(method.cents)}
                            </dd>
                        </div>
                    ))}
                    <div className="contents font-medium">
                        <dt className="border-t pt-2">Total recibido</dt>
                        <dd className="border-t pt-2 text-right tabular-nums">
                            {formatBalance(totalCents)}
                        </dd>
                    </div>
                </dl>
            )}
        </ReportSection>
    );
}
