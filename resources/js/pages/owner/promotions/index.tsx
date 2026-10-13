import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { NewPromotionDialog } from '@/components/promotions/new-promotion-dialog';
import { PromotionCard } from '@/components/promotions/promotion-card';
import { PromotionInvitation } from '@/components/promotions/promotion-invitation';
import { PromotionSheet } from '@/components/promotions/promotion-sheet';
import { ShareDialog } from '@/components/promotions/share-dialog';
import type {
    Promotion,
    PromotionCardKind,
    PromotionContext,
} from '@/components/promotions/types';
import { Button } from '@/components/ui/button';
import type { PromotionTarget } from '@/hooks/use-promotion-form';
import { index } from '@/routes/promotions';

type Props = PromotionContext & { promotions: Promotion[] };

/** What each dialog shows stays while it closes, so its content does not flip. */
type Open = {
    isPicking: boolean;
    isFilling: boolean;
    target?: PromotionTarget;
    isSharing: boolean;
    shared?: Promotion;
};

export default function Promotions({ promotions, ...context }: Props) {
    const [open, setOpen] = useState<Open>({
        isPicking: false,
        isFilling: false,
        isSharing: false,
    });
    const { services, limits, today } = context;
    const isEmpty = promotions.length === 0;
    const change = (changes: Partial<Open>) =>
        setOpen((now) => ({ ...now, ...changes }));
    const fill = (target: PromotionTarget) =>
        change({ isPicking: false, isFilling: true, target });
    const startFrom = (card: PromotionCardKind) => fill({ card });

    return (
        <>
            <Head title="Promociones" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Promociones</h1>
                        <p className="text-sm text-muted-foreground">
                            Se aplican solas al reservar, sin códigos. Puedes
                            tener hasta {limits.maxActive} publicadas a la vez.
                        </p>
                    </div>
                    {!isEmpty && (
                        <Button onClick={() => change({ isPicking: true })}>
                            <Plus aria-hidden /> Nueva promoción
                        </Button>
                    )}
                </div>

                {isEmpty ? (
                    <PromotionInvitation
                        services={services}
                        today={today}
                        onPick={startFrom}
                    />
                ) : (
                    <ul className="grid items-start gap-4 lg:grid-cols-2">
                        {promotions.map((promotion) => (
                            <PromotionCard
                                key={promotion.id}
                                promotion={promotion}
                                onEdit={(one) =>
                                    fill({ card: one.card, promotion: one })
                                }
                                onShare={(shared) =>
                                    change({ isSharing: true, shared })
                                }
                            />
                        ))}
                    </ul>
                )}
            </div>

            <NewPromotionDialog
                open={open.isPicking}
                onClose={() => change({ isPicking: false })}
                services={services}
                today={today}
                onPick={startFrom}
            />
            <PromotionSheet
                open={open.isFilling}
                onClose={() => change({ isFilling: false })}
                target={open.target}
                context={context}
            />
            <ShareDialog
                open={open.isSharing}
                onClose={() => change({ isSharing: false })}
                promotion={open.shared}
            />
        </>
    );
}

Promotions.layout = {
    breadcrumbs: [{ title: 'Promociones', href: index() }],
};
