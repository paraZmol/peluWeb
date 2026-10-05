import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ImageUploadButton } from '@/components/form/image-upload-button';
import { BrandTheme } from '@/components/salon/brand-theme';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyCover,
    store as storeCover,
} from '@/routes/salon/cover';
import {
    destroy as destroyLogo,
    store as storeLogo,
} from '@/routes/salon/logo';
import { update as updateColor } from '@/routes/salon/color';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

/** Curated starting points; any color can be picked. */
const SWATCHES = [
    '#8f5e45',
    '#1f1b18',
    '#b42318',
    '#c2185b',
    '#6d28d9',
    '#1d4ed8',
    '#0f766e',
    '#15803d',
    '#b45309',
];

export function BrandingSection({ salon }: { salon: SalonSettings }) {
    const [color, setColor] = useState(salon.brandColor ?? '#8f5e45');
    const remove = (route: typeof destroyLogo | typeof destroyCover) =>
        router.delete(route(), { preserveScroll: true });

    return (
        <SettingsSection
            id="marca"
            title="Tu marca"
            description="Logo, portada y color. Se aplican en tu página y en tu panel."
        >
            <div className="grid gap-6 md:grid-cols-[auto_1fr]">
                <div className="flex flex-col items-start gap-3">
                    <Label>Logo</Label>
                    <div className="flex size-24 items-center justify-center overflow-hidden rounded-2xl border bg-muted">
                        {salon.logoUrl ? (
                            <img
                                src={salon.logoUrl}
                                alt="Logo actual"
                                className="size-full object-cover"
                            />
                        ) : (
                            <AppLogoIcon className="size-10 text-muted-foreground" />
                        )}
                    </div>
                    <ImageUploadButton
                        route={storeLogo()}
                        label={salon.logoUrl ? 'Cambiar logo' : 'Subir logo'}
                    />
                    {salon.logoUrl && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => remove(destroyLogo)}
                        >
                            Quitar logo
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-3">
                    <Label>Portada</Label>
                    <div className="aspect-video w-full overflow-hidden rounded-xl border bg-muted">
                        {salon.coverUrl && (
                            <img
                                src={salon.coverUrl}
                                alt="Portada actual"
                                className="size-full object-cover"
                            />
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ImageUploadButton
                            route={storeCover()}
                            label={
                                salon.coverUrl
                                    ? 'Cambiar portada'
                                    : 'Subir portada'
                            }
                        />
                        {salon.coverUrl && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => remove(destroyCover)}
                            >
                                Quitar portada
                            </Button>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Una foto horizontal de tu local. La recortamos a 16:9.
                    </p>
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <Label htmlFor="brand-color">Color de tu marca</Label>
                <div className="flex flex-wrap items-center gap-2">
                    {SWATCHES.map((swatch) => (
                        <button
                            key={swatch}
                            type="button"
                            aria-label={`Usar el color ${swatch}`}
                            aria-pressed={color === swatch}
                            onClick={() => setColor(swatch)}
                            className="size-11 rounded-full border-2 border-background ring-2 ring-transparent transition aria-pressed:ring-foreground"
                            style={{ backgroundColor: swatch }}
                        />
                    ))}
                    <input
                        id="brand-color"
                        type="color"
                        value={color}
                        onChange={(event) => setColor(event.target.value)}
                        className="size-11 cursor-pointer rounded-full border bg-transparent"
                    />
                </div>
                <BrandTheme
                    brand={salon.brand}
                    className="flex flex-wrap items-center gap-3 rounded-xl border bg-background p-4"
                >
                    <span className="text-sm text-muted-foreground">
                        Así se ve hoy:
                    </span>
                    <span className="rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                        Reservar
                    </span>
                </BrandTheme>
                <Button
                    type="button"
                    className="rounded-full sm:self-start"
                    onClick={() =>
                        router.put(
                            updateColor(),
                            { brand_color: color },
                            { preserveScroll: true },
                        )
                    }
                >
                    Guardar color
                </Button>
            </div>
        </SettingsSection>
    );
}
