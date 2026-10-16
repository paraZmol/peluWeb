import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, History } from 'lucide-react';
import { BrandMark } from '@/components/landing/brand-mark';
import { LegalFooter } from '@/components/legal/legal-footer';
import { LegalProse } from '@/components/legal/legal-prose';
import type { LegalDocumentType } from '@/hooks/use-legal-links';
import { formatFullDate } from '@/lib/format';
import { home } from '@/routes';
import { show, version as versionRoute } from '@/routes/legal';

type Props = {
    document: {
        type: LegalDocumentType;
        title: string;
        version: string;
        effectiveOn: string;
        hash: string;
        isCurrent: boolean;
        html: string;
    };
    versions: string[];
    others: { type: LegalDocumentType; label: string }[];
};

const ASIDE_TITLE =
    'text-xs font-bold tracking-[.16em] text-muted-foreground uppercase';
const ASIDE_LINK =
    'inline-flex min-h-10 items-center text-sm underline-offset-4 hover:text-brand-text hover:underline';

/** One of the platform's legal texts, with its version and the earlier ones. */
export default function LegalPage({ document, versions, others }: Props) {
    return (
        <div className="min-h-svh bg-background text-foreground">
            <Head title={document.title} />
            <header className="border-b bg-band">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <Link href={home()} aria-label="Ir al inicio">
                        <BrandMark />
                    </Link>
                    <Link
                        href={home()}
                        className="inline-flex min-h-11 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft
                            className="size-4"
                            strokeWidth={1.75}
                            aria-hidden
                        />
                        Volver al inicio
                    </Link>
                </div>
                <div className="mx-auto w-full max-w-5xl px-4 pt-6 pb-10 sm:px-6 sm:pt-10">
                    <p className={ASIDE_TITLE}>Legal</p>
                    <h1 className="mt-2 font-display text-title-2 font-semibold text-balance">
                        {document.title}
                    </h1>
                    <p className="mt-3 text-sm text-muted-foreground tabular-nums">
                        Versión {document.version} · vigente desde el{' '}
                        {formatFullDate(document.effectiveOn)}
                    </p>
                    {!document.isCurrent && (
                        <p className="mt-4 inline-flex flex-wrap items-center gap-2 rounded-full bg-brand-soft px-4 py-2 text-sm">
                            <History
                                className="size-4 text-brand-text"
                                strokeWidth={1.75}
                                aria-hidden
                            />
                            Esta es una versión anterior.
                            <Link
                                href={show(document.type)}
                                className="font-semibold text-brand-text underline underline-offset-4"
                            >
                                Ver la vigente
                            </Link>
                        </p>
                    )}
                </div>
            </header>

            <main className="mx-auto grid w-full max-w-5xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_15rem] lg:py-12">
                <article className="min-w-0 rounded-xl p-5 skin-card sm:p-10">
                    <LegalProse html={document.html} />
                </article>

                <aside className="flex flex-col gap-8 lg:sticky lg:top-6 lg:self-start">
                    <nav
                        aria-label="Otros textos legales"
                        className="flex flex-col gap-1"
                    >
                        <p className={ASIDE_TITLE}>Otros textos</p>
                        {others.map((other) => (
                            <Link
                                key={other.type}
                                href={show(other.type)}
                                className={ASIDE_LINK}
                            >
                                {other.label}
                            </Link>
                        ))}
                    </nav>
                    <nav aria-label="Versiones" className="flex flex-col gap-1">
                        <p className={ASIDE_TITLE}>Versiones</p>
                        {versions.map((number, index) => (
                            <Link
                                key={number}
                                href={versionRoute({
                                    document: document.type,
                                    version: number,
                                })}
                                aria-current={
                                    number === document.version
                                        ? 'page'
                                        : undefined
                                }
                                className={`${ASIDE_LINK} aria-[current=page]:font-semibold aria-[current=page]:text-brand-text`}
                            >
                                Versión {number}
                                {index === 0 && ' (vigente)'}
                            </Link>
                        ))}
                    </nav>
                    <p className="text-xs break-all text-muted-foreground">
                        Huella del texto (SHA-256): {document.hash}
                    </p>
                </aside>
            </main>

            <LegalFooter className="border-t" />
        </div>
    );
}
