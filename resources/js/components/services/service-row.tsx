import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatMinutes, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import { destroy, toggle } from '@/routes/services';
import type { Option } from '@/types';
import { ServiceForm } from './service-form';
import type { SalonService } from './types';

type Props = { service: SalonService; categories: Option[]; targets: Option[] };

export function ServiceRow({ service, categories, targets }: Props) {
    const [editing, setEditing] = useState(false);
    const price =
        service.variants.length > 0
            ? `desde ${formatPrice(service.variants.reduce((min, v) => (v.priceCents < min.priceCents ? v : min)).price)}`
            : formatPrice(service.price);

    return (
        <li
            className={cn(
                'flex flex-col gap-3 p-4 md:flex-row md:items-center',
                !service.isActive && 'opacity-60',
            )}
        >
            <div className="min-w-0 flex-1">
                <p className="font-medium break-words">
                    {service.name}
                    {service.isAddon && (
                        <Badge variant="secondary" className="ml-2">
                            Adicional
                        </Badge>
                    )}
                </p>
                <p className="text-sm text-muted-foreground tabular-nums">
                    {formatMinutes(service.totalMinutes)}
                    {service.idleMinutes > 0 &&
                        ` (incluye ${formatMinutes(service.idleMinutes)} de espera)`}{' '}
                    · {price}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <label className="flex min-h-11 cursor-pointer items-center gap-2 pr-2 text-sm">
                    <Checkbox
                        checked={service.isActive}
                        onCheckedChange={(checked) =>
                            router.patch(
                                toggle(service.id),
                                { is_active: checked === true },
                                { preserveScroll: true },
                            )
                        }
                    />
                    Visible
                </label>
                <Dialog open={editing} onOpenChange={setEditing}>
                    <DialogTrigger asChild>
                        <Button variant="outline" size="sm">
                            <Pencil /> Editar
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[90svh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>{service.name}</DialogTitle>
                            <DialogDescription>
                                Precio, tiempos y opciones de este servicio.
                            </DialogDescription>
                        </DialogHeader>
                        <ServiceForm
                            service={service}
                            categories={categories}
                            targets={targets}
                            onSaved={() => setEditing(false)}
                        />
                    </DialogContent>
                </Dialog>
                <ConfirmRemoveDialog
                    triggerLabel="Quitar"
                    title={`¿Quitar "${service.name}"?`}
                    description="Dejará de aparecer en tu página. Podrás volver a agregarlo desde el catálogo."
                    confirmLabel="Sí, quitar"
                    form={destroy.form(service.id)}
                />
            </div>
        </li>
    );
}
