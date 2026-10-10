import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarPlus, History } from 'lucide-react';
import { CareConsentSheet } from '@/components/clients/care-consent';
import { ClientHeader } from '@/components/clients/client-header';
import { ClientStats } from '@/components/clients/client-stats';
import { NotesForm } from '@/components/clients/notes-form';
import type {
    ClientFile,
    ClientStats as Stats,
    ClientVisit,
} from '@/components/clients/types';
import { VisitList } from '@/components/clients/visit-list';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as agenda } from '@/routes/agenda';
import { create as newBooking } from '@/routes/agenda/bookings';
import { index as clients, show } from '@/routes/clients';

type Props = {
    client: ClientFile;
    stats: Stats;
    upcoming: ClientVisit[];
    history: ClientVisit[];
    /** Only the latest visits are listed. */
    isHistoryTruncated: boolean;
    canSeeCare: boolean;
    /** What the person signs before their care notes may be kept. */
    careConsentText: string;
    canManageVip: boolean;
    notesMaxLength: number;
};

export default function Client({
    client,
    stats,
    upcoming,
    history,
    isHistoryTruncated,
    canSeeCare,
    careConsentText,
    canManageVip,
    notesMaxLength,
}: Props) {
    // The client list is only for who manages it; the rest came from the agenda.
    setLayoutProps({
        breadcrumbs: [
            canManageVip
                ? { title: 'Clientes', href: clients() }
                : { title: 'Agenda', href: agenda() },
            { title: client.name, href: show(client.id) },
        ],
    });

    return (
        <>
            <Head title={client.name} />

            <div
                className={cn(
                    'mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6',
                    // On paper this page is the authorization to sign.
                    canSeeCare && 'print:hidden',
                )}
            >
                <ClientHeader client={client} canManageVip={canManageVip} />
                <ClientStats stats={stats} />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_26rem] lg:grid-rows-[auto_1fr]">
                    <VisitList
                        id="client-upcoming-title"
                        title="Próximas citas"
                        visits={upcoming}
                        empty={
                            <>
                                <CalendarPlus className="size-6" aria-hidden />
                                <p>No tiene citas próximas.</p>
                                <Button asChild variant="outline">
                                    <Link href={newBooking()}>
                                        Agendar una cita
                                    </Link>
                                </Button>
                            </>
                        }
                    />
                    <NotesForm
                        // A saved file starts the form again from what was kept.
                        key={client.careConsent?.date ?? 'no-consent'}
                        client={client}
                        canSeeCare={canSeeCare}
                        careConsentText={careConsentText}
                        maxLength={notesMaxLength}
                        className="lg:col-start-2 lg:row-span-2 lg:row-start-1"
                    />
                    <VisitList
                        id="client-history-title"
                        title="Historial"
                        visits={history}
                        className="lg:col-start-1"
                        empty={
                            <>
                                <History className="size-6" aria-hidden />
                                <p>
                                    Todavía no hay visitas terminadas. Aquí
                                    verás qué se hizo, quién atendió y si faltó
                                    o canceló alguna vez.
                                </p>
                            </>
                        }
                        footnote={
                            isHistoryTruncated
                                ? `Mostramos las últimas ${history.length} citas.`
                                : undefined
                        }
                    />
                </div>
            </div>

            {canSeeCare && (
                <CareConsentSheet
                    clientName={client.name}
                    text={careConsentText}
                />
            )}
        </>
    );
}
