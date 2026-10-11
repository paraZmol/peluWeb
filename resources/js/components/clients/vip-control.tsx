import { router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { update as updateVip } from '@/routes/clients/vip';

type Props = {
    client: { id: number; name: string; isVip: boolean };
    /** false: they see who is VIP but cannot change it. */
    canManage: boolean;
};

/** The VIP switch of a client in the list, or just the mark for who cannot change it. */
export function VipControl({ client, canManage }: Props) {
    if (!canManage) {
        return client.isVip ? (
            <span className="inline-flex items-center gap-1 text-sm font-medium">
                <Star className="size-4 fill-current" aria-hidden />
                VIP
            </span>
        ) : null;
    }

    return (
        <Button
            variant={client.isVip ? 'default' : 'outline'}
            aria-pressed={client.isVip}
            aria-label={`${client.name}: ${client.isVip ? 'quitar VIP' : 'marcar como VIP'}`}
            onClick={() =>
                router.patch(
                    updateVip(client.id),
                    { is_vip: !client.isVip },
                    { preserveScroll: true },
                )
            }
        >
            <Star className={client.isVip ? 'fill-current' : undefined} />
            VIP
        </Button>
    );
}
