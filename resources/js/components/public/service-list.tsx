import { MessageCircle, Scissors } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ServiceRow } from './service-row';
import type { PublicServiceGroup } from './types';

type Props = {
    groups: PublicServiceGroup[];
    bookingUrl: string | null;
    whatsappUrl: string | null;
};

export function ServiceList({ groups, bookingUrl, whatsappUrl }: Props) {
    const visibleGroups = groups.filter((group) => group.services.length > 0);

    return (
        <div className="flex flex-col gap-6">
            <h2 className="font-display text-2xl font-semibold">Servicios</h2>

            {visibleGroups.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border bg-card p-6 text-center">
                    <Scissors className="size-6 text-primary" aria-hidden />
                    <p className="font-medium">
                        Pronto verás aquí los servicios
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Mientras tanto, escríbenos y te contamos qué ofrecemos.
                    </p>
                    {whatsappUrl && (
                        <Button asChild className="mt-2 rounded-full">
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle aria-hidden /> Escribir por
                                WhatsApp
                            </a>
                        </Button>
                    )}
                </div>
            ) : (
                visibleGroups.map((group) => (
                    <div key={group.label} className="flex flex-col gap-3">
                        <h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            {group.label}
                        </h3>
                        <ul className="divide-y rounded-xl border bg-card">
                            {group.services.map((service) => (
                                <ServiceRow
                                    key={service.id}
                                    service={service}
                                    bookingUrl={bookingUrl}
                                />
                            ))}
                        </ul>
                    </div>
                ))
            )}
        </div>
    );
}
