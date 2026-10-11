import { Head, setLayoutProps } from '@inertiajs/react';
import { BeforeAttending } from '@/components/clients/before-attending';
import { CareConsentSheet } from '@/components/clients/care-consent';
import { ClientHeader } from '@/components/clients/client-header';
import { ClientPhotos } from '@/components/clients/client-photos';
import { ClientStats } from '@/components/clients/client-stats';
import { NotesForm } from '@/components/clients/notes-form';
import type {
    ClientFile,
    ClientPhoto,
    ClientStats as Stats,
    ClientVisit,
} from '@/components/clients/types';
import {
    NoPastVisits,
    NoUpcomingVisits,
} from '@/components/clients/visit-empty-states';
import { VisitList } from '@/components/clients/visit-list';
import { cn } from '@/lib/utils';
import { index as agenda } from '@/routes/agenda';
import { index as clients, show } from '@/routes/clients';

type Props = {
    client: ClientFile;
    stats: Stats;
    upcoming: ClientVisit[];
    history: ClientVisit[];
    /** Only the latest visits are listed. */
    isHistoryTruncated: boolean;
    canSeeCare: boolean;
    /** What the person signs before care notes are kept; null without access. */
    careConsentText: string | null;
    canManageVip: boolean;
    notesMaxLength: number;
    /** Before and after pictures; empty for whoever may not see them. */
    photos: ClientPhoto[];
    photosMax: number;
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
    photos,
    photosMax,
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
                    // Room for the docked save bar of the notes on a phone.
                    'max-sm:has-[[data-unsaved]]:pb-28',
                    // On paper this page is the authorization to sign.
                    canSeeCare && 'print:hidden',
                )}
            >
                <ClientHeader
                    client={client}
                    firstVisit={stats.firstVisit}
                    canManageVip={canManageVip}
                />
                <BeforeAttending client={client} canSeeCare={canSeeCare} />
                <ClientStats stats={stats} />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_26rem] lg:grid-rows-[auto_auto_1fr]">
                    <VisitList
                        id="client-upcoming-title"
                        title="Próximas citas"
                        visits={upcoming}
                        className="lg:col-start-1 lg:row-start-1"
                        empty={<NoUpcomingVisits />}
                    />
                    <NotesForm
                        // A saved file starts the form again from what was kept.
                        key={client.careConsent?.date ?? 'no-consent'}
                        client={client}
                        canSeeCare={canSeeCare}
                        careConsentText={careConsentText}
                        maxLength={notesMaxLength}
                        className="lg:col-start-2 lg:row-span-3 lg:row-start-1"
                    />
                    <VisitList
                        id="client-history-title"
                        title="Historial"
                        visits={history}
                        // On a wide screen the pictures sit above it.
                        className={cn(
                            'lg:col-start-1',
                            canSeeCare ? 'lg:row-start-3' : 'lg:row-start-2',
                        )}
                        empty={<NoPastVisits />}
                        footnote={
                            isHistoryTruncated
                                ? `Mostramos las últimas ${history.length} citas.`
                                : undefined
                        }
                    />
                    {canSeeCare && (
                        <ClientPhotos
                            clientId={client.id}
                            clientName={client.name}
                            photos={photos}
                            max={photosMax}
                            className="lg:col-start-1 lg:row-start-2"
                        />
                    )}
                </div>
            </div>

            {careConsentText !== null && (
                <CareConsentSheet
                    clientName={client.name}
                    text={careConsentText}
                />
            )}
        </>
    );
}
