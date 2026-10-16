import { Link } from '@inertiajs/react';
import { COOKIE_NOTICE, useLegalLinks } from '@/hooks/use-legal-links';
import { show } from '@/routes/legal';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    /** `div` when it goes inside a footer of its own (the salon's page). */
    as?: 'footer' | 'div';
};

/**
 * The legal line every screen ends with: the texts, the Libro de
 * Reclamaciones, who provides the service and the cookie notice. Tokens
 * only, so it takes the look of the platform or of a salon's template.
 */
export function LegalFooter({ className, as: Tag = 'footer' }: Props) {
    const { links, provider } = useLegalLinks();

    return (
        <Tag
            className={cn(
                'flex flex-col items-center gap-2 px-4 py-6 text-center text-xs text-muted-foreground',
                className,
            )}
        >
            <nav aria-label="Legal">
                <ul className="flex flex-wrap justify-center gap-x-4 gap-y-1">
                    {links.map((link) => (
                        <li key={link.href}>
                            <Link
                                href={link.href}
                                className="inline-flex min-h-8 items-center underline-offset-4 hover:text-foreground hover:underline"
                            >
                                {link.label}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
            {provider.length > 0 && <p>{provider.join(' · ')}</p>}
            <p className="text-pretty">
                {COOKIE_NOTICE}{' '}
                <Link
                    href={show('cookies')}
                    className="underline underline-offset-4 hover:text-foreground"
                >
                    Más información
                </Link>
            </p>
        </Tag>
    );
}
