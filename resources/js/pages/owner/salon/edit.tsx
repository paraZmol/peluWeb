import { Head } from '@inertiajs/react';
import { BrandingSection } from '@/components/salon-settings/branding-section';
import { GallerySection } from '@/components/salon-settings/gallery-section';
import { LocationSection } from '@/components/salon-settings/location-section';
import { ProfileSection } from '@/components/salon-settings/profile-section';
import { ShareSection } from '@/components/salon-settings/share-section';
import type { SalonSettings } from '@/components/salon-settings/types';
import { edit } from '@/routes/salon';
import type { Option } from '@/types';

type Props = {
    salon: SalonSettings;
    businessTypes: Option[];
    clienteles: Option[];
};

const sections = [
    ['compartir', 'Tu página'],
    ['datos', 'Datos'],
    ['marca', 'Marca'],
    ['ubicacion', 'Ubicación'],
    ['galeria', 'Galería'],
] as const;

export default function EditSalon({ salon, businessTypes, clienteles }: Props) {
    return (
        <>
            <Head title="Mi salón" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Mi salón</h1>
                    <p className="text-sm text-muted-foreground">
                        Todo lo que ven tus clientes en tu página.
                    </p>
                </div>

                <nav
                    aria-label="Secciones"
                    className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1"
                >
                    {sections.map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            className="flex min-h-11 shrink-0 items-center rounded-full border bg-card px-4 text-sm hover:bg-accent"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <ShareSection salon={salon} />
                <ProfileSection
                    salon={salon}
                    businessTypes={businessTypes}
                    clienteles={clienteles}
                />
                <BrandingSection salon={salon} />
                <LocationSection salon={salon} />
                <GallerySection salon={salon} />
            </div>
        </>
    );
}

EditSalon.layout = {
    breadcrumbs: [{ title: 'Mi salón', href: edit() }],
};
