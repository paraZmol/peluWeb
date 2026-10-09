import { Instagram } from 'lucide-react';
import { CHIP_ICON, GLASS_ICON, ON_BRAND_ICON } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { SalonMark } from '../salon-mark';
import { ShareButton } from '../share-button';
import type { PublicSalon } from '../types';

const ICON_TONES = {
    glass: GLASS_ICON,
    'on-brand': ON_BRAND_ICON,
    chip: cn(CHIP_ICON, 'size-11'),
};

type Props = {
    salon: Pick<PublicSalon, 'name' | 'logoUrl' | 'instagramUrl'>;
    tone: keyof typeof ICON_TONES;
    /** The logo on the left (when the cover does not show it bigger). */
    showMark?: boolean;
    className?: string;
};

/** Top row of the cover: the salon's mark, Instagram and share. */
export function HeroToolbar({
    salon,
    tone,
    showMark = false,
    className,
}: Props) {
    const icon = ICON_TONES[tone];

    return (
        <div className={cn('flex items-center gap-2', className)}>
            {showMark && (
                <SalonMark
                    name={salon.name}
                    logoUrl={salon.logoUrl}
                    size="sm"
                />
            )}
            <div className="ml-auto flex items-center gap-2">
                {salon.instagramUrl && (
                    <a
                        href={salon.instagramUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Ver en Instagram"
                        className={icon}
                    >
                        <Instagram aria-hidden />
                    </a>
                )}
                <ShareButton salonName={salon.name} className={icon} />
            </div>
        </div>
    );
}
