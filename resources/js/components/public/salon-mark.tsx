import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

const SIZES = {
    sm: 'size-10 text-base',
    md: 'size-14 text-xl',
    lg: 'size-20 text-3xl',
};

type Props = {
    name: string;
    logoUrl: string | null;
    size?: keyof typeof SIZES;
    className?: string;
};

/** The salon's logo, or its initials on its own color when it has none. */
export function SalonMark({ name, logoUrl, size = 'md', className }: Props) {
    const getInitials = useInitials();

    if (logoUrl) {
        return (
            <img
                src={logoUrl}
                alt={`Logo de ${name}`}
                className={cn(
                    'shrink-0 rounded-lg bg-card object-cover shadow-card',
                    SIZES[size],
                    className,
                )}
            />
        );
    }

    return (
        <span
            aria-hidden
            className={cn(
                'flex shrink-0 items-center justify-center rounded-lg skin-primary display-type',
                SIZES[size],
                className,
            )}
        >
            {getInitials(name)}
        </span>
    );
}
