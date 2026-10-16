import { Link } from '@inertiajs/react';
import { LegalFooter } from '@/components/legal/legal-footer';
import { home } from '@/routes';

/** The platform's quiet signature and legal line; room below for the dock on phones. */
export function SalonFooter() {
    return (
        <footer className="px-4 pt-6 pb-[calc(7rem+env(safe-area-inset-bottom))] text-center text-sm text-muted-foreground lg:pb-10">
            Página hecha con{' '}
            <span className="font-semibold text-foreground">peluWeb</span>
            {' · '}
            <Link
                href={home()}
                className="font-semibold text-brand-text underline-offset-4 hover:underline"
            >
                ¿Tienes un salón?
            </Link>
            <LegalFooter as="div" className="px-0 pt-3 pb-0" />
        </footer>
    );
}
