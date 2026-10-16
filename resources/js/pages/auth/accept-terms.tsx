import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import DeleteUser from '@/components/delete-user';
import { TermsCheckbox } from '@/components/legal/terms-checkbox';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Spinner } from '@/components/ui/spinner';
import { formatFullDate } from '@/lib/format';
import { login, logout } from '@/routes';
import { store as acceptGoogleTerms } from '@/routes/google/terms';
import { store as acceptTerms } from '@/routes/legal/acceptance';

type LegalDocument = {
    type: string;
    title: string;
    label: string;
    version: string;
    effectiveOn: string;
    changes: string;
    url: string;
};

type Props = {
    /** first: never accepted · update: a new version · google: no account yet */
    mode: 'first' | 'update' | 'google';
    account: { name: string; email: string };
    documents: LegalDocument[];
};

const HEADINGS = {
    first: {
        title: 'Antes de seguir',
        description: 'Lee y acepta los textos que rigen tu cuenta.',
    },
    update: {
        title: 'Actualizamos nuestros términos',
        description: 'Este es el resumen de lo que cambió:',
    },
    google: {
        title: 'Un paso antes de crear tu cuenta',
        description: 'Lee y acepta los textos que rigen tu cuenta.',
    },
} as const;

/** Casillas 2 and 3: nobody goes on without accepting the current texts. */
export default function AcceptTerms({ mode, account, documents }: Props) {
    setLayoutProps(HEADINGS[mode]);
    const isGoogle = mode === 'google';

    return (
        <>
            <Head title={HEADINGS[mode].title} />
            <ul className="flex flex-col gap-3">
                {documents.map((document) => (
                    <li
                        key={document.type}
                        className="rounded-xl p-4 skin-card"
                    >
                        <a
                            href={document.url}
                            target="_blank"
                            rel="noopener"
                            className="flex items-start gap-3 font-semibold underline-offset-4 hover:underline"
                        >
                            <FileText
                                className="mt-0.5 size-5 shrink-0 text-brand-text"
                                strokeWidth={1.75}
                                aria-hidden
                            />
                            {document.label}
                        </a>
                        <p className="mt-1 pl-8 text-xs text-muted-foreground tabular-nums">
                            Versión {document.version} · desde el{' '}
                            {formatFullDate(document.effectiveOn)}
                        </p>
                        {mode === 'update' && (
                            <p className="mt-2 pl-8 text-sm text-pretty">
                                {document.changes}
                            </p>
                        )}
                    </li>
                ))}
            </ul>

            {isGoogle && (
                <p className="text-sm text-muted-foreground">
                    Tu cuenta será{' '}
                    <span className="font-medium text-foreground">
                        {account.email}
                    </span>
                    .
                </p>
            )}

            <Form
                {...(isGoogle ? acceptGoogleTerms.form() : acceptTerms.form())}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <TermsCheckbox error={errors.terms} />
                        <Button
                            type="submit"
                            className="w-full rounded-full"
                            data-test="accept-terms-button"
                        >
                            {processing && <Spinner />}
                            {isGoogle
                                ? 'Aceptar y crear mi cuenta'
                                : 'Aceptar y continuar'}
                        </Button>
                    </>
                )}
            </Form>

            {isGoogle ? (
                <Link
                    href={login()}
                    className="text-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                >
                    Cancelar y volver
                </Link>
            ) : (
                <NotAccepting />
            )}
        </>
    );
}

/** Whoever does not agree may still leave, or take their account with them. */
function NotAccepting() {
    return (
        <Collapsible className="flex flex-col gap-4 text-center">
            <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                <Link
                    href={logout()}
                    as="button"
                    className="min-h-11 underline-offset-4 hover:text-foreground hover:underline"
                >
                    Cerrar sesión
                </Link>
                <CollapsibleTrigger className="min-h-11 underline-offset-4 hover:text-foreground hover:underline">
                    No acepto: borrar mi cuenta
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent className="text-left">
                <DeleteUser />
            </CollapsibleContent>
        </Collapsible>
    );
}
