import { router } from '@inertiajs/react';
import { MessageCircle, Star } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { clientSince, formatLongDay } from '@/lib/clients';
import { update as updateVip } from '@/routes/clients/vip';
import type { ClientFile } from './types';

type Props = {
    client: ClientFile;
    /** The day of their first completed visit, if any. */
    firstVisit: string | null;
    canManageVip: boolean;
};

/** Who the file is about, how to reach them and whether they are VIP. */
export function ClientHeader({ client, firstVisit, canManageVip }: Props) {
    const getInitials = useInitials();
    const since = clientSince(client.since, firstVisit);
    const origin = client.hasAccount
        ? 'Tiene cuenta en peluWeb'
        : 'Registro creado por el salón';

    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-center gap-4">
                <span
                    aria-hidden
                    className="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-soft text-lg font-semibold text-brand-text"
                >
                    {getInitials(client.name)}
                </span>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="text-2xl font-semibold break-words">
                            {client.name}
                        </h1>
                        {client.isVip && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-medium text-brand-text">
                                <Star
                                    className="size-3 fill-current"
                                    aria-hidden
                                />
                                VIP
                            </span>
                        )}
                    </div>
                    <p className="text-sm break-words text-muted-foreground">
                        {[
                            client.phone,
                            since && `Cliente desde ${formatLongDay(since)}`,
                            origin,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                {client.whatsappUrl && (
                    <Button asChild variant="outline">
                        <a
                            href={client.whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={`Escribir a ${client.name} por WhatsApp`}
                        >
                            <MessageCircle aria-hidden /> WhatsApp
                        </a>
                    </Button>
                )}
                {canManageVip && (
                    <Button
                        variant={client.isVip ? 'default' : 'outline'}
                        aria-pressed={client.isVip}
                        className="only:col-span-2"
                        onClick={() =>
                            router.patch(
                                updateVip(client.id),
                                { is_vip: !client.isVip },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Star
                            className={
                                client.isVip ? 'fill-current' : undefined
                            }
                            aria-hidden
                        />
                        {client.isVip ? 'Quitar VIP' : 'Marcar como VIP'}
                    </Button>
                )}
            </div>
        </header>
    );
}
