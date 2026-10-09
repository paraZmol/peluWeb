import type { PublicTeamMember } from '../types';
import { StaffPhoto } from './staff-photo';

/**
 * Luminosa and Editorial: a square photo in a card, with the name and the
 * role (in the brand's color) underneath.
 */
export function AvatarCard({ person }: { person: PublicTeamMember }) {
    return (
        <article className="flex h-full flex-col gap-3 rounded-xl p-2 pb-4 text-center skin-card">
            <StaffPhoto
                person={person}
                className="aspect-square rounded-portrait"
            />
            <div className="flex flex-col gap-0.5 px-1">
                <h3 className="text-sm font-semibold break-words sm:text-base">
                    {person.name}
                </h3>
                {person.title && (
                    <p className="text-sm leading-tight font-medium break-words text-brand-text">
                        {person.title}
                    </p>
                )}
            </div>
        </article>
    );
}
