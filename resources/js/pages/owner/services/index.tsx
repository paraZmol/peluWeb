import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { CatalogSheet } from '@/components/services/catalog-sheet';
import { ServiceForm } from '@/components/services/service-form';
import { ServiceRow } from '@/components/services/service-row';
import type { CatalogEntry, SalonService } from '@/components/services/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index } from '@/routes/services';
import type { Option } from '@/types';

type Props = {
    services: SalonService[];
    catalog: CatalogEntry[];
    categories: Option[];
    targets: Option[];
};

export default function Services({
    services,
    catalog,
    categories,
    targets,
}: Props) {
    const [creating, setCreating] = useState(false);
    const groups = categories
        .map((category) => ({
            ...category,
            services: services.filter((s) => s.category === category.value),
        }))
        .filter((group) => group.services.length > 0);

    return (
        <>
            <Head title="Servicios" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Servicios</h1>
                        <p className="text-sm text-muted-foreground">
                            Lo que ofreces, con su precio y su tiempo. Desmarca
                            "Visible" para pausar uno.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <CatalogSheet
                            catalog={catalog}
                            categories={categories}
                        />
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus /> Crear servicio
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90svh] overflow-y-auto">
                                <DialogHeader>
                                    <DialogTitle>Nuevo servicio</DialogTitle>
                                    <DialogDescription>
                                        Para lo que no está en el catálogo.
                                    </DialogDescription>
                                </DialogHeader>
                                <ServiceForm
                                    categories={categories}
                                    targets={targets}
                                    onSaved={() => setCreating(false)}
                                />
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {groups.length === 0 && (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Aún no tienes servicios. Agrégalos desde el catálogo en
                        un minuto.
                    </p>
                )}

                {groups.map((group) => (
                    <section key={group.value} className="flex flex-col gap-2">
                        <h2 className="text-lg font-semibold">{group.label}</h2>
                        <ul className="divide-y rounded-xl border bg-card">
                            {group.services.map((service) => (
                                <ServiceRow
                                    key={service.id}
                                    service={service}
                                    categories={categories}
                                    targets={targets}
                                />
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </>
    );
}

Services.layout = {
    breadcrumbs: [{ title: 'Servicios', href: index() }],
};
