import {
    MiniCover,
    MiniMonogram,
    MiniName,
    MiniPills,
    MiniStatus,
} from './mini-parts';
import type { MiniSalon } from './types';

type Props = { salon: MiniSalon };

/** Cover with the name on it and a floating card over its edge (Luminosa). */
export function MiniCardOverCover({ salon }: Props) {
    return (
        <div className="relative">
            <MiniCover src={salon.coverUrl} className="h-[74cqi]">
                <div className="absolute inset-0 photo-veil" />
                <div className="absolute inset-x-0 bottom-[11cqi] z-10 flex flex-col gap-[1.4cqi] px-[5cqi] text-on-photo">
                    <span className="truncate text-[2.8cqi] font-semibold text-on-photo/85">
                        {salon.subtitle}
                    </span>
                    <MiniName name={salon.name} fitSize="11cqi" />
                </div>
            </MiniCover>
            <div className="relative mx-[4cqi] -mt-[8cqi]">
                <div
                    aria-hidden
                    className="absolute -inset-[2cqi] rounded-[6cqi] brand-halo"
                />
                <div className="relative flex flex-col gap-[2.6cqi] rounded-[4.5cqi] p-[4cqi] skin-raised">
                    <div className="flex items-center gap-[2.4cqi]">
                        <MiniMonogram
                            salon={salon}
                            className="size-[8cqi] rounded-full text-[3cqi]"
                        />
                        <MiniStatus />
                    </div>
                    <MiniPills />
                </div>
            </div>
        </div>
    );
}

/** A block of brand color with the name, and a collage of photo shapes (Vibrante). */
export function MiniColorBlock({ salon }: Props) {
    const photos = [salon.coverUrl, ...salon.photoUrls].filter(
        (url): url is string => url !== null,
    );
    const hasCollage = photos.length > 0;

    return (
        <div className="relative">
            <div
                className={
                    hasCollage
                        ? 'bg-primary px-[5cqi] pt-[7cqi] pb-[24cqi] text-primary-foreground'
                        : 'rounded-b-[8cqi] bg-primary px-[5cqi] pt-[7cqi] pb-[8cqi] text-primary-foreground'
                }
            >
                <div className="flex items-center justify-between gap-[2cqi]">
                    <span className="-rotate-3 rounded-full px-[2.6cqi] py-[1cqi] text-[2.8cqi] font-bold skin-chip">
                        Abierto hoy
                    </span>
                    <MiniMonogram
                        salon={salon}
                        className="size-[9cqi] rounded-full text-[3.2cqi]"
                    />
                </div>
                <MiniName
                    name={salon.name}
                    fitSize="11cqi"
                    className="mt-[3cqi]"
                />
                <p className="mt-[1.4cqi] truncate text-[3cqi] font-medium">
                    {salon.subtitle}
                </p>
                <MiniPills tone="chip" className="mt-[3cqi]" />
            </div>
            {hasCollage && (
                <div className="relative -mt-[19cqi] flex items-end gap-[2.5cqi] px-[5cqi]">
                    <MiniCover
                        src={photos[0]}
                        className="h-[32cqi] flex-[1.5] rounded-[3cqi_11cqi_3cqi_3cqi]"
                    />
                    <MiniCover
                        src={photos[1] ?? photos[0]}
                        className="size-[22cqi] rounded-full"
                    />
                    <MiniCover
                        src={photos[2] ?? photos[0]}
                        className="h-[27cqi] flex-1 rounded-[9cqi_2.5cqi_9cqi_2.5cqi]"
                    />
                </div>
            )}
        </div>
    );
}
