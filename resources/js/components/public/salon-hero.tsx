import AppLogoIcon from '@/components/app-logo-icon';
import { Badge } from '@/components/ui/badge';
import { OpeningStatus } from './opening-hours';
import type { PublicSalon } from './types';

type Props = {
    salon: Pick<
        PublicSalon,
        | 'name'
        | 'businessType'
        | 'clientele'
        | 'description'
        | 'logoUrl'
        | 'coverUrl'
        | 'openingHours'
    >;
};

export function SalonHero({ salon }: Props) {
    return (
        <header>
            <div
                className={
                    salon.coverUrl
                        ? 'relative aspect-video w-full overflow-hidden md:aspect-auto md:h-90'
                        : 'relative h-24 w-full overflow-hidden'
                }
            >
                {salon.coverUrl ? (
                    <>
                        <img
                            src={salon.coverUrl}
                            alt=""
                            className="size-full object-cover"
                        />
                        <div className="absolute inset-0 bg-linear-to-t from-background via-background/20 to-transparent" />
                    </>
                ) : (
                    <div className="size-full bg-primary/15" />
                )}
            </div>

            <div className="relative mx-auto -mt-10 flex w-full max-w-3xl flex-col gap-3 px-4">
                <div className="size-18 overflow-hidden rounded-2xl bg-card ring-4 ring-background">
                    {salon.logoUrl ? (
                        <img
                            src={salon.logoUrl}
                            alt={`Logo de ${salon.name}`}
                            className="size-full object-cover"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-9" />
                        </div>
                    )}
                </div>

                <h1 className="font-display text-3xl font-semibold text-balance break-words">
                    {salon.name}
                </h1>

                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">{salon.businessType}</Badge>
                    <Badge variant="outline">{salon.clientele}</Badge>
                </div>

                {salon.openingHours && (
                    <OpeningStatus hours={salon.openingHours} />
                )}

                {salon.description && (
                    <p className="text-base whitespace-pre-line text-muted-foreground">
                        {salon.description}
                    </p>
                )}
            </div>
        </header>
    );
}
