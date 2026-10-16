import { BookOpen } from 'lucide-react';
import type { PublicComplaintsBook } from './types';

type Props = {
    salonName: string;
    address: string | null;
    book: PublicComplaintsBook;
};

/**
 * The salon's own Libro de Reclamaciones, always at the foot of its page
 * (requirement P7): its link, or where to find it at the premises.
 */
export function SalonComplaintsLine({ salonName, address, book }: Props) {
    const provider = [book.legalName, book.ruc && `RUC ${book.ruc}`]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="mx-auto flex max-w-2xl flex-col items-center gap-1 px-4 pt-12 text-center text-sm text-muted-foreground">
            <p className="flex flex-wrap items-center justify-center gap-x-2">
                <BookOpen
                    className="size-4 shrink-0"
                    strokeWidth={1.75}
                    aria-hidden
                />
                {book.url ? (
                    <a
                        href={book.url}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        className="font-semibold text-foreground underline-offset-4 hover:underline"
                    >
                        Libro de Reclamaciones de {salonName}
                    </a>
                ) : (
                    <span>
                        Libro de Reclamaciones de {salonName}: disponible en su
                        local{address ? `, ${address}` : ''}.
                    </span>
                )}
            </p>
            {provider && <p>{provider}</p>}
        </div>
    );
}
