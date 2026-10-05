/** Mirrors App\Http\Resources\ServiceResource. */
export type SalonService = {
    id: number;
    name: string;
    description: string | null;
    category: string;
    target: string;
    price: string;
    priceCents: number;
    activeMinutes: number;
    idleMinutes: number;
    finishMinutes: number;
    totalMinutes: number;
    isAddon: boolean;
    requiresEvaluation: boolean;
    requiresPatchTest: boolean;
    isActive: boolean;
    variants: {
        id: number;
        label: string;
        price: string;
        priceCents: number;
        extraMinutes: number;
    }[];
};

export type CatalogEntry = {
    id: number;
    name: string;
    nameEn: string | null;
    description: string;
    category: string;
    target: string;
    minutes: number;
    priceMin: number;
    priceMax: number;
    tags: string[];
};
