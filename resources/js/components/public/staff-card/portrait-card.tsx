import { FitTitle } from '../fit-title';
import type { PublicTeamMember } from '../types';
import { StaffPhoto } from './staff-photo';

/**
 * Nocturna: a 4:5 portrait with the name in the display face on a veil,
 * and the role under it.
 */
export function PortraitCard({ person }: { person: PublicTeamMember }) {
    return (
        <article className="relative">
            <StaffPhoto
                person={person}
                className="aspect-4/5 rounded-portrait shadow-card"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 rounded-portrait photo-veil"
            />
            <div className="@container absolute inset-x-0 bottom-0 z-10 flex flex-col gap-1 p-3 text-on-photo sm:p-4">
                <FitTitle as="h3" size="title-3" text={person.name} />
                {person.title && (
                    <p className="text-sm leading-tight font-medium break-words text-on-photo/80">
                        {person.title}
                    </p>
                )}
            </div>
        </article>
    );
}
