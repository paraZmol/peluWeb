import { Link } from '@inertiajs/react';
import { Scissors } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index as servicesIndex } from '@/routes/services';
import { CardPicker } from './card-picker';
import type { PromotionCardKind, PromotionService } from './types';

type Props = {
    services: PromotionService[];
    today: string;
    onPick: (card: PromotionCardKind) => void;
};

/**
 * A salon without promotions yet: the cards as the invitation. With
 * no services there is nothing to promote, so it sends there first.
 */
export function PromotionInvitation({ services, today, onPick }: Props) {
    if (services.length === 0) {
        return (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                <Scissors
                    className="size-6 text-muted-foreground"
                    aria-hidden
                />
                <p className="text-muted-foreground">
                    Una promoción se hace con tus servicios. Crea al menos uno y
                    vuelve aquí.
                </p>
                <Button asChild>
                    <Link href={servicesIndex()}>Ir a Servicios</Link>
                </Button>
            </div>
        );
    }

    return (
        <section
            aria-labelledby="first-promotion"
            className="flex flex-col gap-4"
        >
            <div>
                <h2
                    id="first-promotion"
                    className="font-display text-xl font-semibold"
                >
                    Crea tu primera promoción
                </h2>
                <p className="text-sm text-muted-foreground">
                    Elige de dónde partir. Rellenas dos o tres datos, ves cómo
                    queda y la publicas.
                </p>
            </div>
            <CardPicker services={services} today={today} onPick={onPick} />
        </section>
    );
}
