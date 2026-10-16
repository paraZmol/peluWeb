import { usePage } from '@inertiajs/react';
import { show } from '@/routes/legal';

export type LegalLink = { label: string; href: string };

export const LEGAL_DOCUMENTS = [
    { type: 'terminos-clientes', label: 'Términos y condiciones' },
    { type: 'terminos-salones', label: 'Términos para salones' },
    { type: 'privacidad', label: 'Política de privacidad' },
    { type: 'cookies', label: 'Política de cookies' },
] as const;

export type LegalDocumentType = (typeof LEGAL_DOCUMENTS)[number]['type'];

export const COOKIE_NOTICE =
    'Usamos solo cookies necesarias para que el sitio funcione y recuerde tus preferencias.';

/**
 * The platform's legal texts and its Libro de Reclamaciones, plus who
 * provides the service, as every footer shows them (requisito P1).
 */
export function useLegalLinks(): {
    links: LegalLink[];
    provider: string[];
} {
    const { legal } = usePage().props;

    const links: LegalLink[] = LEGAL_DOCUMENTS.map(({ type, label }) => ({
        label,
        href: show(type).url,
    }));

    if (legal.complaintsUrl) {
        links.push({
            label: 'Libro de Reclamaciones',
            href: legal.complaintsUrl,
        });
    }

    const provider = [
        legal.providerName,
        legal.ruc && `RUC ${legal.ruc}`,
        legal.contactEmail,
    ].filter((part): part is string => Boolean(part));

    return { links, provider };
}
