import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { PublicTeamMember } from '../types';

type Props = { person: PublicTeamMember; className?: string };

/** A professional's portrait in a photo frame; their initials without one. */
export function StaffPhoto({ person, className }: Props) {
    const getInitials = useInitials();

    return (
        <div className={cn('photo-frame', className)}>
            {person.photoUrl ? (
                <img src={person.photoUrl} alt={person.name} loading="lazy" />
            ) : (
                <span
                    aria-hidden
                    className="flex size-full items-center justify-center bg-brand-soft display-type text-4xl text-brand-text"
                >
                    {getInitials(person.name)}
                </span>
            )}
        </div>
    );
}
