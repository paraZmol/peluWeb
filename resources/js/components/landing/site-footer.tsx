import { Link } from '@inertiajs/react';
import { COOKIE_NOTICE, useLegalLinks } from '@/hooks/use-legal-links';
import { show } from '@/routes/legal';
import { BrandMark } from './brand-mark';
import { BRAND_NAME, FOOTER } from './content';
import { CONTAINER_CLASS } from './landing-section';

const COLUMN_TITLE_CLASS = 'text-sm font-semibold';
const LINK_CLASS =
    'inline-flex min-h-11 items-center text-sm text-muted-foreground transition-colors hover:text-foreground md:min-h-9';

export function SiteFooter() {
    const year = new Date().getFullYear();
    const { links: legalLinks, provider } = useLegalLinks();

    return (
        <footer className="border-t bg-card">
            <div className={`${CONTAINER_CLASS} py-12 md:py-16`}>
                <div className="grid grid-cols-2 gap-x-6 gap-y-10 lg:grid-cols-[1.6fr_1fr_1fr_1.4fr]">
                    <div className="col-span-2 flex max-w-xs flex-col gap-3 lg:col-span-1">
                        <BrandMark />
                        <p className="text-sm text-muted-foreground">
                            {FOOTER.description}
                        </p>
                    </div>

                    {FOOTER.columns.map((column) => (
                        <nav
                            key={column.title}
                            aria-label={column.title}
                            className="flex flex-col gap-2"
                        >
                            <p className={COLUMN_TITLE_CLASS}>{column.title}</p>
                            <ul className="flex flex-col">
                                {column.links.map((link) => (
                                    <li key={link.href}>
                                        <a
                                            href={link.href}
                                            className={LINK_CLASS}
                                        >
                                            {link.label}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    ))}

                    <nav
                        aria-label={FOOTER.legalTitle}
                        className="col-span-2 flex flex-col gap-2 sm:col-span-1"
                    >
                        <p className={COLUMN_TITLE_CLASS}>
                            {FOOTER.legalTitle}
                        </p>
                        <ul className="flex flex-col">
                            {legalLinks.map((link) => (
                                <li key={link.href}>
                                    <Link
                                        href={link.href}
                                        className={LINK_CLASS}
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                </div>

                <div className="mt-12 flex flex-col gap-2 border-t pt-6 text-sm text-muted-foreground">
                    <p>
                        © {year} {BRAND_NAME}. {FOOTER.madeIn}
                        {provider.length > 0 && ` ${provider.join(' · ')}`}
                    </p>
                    <p className="text-xs text-pretty">
                        {COOKIE_NOTICE}{' '}
                        <Link
                            href={show('cookies')}
                            className="underline underline-offset-4 hover:text-foreground"
                        >
                            Más información
                        </Link>
                    </p>
                </div>
            </div>
        </footer>
    );
}
