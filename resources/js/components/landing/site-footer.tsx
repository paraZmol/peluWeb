import { BrandMark } from './brand-mark';
import { BRAND_NAME, FOOTER } from './content';
import { CONTAINER_CLASS } from './landing-section';

const COLUMN_TITLE_CLASS = 'text-sm font-semibold';

export function SiteFooter() {
    const year = new Date().getFullYear();

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
                                            className="inline-flex min-h-11 items-center text-sm text-muted-foreground transition-colors hover:text-foreground md:min-h-9"
                                        >
                                            {link.label}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    ))}

                    {/* Plain text until those pages exist, so there are no broken links */}
                    <div className="col-span-2 flex flex-col gap-2 sm:col-span-1">
                        <p className={COLUMN_TITLE_CLASS}>
                            {FOOTER.legal.title}
                        </p>
                        <ul className="flex flex-col">
                            {FOOTER.legal.items.map((item) => (
                                <li
                                    key={item}
                                    className="flex min-h-11 flex-wrap items-center gap-x-1.5 text-sm md:min-h-9"
                                >
                                    <span className="text-muted-foreground">
                                        {item}
                                    </span>
                                    <span className="text-xs text-muted-foreground/70">
                                        {FOOTER.legal.soonLabel}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <p className="mt-12 border-t pt-6 text-sm text-muted-foreground">
                    © {year} {BRAND_NAME}. {FOOTER.madeIn}
                </p>
            </div>
        </footer>
    );
}
