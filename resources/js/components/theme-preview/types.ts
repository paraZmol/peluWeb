/** One service row of a miniature page (sample text, never real data). */
export type MiniService = {
    name: string;
    minutes: number;
    /** Already formatted amount, e.g. "35.00". */
    price: string;
};

/**
 * What a miniature of a salon page needs: enough to recognise the salon
 * (name, photos, logo) inside each template. Rendered inside a BrandTheme.
 */
export type MiniSalon = {
    name: string;
    /** "Salón de belleza · Miraflores" */
    subtitle: string;
    coverUrl: string | null;
    logoUrl: string | null;
    /** More photos for the collage and the featured service card. */
    photoUrls: string[];
    services: MiniService[];
};
