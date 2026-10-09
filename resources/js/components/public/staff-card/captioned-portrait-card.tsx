import type { PublicTeamMember } from '../types';
import { StaffPhoto } from './staff-photo';

/**
 * Vibrante: the portrait in its arch, with the name and the role under it.
 * The name goes in the body face: Syne is too wide for a third of a phone
 * and would split it in three lines over the photo.
 */
export function CaptionedPortraitCard({
    person,
}: {
    person: PublicTeamMember;
}) {
    return (
        <article className="flex h-full flex-col gap-3">
            <StaffPhoto
                person={person}
                className="aspect-4/5 rounded-portrait shadow-card"
            />
            <div className="flex flex-col gap-0.5 px-1">
                <h3 className="font-sans text-base leading-snug font-bold break-words">
                    {person.name}
                </h3>
                {person.title && (
                    <p className="text-sm leading-tight font-medium break-words text-muted-foreground">
                        {person.title}
                    </p>
                )}
            </div>
        </article>
    );
}
